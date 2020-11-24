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
 *
 */
if (!function_exists('woothemes_queue_update')) {
    require_once('woo-includes/woo-functions.php');
}

define('WC_SHIPPING_FREIGHT_VERSION', '2.0.0');

class WC_Shipping_Freight_Init
{
    /**
     * Plugin's version.
     *
     * @since 2.0.0
     * @var string
     */
    public $version;

    /** @var object Class Instance */
    private static $instance;

    /**
     * Get the class instance
     */
    public static function get_instance()
    {
        return null === self::$instance ? (self::$instance = new self) : self::$instance;
    }

    /**
     * Initialize the plugin's public actions
     */
    public function __construct()
    {
        $this->version = WC_SHIPPING_FREIGHT_VERSION;

        if (class_exists('WC_Shipping_Method') && class_exists('SoapClient')) {
            add_action('admin_init', [$this, 'maybe_install'], 5);
            add_action('init', [$this, 'load_textdomain']);
            add_filter('plugin_action_links_'.plugin_basename(__FILE__), [$this, 'plugin_links']);
            add_action('woocommerce_shipping_init', [$this, 'includes']);
            add_filter('woocommerce_shipping_methods', [$this, 'add_method']);
            add_action('admin_notices', [$this, 'environment_check']);
            add_action('admin_notices', [$this, 'upgrade_notice']);
            add_action('wp_ajax_freight_dismiss_upgrade_notice', [$this, 'dismiss_upgrade_notice']);

            $freight_settings = get_option('woocommerce_freight_settings', []);

            if (isset($freight_settings['freight_enabled']) && 'yes' === $freight_settings['freight_enabled']) {
                // Make the city field show in the calculator (for freight)
                add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');

                // Add freight class option for shipping classes (for freight)
                if (is_admin()) {
                    include(dirname(__FILE__).'/includes/class-wc-freight-mapping.php');
                }
            }
        } else {
            add_action('admin_notices', [$this, 'wc_deactivated']);
        }
    }

    /**
     *
     */
    public function environment_check()
    {
        if (version_compare(WC_VERSION, '2.6.0', '<')) {
            return;
        }

        if (get_woocommerce_currency() !== 'USD' || WC()->countries->get_base_country() !== 'US') {
            echo '<div class="error">
				<p>'.__('Freight Shipping requires that the WooCommerce currency is set to US Dollars and that the base country/region is set to United States.',
                    'woocommerce-shipping-freight').'</p>
			</div>';
        }
    }

    /**
     * woocommerce_init_shipping_table_rate function.
     *
     * @access  public
     * @return  void
     * @since   2.0.0
     */
    public function includes()
    {
        include_once(dirname(__FILE__).'/includes/class-wc-freight-privacy.php');

        if (version_compare(WC_VERSION, '2.6.0', '<')) {
            include_once(dirname(__FILE__).'/includes/class-wc-shipping-freight-deprecated.php');
        } else {
            include_once(dirname(__FILE__).'/includes/class-wc-shipping-freight.php');
        }
    }

    /**
     * Add Freight shipping method to WC
     *
     * @access public
     *
     * @param  mixed  $methods
     *
     * @return void
     */
    public function add_method($methods)
    {
        if (version_compare(WC_VERSION, '2.6.0', '<')) {
            $methods[] = 'WC_Shipping_Freight';
        } else {
            $methods['freight'] = 'WC_Shipping_Freight';
        }

        return $methods;
    }

    /**
     * Localisation
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('woocommerce-shipping-freight', false, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    /**
     * Plugin page links.
     *
     * @param  array  $links  Plugin action links.
     *
     * @return array Plugin action links.
     * @version 3.4.9
     *
     */
    public function plugin_links($links)
    {
        $plugin_links = [
            '<a href="'.admin_url('admin.php?page=wc-settings&tab=shipping&section=freight').'">'.__('Settings',
                'woocommerce-shipping-freight').'</a>',
            '<a href="https://atomicdc.com/">'.__('Support', 'woocommerce-shipping-freight').'</a>',
            '<a href="https://docs.rubberduckydev.io/wordpress/plugin/freight-shipping/">'.__('Docs',
                'woocommerce-shipping-freight').'</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * XML library not installed notice
     */
    public function wc_deactivated()
    {
        if (!class_exists('SoapClient')) {
            echo '<div class="error"><p>'.__('Your server does not provide SOAP support which is required functionality for communicating with Bedrock API. You will need to reach out to your web hosting provider to get information on how to enable this functionality on your server.',
                    'woocommerce-shipping-freight').'</p></div>';
        }

        if (!class_exists('WC_Shipping_Method')) {
            echo '<div class="error"><p>'.sprintf(__('Freight Shipping requires %s to be installed and active.',
                    'woocommerce-shipping-freight'), '#').'</p></div>';
        }
    }

    /**
     * See if we need to install any upgrades and call the install
     *
     * @access  public
     * @return  bool
     * @since   2.0.0
     */
    public function maybe_install()
    {
        // only need to do this for versions less than 2.0.0 to migrate
        // settings to shipping zone instance
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
     * @access  public
     * @return  bool
     * @since   2.0.0
     */
    public function install()
    {
        // get all saved settings and cache it
        $freight_settings = get_option('woocommerce_freight_settings', false);

        // settings exists
        if ($freight_settings) {
            global $wpdb;

            // unset un-needed settings
            unset($freight_settings['enabled']);
            unset($freight_settings['availability']);
            unset($freight_settings['countries']);

            // add it to the "rest of the world" zone when no freight.
            if (!$this->is_zone_has_freight(0)) {
                $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}woocommerce_shipping_zone_methods ( zone_id, method_id, method_order, is_enabled ) VALUES ( %d, %s, %d, %d )",
                    0, 'freight', 1, 1));
                // add settings to the newly created instance to options table
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
     * @since 2.0.0
     */
    public function upgrade_notice()
    {
        $show_notice = get_option('woocommerce_freight_show_upgrade_notice');

        if ('yes' !== $show_notice) {
            return;
        }

        $query_args = ['page' => 'wc-settings', 'tab' => 'shipping'];
        $zones_admin_url = add_query_arg($query_args, get_admin_url().'admin.php');
        ?>
        <div class="notice notice-success is-dismissible wc-freight-notice">
            <p><?= sprintf(__('Freight Shipping now supports shipping zones. The zone settings were added to a new Freight Shipping method on the "Rest of the World" Zone. See the zones %1$shere%2$s ', 'woocommerce-shipping-freight'), '<a href="'.$zones_admin_url.'">', '</a>'); ?></p>
        </div>

        <script type="application/javascript">
            jQuery('.notice.wc-freight-notice').on('click', '.notice-dismiss', function () {
                wp.ajax.post('freight_dismiss_upgrade_notice');
            });
        </script>
        <?php
    }

    /**
     * Turn of the dismisable upgrade notice.
     *
     * @since 2.0.0
     */
    public function dismiss_upgrade_notice()
    {
        update_option('woocommerce_freight_show_upgrade_notice', 'no');
    }

    /**
     * Helper method to check whether given zone_id has freight shipping method instance.
     *
     * @param  int  $zone_id  Zone ID
     *
     * @return  bool True if given zone_id has freight shipping method instance
     * @since   2.0.0
     *
     */
    public function is_zone_has_freight($zone_id)
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(instance_id) FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'freight' AND zone_id = %d",
                $zone_id)) > 0;
    }
}

add_action('plugins_loaded', ['WC_Shipping_Freight_Init', 'get_instance'], 0);
