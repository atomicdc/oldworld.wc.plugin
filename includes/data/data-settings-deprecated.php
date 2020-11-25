<?php

if (!defined('ABSPATH')) {
    exit;
}

$freight_classes = include('data-freight-classes.php');
$smartpost_hubs = include('data-smartpost-hubs.php');
$smartpost_hubs = ['' => __('N/A', 'woocommerce-shipping-freight')] + $smartpost_hubs;
$shipping_class_link = version_compare(WC_VERSION, '2.6',
    '>=') ? admin_url('admin.php?page=wc-settings&tab=shipping&section=classes') : admin_url('edit-tags.php?taxonomy=product_shipping_class&post_type=product');

/**
 * Array of settings
 */
return [
    'enabled' => [
        'title' => __('Enable Freight Shipping', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'label' => __('Enable this shipping method', 'woocommerce-shipping-freight'),
        'default' => 'no',
    ],
    'debug' => [
        'title' => __('Debug Mode', 'woocommerce-shipping-freight'),
        'label' => __('Enable debug mode', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('Enable debug mode to show debugging information on the cart/checkout.',
            'woocommerce-shipping-freight'),
    ],
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
    'availability' => [
        'title' => __('Method Availability', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => 'all',
        'class' => 'availability',
        'options' => [
            'all' => __('All Countries', 'woocommerce-shipping-freight'),
            'specific' => __('Specific Countries', 'woocommerce-shipping-freight'),
        ],
    ],
    'countries' => [
        'title' => __('Specific Countries', 'woocommerce-shipping-freight'),
        'type' => 'multiselect',
        'class' => 'chosen_select',
        'css' => 'width: 450px;',
        'default' => '',
        'options' => WC()->countries->get_allowed_countries(),
    ],
    'api' => [
        'title' => __('API Settings', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('#',
            'woocommerce-shipping-freight'),
    ],
    /*'account_number' => [
        'title' => __('Freight Account Number', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'description' => '',
        'default' => '',
    ],*/
    /*'meter_number' => [
        'title' => __('Freight Meter Number', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'description' => '',
        'default' => '',
    ],*/
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
    /*'production' => [
        'title' => __('Production Key', 'woocommerce-shipping-freight'),
        'label' => __('This is a production key', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('If this is a production API key and not a developer key, check this box.',
            'woocommerce-shipping-freight'),
    ],*/
    /*'packing' => [
        'title' => __('Packages', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('The following settings determine how items are packed before being sent to Freight Shipping.',
            'woocommerce-shipping-freight'),
    ],*/
    /*'packing_method' => [
        'title' => __('Parcel Packing Method', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => '',
        'class' => 'packing_method',
        'options' => [
            'per_item' => __('Default: Pack items individually', 'woocommerce-shipping-freight'),
            'box_packing' => __('Recommended: Pack into boxes with weights and dimensions', 'woocommerce-shipping-freight'),
        ],
    ],*/
    /*'boxes' => [
        'type' => 'box_packing',
    ],*/
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
        'default' => 'no',
        'description' => __('Enables residential flag. If you account has Address Validation enabled, this will be turned off/on automatically.',
            'woocommerce-shipping-freight'),
        'desc_tip' => true,
    ],
    /*'insure_contents' => [
        'title' => __('Insurance', 'woocommerce-shipping-freight'),
        'label' => __('Enable Insurance', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'yes',
        'desc_tip' => true,
        'description' => __('####', 'woocommerce-shipping-freight'),
    ],*/
    /*'freight_one_rate' => [
        'title' => __('Freight Shipping One Rate', 'woocommerce-shipping-freight'),
        'label' => sprintf(__('Enable %sFreight One Rates%s', 'woocommerce-shipping-freight'),
            '###', ' ###'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('Freight Rates will be offered if the items are packed into a valid crate, and the origin and destination is the US.',
            'woocommerce-shipping-freight'),
    ],*/
    /*'direct_distribution' => [
        'title' => __('International Ground Direct Distribution', 'woocommerce-shipping-freight'),
        'label' => __('Enable direct distribution Rates.', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('Enable to get direct distribution rates if your account has this enabled.',
            'woocommerce-shipping-freight'),
    ],*/
    /*'request_type' => [
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
    /*'smartpost_hub' => [
        'title' => __('Freight SmartPost Hub', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'description' => __('Only required if using SmartPost.', 'woocommerce-shipping-freight'),
        'desc_tip' => true,
        'default' => '',
        'options' => $smartpost_hubs,
    ],*/
    /*'offer_rates' => [
        'title' => __('Offer Rates', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'description' => '',
        'default' => 'all',
        'options' => [
            'all' => __('Offer the customer all returned rates', 'woocommerce-shipping-freight'),
            'cheapest' => __('Offer the customer the cheapest rate only, anonymously', 'woocommerce-shipping-freight'),
        ],
    ],*/
    /*'services' => [
        'type' => 'services',
    ],*/
    'freight' => [
        'title' => __('Freight LTL Freight', 'woocommerce-shipping-freight'),
        'type' => 'title',
        'description' => __('If your account supports Freight, we need some additional details to get LTL rates. Note: These rates require the customers CITY so won\'t display until checkout.',
            'woocommerce-shipping-freight'),
    ],
    'freight_enabled' => [
        'title' => __('Enable', 'woocommerce-shipping-freight'),
        'label' => __('Enable Freight', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    /*'freight_number' => [
        'title' => __('Freight Freight Account Number', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'description' => '',
        'default' => '',
        'placeholder' => __('Defaults to your main account number', 'woocommerce-shipping-freight'),
    ],*/
    'freight_billing_street' => [
        'title' => __('Billing Street Address', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_billing_street_2' => [
        'title' => __('Billing Street Address', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_billing_city' => [
        'title' => __('Billing City', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_billing_state' => [
        'title' => __('Billing State Code', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_billing_postcode' => [
        'title' => __('Billing ZIP / Postcode', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_billing_country' => [
        'title' => __('Billing Country Code', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_street' => [
        'title' => __('Shipper Street Address', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_street_2' => [
        'title' => __('Shipper Street Address 2', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_city' => [
        'title' => __('Shipper City', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_state' => [
        'title' => __('Shipper State Code', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_postcode' => [
        'title' => __('Shipper ZIP / Postcode', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_country' => [
        'title' => __('Shipper Country Code', 'woocommerce-shipping-freight'),
        'type' => 'text',
        'default' => '',
    ],
    'freight_shipper_residential' => [
        'title' => __('Residential', 'woocommerce-shipping-freight'),
        'label' => __('Shipper Address is Residential?', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
    ],
    'freight_class' => [
        'title' => __('Default Freight Class', 'woocommerce-shipping-freight'),
        'description' => sprintf(__('This is the default freight class for shipments. This can be overridden using <a href="%s">shipping classes</a>',
            'woocommerce-shipping-freight'), $shipping_class_link),
        'type' => 'select',
        'default' => '50',
        'options' => $freight_classes,
    ],
];
