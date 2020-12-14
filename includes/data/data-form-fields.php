<?php

if (!defined('ABSPATH'))
    exit;

    return [
        'freight_enabled' => [
            'title' => __('Enable', 'woocommerce-shipping-freight'),
            'label' => __('Enable Freight', 'woocommerce-shipping-freight'),
            'type' => 'checkbox',
            'default' => 'yes',
        ],

        'api' => [
            'title' => __('API Settings', 'woocommerce-shipping-freight'),
            'type' => 'title',
            'description' => __('Description should go here', 'woocommerce-shipping-freight'),
        ],

        'api_url' => [
            'title' => __('Web Services URL', 'woocommerce-shipping-frieght'),
            'type' => 'text',
            'description' => 'URL of the actual API endpoint to communicate with.',
            'default' => '',
            'custom_attributes' => [
                'autocomplete' => 'off',
            ],
        ],

        'api_user' => [
            'title' => __('Web Services Username', 'woocommerce-shipping-freight'),
            'type' => 'text',
            'description' => 'Username needed to access the API.',
            'default' => '',
            'custom_attributes' => [
                'autocomplete' => 'off',
            ],
        ],

        'api_pass' => [
            'title' => __('Web Services Password', 'woocommerce-shipping-freight'),
            'type' => 'password',
            'description' => 'Password needed to access the API.',
            'default' => '',
            'custom_attributes' => [
                'autocomplete' => 'off',
            ],
        ],

        'freight' => [
            'title' => __('Freight Shipping', 'woocommerce-shipping-freight'),
            'type' => 'title',
            'description' => __('Note: These rates require the customers ZIP.', 'woocommerce-shipping-freight'),
        ],

        'freight_shipper_postcode' => [
            'title' => __('Shipper ZIP / Postcode', 'woocommerce-shipping-freight'),
            'type' => 'text',
            'default' => '',
        ],

        /*'freight_class' => [
            'title' => __('Default Freight Class', 'woocommerce-shipping-freight'),
            'description' => sprintf(__('This is the default freight class for shipments. This can be overridden using <a href="%s">shipping classes</a>',
                'woocommerce-shipping-freight'), $shipping_class_link),
            'type' => 'select',
            'default' => '50',
            'options' => $freight_classes,
        ],*/

        'debug' => [
            'title' => __('Debug Mode', 'woocommerce-shipping-freight'),
            'label' => __('Enable debug mode', 'woocommerce-shipping-freight'),
            'type' => 'checkbox',
            'default' => 'no',
            'desc_tip' => true,
            'description' => __('Enable debug mode to show debugging information on the cart/checkout.', 'woocommerce-shipping-freight'),
        ],
    ];