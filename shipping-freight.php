<?php

/**
 * Backwards compatibility
 *
 * @since 2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

$active_plugins = get_option('active_plugins', []);

foreach ($active_plugins as $key => $active_plugin) {
    if (strpos($active_plugin, '/shipping-freight.php') !== false) {
        $active_plugins[$key] = str_replace('/shipping-freight.php', '/woocommerce-shipping-freight.php', $active_plugin);
    }
}
update_option('active_plugins', $active_plugins);
