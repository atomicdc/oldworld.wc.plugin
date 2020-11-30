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

    'packing' => [
        'title' => __('Packages', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('The following settings determine how items are packed before being sent to freight.',
            'woocommerce-shipping-freight'),
    ],

    'packing_method' => [
        'title' => __('Packing Method', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => '',
        'class' => 'packing_method',
        'options' => [
            'per_item' => __('Default: Pack items individually (1:1)', 'woocommerce-shipping-freight'),
            'box_packing' => __('Pack into boxes with weights and dimensions (1:many)', 'woocommerce-shipping-freight'),
        ],
    ],

    'boxes' => [
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
         * 'description' => __('Enables residential flag. If you account has Address Validation enabled, this will be turned off/on automatically.', 'woocommerce-shipping-freight'),*/
        'description' => __('Enables residential flag.', 'woocommerce-shipping-freight'),
        'desc_tip' => true,
        'class' => 'manuallyChecked'

    ],

    /* //this feature is out of scope, always List Rates.
     * 'request_type' => [
        'title' => __('Request Type', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => 'LIST',
        'class' => '',
        'desc_tip' => true,
        'options' => [
            'LIST' => __('List rates', 'woocommerce-shipping-freight'),
            'ACCOUNT' => __('Account rates', 'woocommerce-shipping-freight'),
        ],
        'description' => __('Choose whether to return List or Account (discounted) rates from the API.',
            'woocommerce-shipping-freight'),
    ],*/

    /* // this feature is out of scope, only one rate will be retrieved for now.
     *'offer_rates' => [
        'title' => __('Offer Rates', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'description' => '',
        'default' => 'all',
        'options' => [
            'all' => __('Offer the customer all returned rates', 'woocommerce-shipping-freight'),
            'cheapest' => __('Offer the customer the one rate only.', 'woocommerce-shipping-freight'),
        ],
    ],*/

    'services' => [
        'type' => 'services',
    ],
];
