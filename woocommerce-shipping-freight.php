<?php
/**
 * Plugin Name: WooCommerce Freight Shipping
 * Plugin URI: https://atomicdc.com
 * Description: Obtain shipping rates dynamically via the Bedrock API for your orders.
 * Version: 2.0.0
 * Author: Rubberducky Dev
 * Author URI: https://rubberduckydev.io
 * WC requires at least: 2.6
 * WC tested up to: 3.6
 * Tested up to: 5.0
 * Copyright: 2020 Atomic Design & Consulting
 */

if (!function_exists('woothemes_queue_update')) {
    require_once('woo-includes/woo-functions.php');
}

define('WC_SHIPPING_FREIGHT_VERSION', '2.0.0');

class WC_Shipping_Freight_Init
{
    /**
     * Plugin version
     *
     * @var string
     * @since 2.0.0
     */
    public $version;

    /**
     * @var   object Class Instance
     * @since 2.0.0
     */
    private static $instance;

    /**
     * Get the class instance
     *
     * @return object $this
     * @since  2.0.0
     */
    public static function get_instance()
    {
        return self::$instance ?? self::$instance = new self();
    }

    /**
     * Initialize the plugin's public actions
     */
    public function __construct()
    {
        $this->version = WC_SHIPPING_FREIGHT_VERSION;

        if (class_exists('WC_Shipping_Method') && class_exists('XMLWriter') && version_compare(WC_VERSION, '2.6.0', '>')) {
            add_action('admin_init', [$this, 'maybe_install'], 5);
            add_action('init', [$this, 'load_textdomain']);
            add_filter('plugin_action_links_'.plugin_basename(__FILE__), [$this, 'plugin_links']);
            add_action('woocommerce_shipping_init', [$this, 'includes']);
            add_filter('woocommerce_shipping_methods', [$this, 'add_method']);
            add_action('admin_notices', [$this, 'environment_check']);

            $freight_settings = get_option('woocommerce_freight_settings', []);

            if (isset($freight_settings['freight_enabled']) && $freight_settings['freight_enabled'] === 'yes') {
                add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
                /*if (is_admin()) {
                    include(__DIR__.'/includes/class-wc-freight-mapping.php');
                }*/
            }
        } else {
            add_action('admin_notices', [$this, 'wc_deactivated']);
        }
    }

