<?php

/**
 * WC Dependency Class
 *
 * @since 2.0.0
 */
class WC_Dependencies
{
    private static $active_plugins;

    /**
     * Initialize
     *
     * @return  void
     * @since   2.0.0
     */
    public static function init()
    {
        self::$active_plugins = (array) get_option('active_plugins', []);
    }

    /**
     * Is WooCommerce active
     *
     * @return  bool
     * @since   2.0.0
     */
    public static function woocommerce_active_check()
    {
        if (!self::$active_plugins)
            self::init();

        return in_array('woocommerce/woocommerce.php', self::$active_plugins)
            || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
    }
}


