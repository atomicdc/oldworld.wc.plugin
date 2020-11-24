<?php

if (!defined('ABSPATH')) {
    exit;
}

$smartpost_hubs = include('data-smartpost-hubs.php');
$smartpost_hubs = ['' => __('N/A', 'woocommerce-shipping-freight')] + $smartpost_hubs;
$shipping_class_link = version_compare(WC_VERSION, '2.6',
    '>=') ? admin_url('admin.php?page=wc-settings&tab=shipping&section=classes') : admin_url('edit-tags.php?taxonomy=product_shipping_class&post_type=product');

/**
 * Array of settings
 */
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
        'description' => __('The following settings determine how items are packed before being sent to Freight.',
            'woocommerce-shipping-freight'),
    ],
    'packing_method' => [
        'title' => __('Parcel Packing Method', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'default' => '',
        'class' => 'packing_method',
        'options' => [
            'per_item' => __('Default: Pack items individually', 'woocommerce-shipping-freight'),
            'box_packing' => __('Recommended: Pack into boxes with weights and dimensions', 'woocommerce-shipping-freight'),
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
        'default' => 'no',
        'description' => __('Enables residential flag. If you account has Address Validation enabled, this will be turned off/on automatically.',
            'woocommerce-shipping-freight'),
        'desc_tip' => true,
    ],
    'insure_contents' => [
        'title' => __('Insurance', 'woocommerce-shipping-freight'),
        'label' => __('Enable Insurance', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'yes',
        'desc_tip' => true,
        'description' => __('Sends the package value to Freight Shipping for insurance.', 'woocommerce-shipping-freight'),
    ],
    'freight_one_rate' => [
        'title' => __('Freight One', 'woocommerce-shipping-freight'),
        'label' => sprintf(__('Enable %sFreight One Rates%s', 'woocommerce-shipping-freight'),
            '####', '####'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('Freight One Rates will be offered if the items are packed into a valid Freight One box, and the origin and destination is the US.',
            'woocommerce-shipping-freight'),
    ],
    'direct_distribution' => [
        'title' => __('International Ground Direct Distribution', 'woocommerce-shipping-freight'),
        'label' => __('Enable direct distribution Rates.', 'woocommerce-shipping-freight'),
        'type' => 'checkbox',
        'default' => 'no',
        'desc_tip' => true,
        'description' => __('Enable to get direct distribution rates if your account has this enabled.  For US to Canada or Canada to US shipments.',
            'woocommerce-shipping-freight'),
    ],
    'request_type' => [
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
    ],
    'smartpost_hub' => [
        'title' => __('Freight SmartPost Hub', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'description' => __('Only required if using SmartPost.', 'woocommerce-shipping-freight'),
        'desc_tip' => true,
        'default' => '',
        'options' => $smartpost_hubs,
    ],
    'offer_rates' => [
        'title' => __('Offer Rates', 'woocommerce-shipping-freight'),
        'type' => 'select',
        'description' => '',
        'default' => 'all',
        'options' => [
            'all' => __('Offer the customer all returned rates', 'woocommerce-shipping-freight'),
            'cheapest' => __('Offer the customer the cheapest rate only, anonymously', 'woocommerce-shipping-freight'),
        ],
    ],
    'services' => [
        'type' => 'services',
    ],
];