    /**
     *
     * @return  void
     * @since   2.0.0
     */
    public function environment_check()
    {
        if (get_woocommerce_currency() !== 'USD' || WC()->countries->get_base_country() !== 'US') {
            echo '
                <div class="error">
                    <p>'.__('Freight Shipping requires that the WooCommerce currency is set to US Dollars and that the base country/region is set to United States.',
                    'woocommerce-shipping-freight').'</p>
                </div>
            ';
        }
    }

    /**
     *
     * @return  void
     * @since   2.0.0
     */
    public function includes()
    {
        include_once(__DIR__.'/includes/class-wc-freight-privacy.php');
        include_once(__DIR__.'/includes/class-wc-shipping-freight.php');
    }

    /**
     * Add Freight Shipping method to WC
     *
     * @param  mixed  $methods
     *
     * @return void
     * @since  2.0.0
     */
    public function add_method($methods)
    {
        $methods['freight'] = 'WC_Shipping_Freight';

        return $methods;
    }

    /**
     * Localisation
     *
     * @return void
     * @since  2.0.0
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('woocommerce-shipping-freight', false, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    /**
     * Plugin page links
     *
     * @param  array  $links  Plugin action links.
     *
     * @return array Plugin action links.
     * @since  2.0.0
     */
    public function plugin_links($links)
    {
        $plugin_links = [
            '<a href="'.admin_url('admin.php?page=wc-settings&tab=shipping&section=freight').'">'.__('Settings',
                'woocommerce-shipping-freight').'</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * XML library not installed notice
     *
     * @return void
     * @since  2.0.0
     */
    public function wc_deactivated()
    {
        if (!class_exists('XMLWriter')) {
            echo '<div class="error"><p>'.__('Your server does not provide XML support (XMLWriter Library) which is required functionality for communicating with the pricing API. You will need to reach out to your web hosting provider to get information on how to enable this functionality on your server.',
                    'woocommerce-shipping-freight').'</p></div>';
        }

        if (!class_exists('WC_Shipping_Method')) {
            echo '<div class="error"><p>'.sprintf(__('Freight Shipping requires %s to be installed and active.',
                    'woocommerce-shipping-freight'), 'WooCommerce').'</p></div>';
        }

        if (version_compare(WC_VERSION, '2.6.0', '<')) {
            echo '<div class="error"><p>'.sprintf(__('Freight Shipping Requires WooCommerce version of 2.6.0 or later.',
                    'woocommerce-shipping-freight'), 'WooCommerce').'</p></div>';
        }
    }

    /**
     * See if we need to install any upgrades and call the install
     *
     * @return  bool
     * @since   2.0.0
     */
    public function maybe_install()
    {
        if (!defined('DOING_AJAX')
            && !defined('IFRAME_REQUEST')
            && version_compare(WC_VERSION, '2.6.0', '>=')
            && version_compare(get_option('wc_freight_version'), '2.0.0', '<')) {
            $this->install();
        }

        return true;
    }

    /**
     *
     * @return  bool
     * @since   2.0.0
     */
    public function install()
    {
        $freight_settings = get_option('woocommerce_freight_settings', false);

        if ($freight_settings) {
            global $wpdb;

            unset($freight_settings['enabled']);
            unset($freight_settings['availability']);
            unset($freight_settings['countries']);

            if (!$this->is_zone_has_freight(0)) {
                $wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."woocommerce_shipping_zone_methods (zone_id, method_id, method_order, is_enabled) VALUES (%d, %s, %d, %d)",
                    0, 'freight', 1, 1));

                $instance = $wpdb->insert_id;
                add_option('woocommerce_freight_'.$instance.'_settings', $freight_settings);
            }
            update_option('woocommerce_freight_show_upgrade_notice', 'yes');
        }
        update_option('wc_freight_version', $this->version);
    }

    /**
     * Show the user a notice for plugin updates
     *
     * @return void
     * @since 2.0.0
     */
    public function upgrade_notice()
    {
        $show_notice = get_option('woocommerce_freight_show_upgrade_notice');

        if ($show_notice !== 'yes') {
            return;
        }

        $query_args = ['page' => 'wc-settings', 'tab' => 'shipping'];
        $zones_admin_url = add_query_arg($query_args, get_admin_url().'admin.php');

        ?>
        <div class="notice notice-success is-dismissible wc-freight-notice">
            <p>
                <?= sprintf(__('Freight Shipping supports shipping zones. The zone settings were added to a new Freight Shipping method on the "Rest of the World" Zone. See the zones %1$shere%2$s ',
                    'woocommerce-shipping-freight'), '<a href="'.$zones_admin_url.'">', '</a>'); ?>
            </p>
        </div>

        <script type="application/javascript">
            jQuery('.notice.wc-freight-notice').on('click', '.notice-dismiss', function () {
                wp.ajax.post('freight_dismiss_upgrade_notice');
            });
        </script><?php
    }

    /**
     * Turn off upgrade notice
     *
     * @return bool
     * @since  2.0.0
     */
    public function dismiss_upgrade_notice()
    {
        update_option('woocommerce_freight_show_upgrade_notice', 'no');
    }

    /**
     * Check if given zone_id has freight shipping method instance
     *
     * @param  int  $zone_id  Zone ID
     *
     * @return  bool
     * @since   2.0.0
     */
    public function is_zone_has_freight($zone_id)
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(instance_id) FROM ".$wpdb->prefix."woocommerce_shipping_zone_methods WHERE method_id = 'freight' AND zone_id = %d",
                $zone_id)) > 0;
    }
}

add_action('plugins_loaded', ['WC_Shipping_Freight_Init', 'get_instance'], 0);
