<?php

if (!defined('ABSPATH'))
    exit;

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

    /*'packing' => [
        'title' => __('Packages', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('The following settings determine how items are packed before being sent to freight.',
            'woocommerce-shipping-freight'),
    ],*/

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

    /*'boxes' => [
        'type' => 'box_packing',
    ],

    'rates' => [
        'title' => __('Rates and Services', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('The following settings determine the rates you offer your customers.',
            'woocommerce-shipping-freight'),
    ],

    'residential' => [
        'title' => __('Residential', 'woocommerce-shipping-freight'),
        'label' => __('Default to residential delivery', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'disabled' => true,
        'default' => 'yes',
        /* // address validation is out of scope for now.
         * 'description' => __('Enables residential flag. If you account has Address Validation enabled, this will be turned off/on automatically.', 'woocommerce-shipping-freight'),
        'description' => __('Enables residential flag.', 'woocommerce-shipping-freight'),
        'desc_tip' => true,
        'class' => 'manuallyChecked'

    ],*/

    /*'services' => [
        'type' => 'services',
    ],*/
];
