<?php

if (!defined('ABSPATH')) {
    exit;
}

$shipping_class_link = admin_url('admin.php?page=wc-settings&tab=shipping&section=classes');

return [
    'title' => [
        'title' => __('Method Title', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-shipping-freight'),
        'default' => __('Freight Shipping', 'woocommerce-shipping-freight'),
        'desc_tip' => true,
    ],

    'origin' => [
        'title' => __('Origin Postcode', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'description' => __('Enter the postcode for the <strong>sender</strong>.', 'woocommerce-shipping-freight'),
        'default' => '',
        'desc_tip' => true,
    ],

    'packing_method' => [
        'title' => __('Packing Method', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => 'per_item',
        'class' => 'packing_method',
        'options' => [
            'per_item' => __('Default: Pack items individually (1:1)', 'woocommerce-shipping-freight'),
            /*'box_packing' => __('Pack into boxes with weights and dimensions (1:many)', 'woocommerce-shipping-freight'),*/
        ],
    ],
];
