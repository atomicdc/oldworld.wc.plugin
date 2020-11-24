<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Shipping_Freight class.
 *
 * @extends WC_Shipping_Method
 */
class WC_Shipping_Freight extends WC_Shipping_Method
{
    private $default_boxes;
    private $found_rates;
    private $services;

    /**
     * Constructor
     */
    public function __construct($instance_id = 0)
    {
        $this->id = 'freight';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Freight Shipping', 'woocommerce-shipping-freight');
        $this->method_description = __('The Freight Shipping extension obtains rates dynamically from the Bedrock API during cart/checkout.',
            'woocommerce-shipping-freight');
        $this->rateservice_version = 16;
        $this->addressvalidationservice_version = 2;
        $this->default_boxes = include(dirname(__FILE__).'/data/data-box-sizes.php');
        $this->services = include(dirname(__FILE__).'/data/data-service-codes.php');
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'settings',
        ];
        $this->init();
    }

    /**
     * is_available function.
     *
     * @param  array  $package
     *
     * @return bool
     */
    public function is_available($package)
    {
        if (empty($package['destination']['country'])) {
            return false;
        }

        return apply_filters('woocommerce_shipping_'.$this->id.'_is_available', true, $package);
    }

    /**
     *
     * @return void
     * @since   2.0.0
     */
    private function set_settings()
    {
        // Define user set variables
        $this->title = $this->get_option('title', $this->method_title);
        $this->origin = apply_filters('woocommerce_freight_origin_postal_code',
            str_replace(' ', '', strtoupper($this->get_option('origin'))));
        $this->origin_country = apply_filters('woocommerce_freight_origin_country_code', WC()->countries->get_base_country());
        $this->account_number = $this->get_option('account_number');
        $this->meter_number = $this->get_option('meter_number');
        $this->smartpost_hub = $this->get_option('smartpost_hub');
        $this->api_key = $this->get_option('api_key');
        $this->api_pass = $this->get_option('api_pass');
        $this->production = (($bool = $this->get_option('production')) && $bool === 'yes');
        $this->debug = (($bool = $this->get_option('debug')) && $bool === 'yes');
        $this->insure_contents = (($bool = $this->get_option('insure_contents')) && $bool === 'yes');
        $this->request_type = $this->get_option('request_type', 'LIST');
        $this->packing_method = $this->get_option('packing_method', 'per_item');
        $this->boxes = $this->get_option('boxes', []);
        $this->custom_services = $this->get_option('services', []);
        $this->offer_rates = $this->get_option('offer_rates', 'all');
        $this->residential = (($bool = $this->get_option('residential')) && $bool === 'yes');
        $this->freight_enabled = (($bool = $this->get_option('freight_enabled')) && $bool === 'yes');
        $this->freight_one_rate = (($bool = $this->get_option('freight_one_rate')) && $bool === 'yes');
        $this->direct_distribution = (($bool = $this->get_option('direct_distribution')) && $bool === 'yes');
        $this->freight_one_rate_package_ids = [
            'FREIGHT_SMALL_BOX',
            'FREIGHT_MEDIUM_BOX',
            'FREIGHT_LARGE_BOX',
            'FREIGHT_EXTRA_LARGE_BOX',
            'FREIGHT_PAK',
            'FREIGHT_ENVELOPE',
            'FREIGHT_TUBE',
        ];

        if ($this->freight_enabled) {
            $this->freight_class = str_replace(['CLASS_', '.'], ['', '_'], $this->get_option('freight_class'));
            $this->freight_number = $this->get_option('freight_number', $this->account_number);
            $this->freight_billing_street = $this->get_option('freight_billing_street');
            $this->freight_billing_street_2 = $this->get_option('freight_billing_street_2');
            $this->freight_billing_city = $this->get_option('freight_billing_city');
            $this->freight_billing_state = $this->get_option('freight_billing_state');
            $this->freight_billing_postcode = $this->get_option('freight_billing_postcode');
            $this->freight_billing_country = $this->get_option('freight_billing_country');
            $this->freight_shipper_street = $this->get_option('freight_shipper_street');
            $this->freight_shipper_street_2 = $this->get_option('freight_shipper_street_2');
            $this->freight_shipper_city = $this->get_option('freight_shipper_city');
            $this->freight_shipper_state = $this->get_option('freight_shipper_state');
            $this->freight_shipper_postcode = $this->get_option('freight_shipper_postcode');
            $this->freight_shipper_country = $this->get_option('freight_shipper_country');
            $this->freight_shipper_residential = (($bool = $this->get_option('freight_shipper_residential')) && $bool === 'yes');
        }

        // Insure contents requires matching currency to country
        switch (WC()->countries->get_base_country()) {
            case 'US' :
                if ('USD' !== get_woocommerce_currency()) {
                    $this->insure_contents = false;
                }
                break;
            case 'CA' :
                if ('CAD' !== get_woocommerce_currency()) {
                    $this->insure_contents = false;
                }
                break;
        }
    }

    /**
     * init function.
     */
    private function init()
    {
        // Load the settings.
        $this->init_form_fields();
        $this->set_settings();

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);
    }

    /**
     * Process settings on save
     *
     * @access  public
     * @return void
     * @version 3.4.0
     * @since   3.4.0
     */
    public function process_admin_options()
    {
        parent::process_admin_options();

        $this->set_settings();
    }

    /**
     * Load admin scripts
     *
     * @return void
     * @version 3.4.0
     * @since   3.4.0
     */
    public function load_admin_scripts()
    {
        wp_enqueue_script('jquery-ui-sortable');
    }

    /**
     * Output a message or error
     *
     * @param  string  $message
     * @param  string  $type
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug || (current_user_can('manage_options') && 'error' == $type)) {
            wc_add_notice($message, $type);
        }
    }

    /**
     * init_form_fields function.
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = include(dirname(__FILE__).'/data/data-settings.php');

        $freight_classes = include(dirname(__FILE__).'/data/data-freight-classes.php');

        $this->form_fields = [
            'api' => [
                'title' => __('API Settings', 'woocommerce-shipping-freight'),
                'type' => 'title',
                'description' => __('Description should go here',
                    'woocommerce-shipping-freight'),
            ],
            'account_number' => [
                'title' => __('Freight Shipping Account Number', 'woocommerce-shipping-freight'),
                'type' => 'text',
                'description' => '',
                'default' => '',
            ],
            'meter_number' => [
                'title' => __('Freight Meter Number', 'woocommerce-shipping-freight'),
                'type' => 'text',
                'description' => '',
                'default' => '',
            ],
            'api_key' => [
                'title' => __('Web Services Key', 'woocommerce-shipping-freight'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                ],
            ],
            'api_pass' => [
                'title' => __('Web Services Password', 'woocommerce-shipping-freight'),
                'type' => 'password',
                'description' => '',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                ],
            ],
            'production' => [
                'title' => __('Production Key', 'woocommerce-shipping-freight'),
                'label' => __('This is a production key', 'woocommerce-shipping-freight'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('If this is a production API key and not a developer key, check this box.',
                    'woocommerce-shipping-freight'),
            ],
            'freight' => [
                'title' => __('Freight Shipping', 'woocommerce-shipping-freight'),
                'type' => 'title',
                'description' => __('Note: These rates require the customers CITY so won\'t display until checkout.',
                    'woocommerce-shipping-freight'),
            ],
            'freight_enabled' => [
                'title' => __('Enable', 'woocommerce-shipping-freight'),
                'label' => __('Enable Freight', 'woocommerce-shipping-freight'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'freight_number' => [
                'title' => __('Bedrock Freight Account Number', 'woocommerce-shipping-freight'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'placeholder' => __('Defaults to your main account number', 'woocommerce-shipping-freight'),
            ],
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
            'debug' => [
                'title' => __('Debug Mode', 'woocommerce-shipping-freight'),
                'label' => __('Enable debug mode', 'woocommerce-shipping-freight'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('Enable debug mode to show debugging information on the cart/checkout.',
                    'woocommerce-shipping-freight'),
            ],
        ];
    }

    /**
     * generate_services_html function.
     */
    public function generate_services_html()
    {
        ob_start();
        include('views/html-services.php');
        return ob_get_clean();
    }

    /**
     * generate_box_packing_html function.
     */
    public function generate_box_packing_html()
    {
        ob_start();
        include('views/html-box-packing.php');
        return ob_get_clean();
    }

    /**
     * validate_box_packing_field function.
     *
     * @param  mixed  $key
     */
    public function validate_box_packing_field($key)
    {
        $boxes_name = isset($_POST['boxes_name']) ? $_POST['boxes_name'] : [];
        $boxes_length = isset($_POST['boxes_length']) ? $_POST['boxes_length'] : [];
        $boxes_width = isset($_POST['boxes_width']) ? $_POST['boxes_width'] : [];
        $boxes_height = isset($_POST['boxes_height']) ? $_POST['boxes_height'] : [];
        $boxes_box_weight = isset($_POST['boxes_box_weight']) ? $_POST['boxes_box_weight'] : [];
        $boxes_max_weight = isset($_POST['boxes_max_weight']) ? $_POST['boxes_max_weight'] : [];
        $boxes_enabled = isset($_POST['boxes_enabled']) ? $_POST['boxes_enabled'] : [];

        $boxes = [];

        if (!empty($boxes_length) && sizeof($boxes_length) > 0) {
            for ($i = 0; $i <= max(array_keys($boxes_length)); $i++) {
                if (!isset($boxes_length[$i])) {
                    continue;
                }

                if ($boxes_length[$i] && $boxes_width[$i] && $boxes_height[$i]) {
                    $boxes[] = [
                        'name' => wc_clean($boxes_name[$i]),
                        'length' => floatval($boxes_length[$i]),
                        'width' => floatval($boxes_width[$i]),
                        'height' => floatval($boxes_height[$i]),
                        'box_weight' => floatval($boxes_box_weight[$i]),
                        'max_weight' => floatval($boxes_max_weight[$i]),
                        'enabled' => isset($boxes_enabled[$i]) ? true : false,
                    ];
                }
            }
        }
        foreach ($this->default_boxes as $box) {
            $boxes[$box['id']] = [
                'enabled' => isset($boxes_enabled[$box['id']]) ? true : false,
            ];
        }
        return $boxes;
    }

    /**
     * validate_services_field function.
     *
     * @param  mixed  $key
     */
    public function validate_services_field($key)
    {
        $services = [];
        $posted_services = $_POST['freight_service'];

        foreach ($posted_services as $code => $settings) {
            $services[$code] = [
                'name' => wc_clean($settings['name']),
                'order' => wc_clean($settings['order']),
                'enabled' => isset($settings['enabled']) ? true : false,
                'adjustment' => wc_clean($settings['adjustment']),
                'adjustment_percent' => str_replace('%', '', wc_clean($settings['adjustment_percent'])),
            ];
        }

        return $services;
    }

    /**
     * Get packages.
     *
     * Divide the WC package into packages/parcels
     *
     * @param  array  $package  Package to ship.
     *
     * @return array Package to ship.
     */
    public function get_freight_packages($package)
    {
        switch ($this->packing_method) {
            case 'box_packing' :
                return $this->box_shipping($package);
                break;
            case 'per_item' :
            default :
                return $this->per_item_shipping($package);
                break;
        }
    }

    /**
     * Get the freight class
     *
     * @param  int  $shipping_class_id
     * @return string
     */
    public function get_freight_class($shipping_class_id)
    {
        $class = version_compare(WC_VERSION, '3.6', 'ge') ? get_term_meta($shipping_class_id, 'freight_class',
            true) : get_woocommerce_term_meta($shipping_class_id, 'freight_class', true);
        return $class ? $class : '';
    }

    /**
     * Pack items individually.
     *
     * @access private
     *
     * @param  mixed  $package  Package to ship.
     *
     * @return array
     */
    private function per_item_shipping($package)
    {
        $to_ship = [];
        $group_id = 1;

        // Get weight of order.
        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'), $item_id),
                    'notice');
                continue;
            }

            if (!$values['data']->get_weight()) {
                $this->debug(sprintf(__('Product # is missing weight. Aborting.', 'woocommerce-shipping-freight'), $item_id),
                    'error');
                return;
            }

            $group = [];

            $group = [
                'GroupNumber' => $group_id,
                'GroupPackageCount' => $values['quantity'],
                'Weight' => [
                    'Value' => max('0.5', round(wc_get_weight($values['data']->get_weight(), 'lbs'), 2)),
                    'Units' => 'LB',
                ],
                'packed_products' => [$values['data']],
            ];

            if ($values['data']->get_length() && $values['data']->get_height() && $values['data']->get_width()) {
                $dimensions = [$values['data']->get_length(), $values['data']->get_width(), $values['data']->get_height()];

                sort($dimensions);

                $group['Dimensions'] = [
                    'Length' => max(1, round(wc_get_dimension($dimensions[2], 'in'), 2)),
                    'Width' => max(1, round(wc_get_dimension($dimensions[1], 'in'), 2)),
                    'Height' => max(1, round(wc_get_dimension($dimensions[0], 'in'), 2)),
                    'Units' => 'IN',
                ];
            }

            $group['InsuredValue'] = [
                'Amount' => round($values['data']->get_price()),
                'Currency' => get_woocommerce_currency(),
            ];

            $to_ship[] = $group;

            $group_id++;
        }

        return $to_ship;
    }

    /**
     * Pack into boxes with weights and dimensions.
     *
     * @access private
     *
     * @param  mixed  $package  Package to ship.
     *
     * @return array
     */
    private function box_shipping($package)
    {
        if (!class_exists('WC_Boxpack')) {
            include_once 'box-packer/class-wc-boxpack.php';
        }

        $boxpack = new WC_Boxpack(['prefer_packets' => true]);

        // Merge default boxes.
        foreach ($this->default_boxes as $key => $box) {
            $box['enabled'] = isset($this->boxes[$box['id']]['enabled']) ? $this->boxes[$box['id']]['enabled'] : true;
            $this->boxes[] = $box;
        }

        // Define boxes.
        foreach ($this->boxes as $key => $box) {
            if (!is_numeric($key)) {
                continue;
            }

            if (!$box['enabled']) {
                continue;
            }

            if ($this->freight_one_rate && !empty($box['one_rate_max_weight'])) {
                $box['max_weight'] = $box['one_rate_max_weight'];
            }

            $newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight']);

            $newbox->set_max_weight($box['max_weight']);

            if (isset($box['id'])) {
                $newbox->set_id(current(explode(':', $box['id'])));
            }

            if (!empty($box['type'])) {
                $newbox->set_type($box['type']);
            }
        }

        // Add items.
        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'), $item_id),
                    'notice');
                continue;
            }

            if ($values['data']->get_length() && $values['data']->get_height() && $values['data']->get_width() && $values['data']->get_weight()) {
                $dimensions = [$values['data']->get_length(), $values['data']->get_height(), $values['data']->get_width()];

                for ($i = 0; $i < $values['quantity']; $i++) {
                    $boxpack->add_item(
                        wc_get_dimension($dimensions[2], 'in'),
                        wc_get_dimension($dimensions[1], 'in'),
                        wc_get_dimension($dimensions[0], 'in'),
                        wc_get_weight($values['data']->get_weight(), 'lbs'),
                        $values['data']->get_price(),
                        [
                            'data' => $values['data'],
                        ]
                    );
                }
            } else {
                $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'woocommerce-shipping-freight'),
                    $item_id), 'error');
                return;
            }
        }

        // Pack it.
        $boxpack->pack();
        $packages = $boxpack->get_packages();
        $to_ship = [];
        $group_id = 1;

        foreach ($packages as $package) {
            $this->debug(
                ($package->unpacked ? 'Unpacked Item ' : 'Packed ').$package->id.' - '.$package->length.'x'.$package->width.'x'.$package->height
            );

            $dimensions = [$package->length, $package->width, $package->height];

            sort($dimensions);

            $group = [
                'GroupNumber' => $group_id,
                'GroupPackageCount' => 1,
                'Weight' => [
                    'Value' => max('0.5', round($package->weight, 2)),
                    'Units' => 'LB',
                ],
                'Dimensions' => [
                    'Length' => max(1, round($dimensions[2], 2)),
                    'Width' => max(1, round($dimensions[1], 2)),
                    'Height' => max(1, round($dimensions[0], 2)),
                    'Units' => 'IN',
                ],
                'InsuredValue' => [
                    'Amount' => round($package->value),
                    'Currency' => get_woocommerce_currency(),
                ],
                'packed_products' => [],
                'package_id' => $package->id,
            ];

            if (!empty($package->packed) && is_array($package->packed)) {
                foreach ($package->packed as $packed) {
                    $group['packed_products'][] = $packed->get_meta('data');
                }
            }

            if ($this->freight_enabled) {
                $highest_freight_class = '';

                if (!empty($package->packed) && is_array($package->packed)) {
                    foreach ($package->packed as $item) {
                        if ($item->get_meta('data')->get_shipping_class_id()) {
                            $freight_class = $this->get_freight_class($item->get_meta('data')->get_shipping_class_id());

                            if ($freight_class > $highest_freight_class) {
                                $highest_freight_class = $freight_class;
                            }
                        }
                    }
                }

                $group['freight_class'] = $highest_freight_class ? $highest_freight_class : '';
            }

            $to_ship[] = $group;

            $group_id++;
        }

        return $to_ship;
    }

    /**
     * See if address is residential
     */
    public function residential_address_validation($package)
    {
        $residential = $this->residential;

        // Address Validation API only available for production
        if ($this->production) {
            // First check if destination is populated. If not return true for residential.
            if (empty($package['destination']['address']) || empty($package['destination']['postcode'])) {
                $this->residential = apply_filters('woocommerce_freight_address_type', true, $package);
                return;
            }

            // Check if address is residential or commerical
            try {
                $client = new SoapClient(plugin_dir_path(dirname(__FILE__)).'api/production/AddressValidationService_v'.$this->addressvalidationservice_version.'.wsdl',
                    ['trace' => 1]);

                $request = [];

                $request['WebAuthenticationDetail'] = [
                    'UserCredential' => [
                        'Key' => $this->api_key,
                        'Password' => $this->api_pass,
                    ],
                ];
                $request['ClientDetail'] = [
                    'AccountNumber' => $this->account_number,
                    'MeterNumber' => $this->meter_number,
                ];
                $request['TransactionDetail'] = ['CustomerTransactionId' => ' *** Address Validation Request v2 from WooCommerce ***'];
                $request['Version'] = [
                    'ServiceId' => 'aval', 'Major' => $this->addressvalidationservice_version, 'Intermediate' => '0',
                    'Minor' => '0',
                ];
                $request['RequestTimestamp'] = date('c');
                $request['Options'] = [
                    'CheckResidentialStatus' => 1,
                    'MaximumNumberOfMatches' => 1,
                    'StreetAccuracy' => 'LOOSE',
                    'DirectionalAccuracy' => 'LOOSE',
                    'CompanyNameAccuracy' => 'LOOSE',
                    'ConvertToUpperCase' => 1,
                    'RecognizeAlternateCityNames' => 1,
                    'ReturnParsedElements' => 1,
                ];
                $request['AddressesToValidate'] = [
                    0 => [
                        'AddressId' => 'WTC',
                        'Address' => [
                            'StreetLines' => [$package['destination']['address'], $package['destination']['address_2']],
                            'PostalCode' => $package['destination']['postcode'],
                        ],
                    ],
                ];

                $response = $client->addressValidation($request);

                if ($response->HighestSeverity == 'SUCCESS') {
                    if (is_array($response->AddressResults)) {
                        $addressResult = $response->AddressResults[0];
                    } else {
                        $addressResult = $response->AddressResults;
                    }

                    if ($addressResult->ProposedAddressDetails->ResidentialStatus == 'BUSINESS') {
                        $residential = false;
                    } else if ($addressResult->ProposedAddressDetails->ResidentialStatus == 'RESIDENTIAL') {
                        $residential = true;
                    }
                }
            } catch (Exception $e) {
            }
        }

        $this->residential = apply_filters('woocommerce_freight_address_type', $residential, $package);

        if ($this->residential == false) {
            $this->debug(__('Business Address', 'woocommerce-shipping-freight'));
        }
    }

    /**
     * get_freight_api_request function.
     *
     * @param  mixed  $package
     *
     * @return array
     * @version 3.4.9
     *
     * @access  private
     */
    private function get_freight_api_request($package)
    {
        $request = [];

        // Prepare Shipping Request for Freight.
        $request['WebAuthenticationDetail'] = [
            'UserCredential' => [
                'Key' => $this->api_key,
                'Password' => $this->api_pass,
            ],
        ];
        $request['ClientDetail'] = [
            'AccountNumber' => $this->account_number,
            'MeterNumber' => $this->meter_number,
        ];
        $request['TransactionDetail'] = [
            'CustomerTransactionId' => ' *** WooCommerce Rate Request ***',
        ];
        $request['Version'] = [
            'ServiceId' => 'crs',
            'Major' => $this->rateservice_version,
            'Intermediate' => '0',
            'Minor' => '0',
        ];
        //$request['ReturnTransitAndCommit'] = false;
        $request['RequestedShipment']['PreferredCurrency'] = get_woocommerce_currency();
        $request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP';
        $request['RequestedShipment']['ShipTimestamp'] = date('c', strtotime('+1 Weekday'));
        $request['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING';
        $request['RequestedShipment']['Shipper'] = [
            'Address' => [
                'PostalCode' => $this->origin,
                'CountryCode' => $this->origin_country,
            ],
        ];
        $request['RequestedShipment']['ShippingChargesPayment'] = [
            'PaymentType' => 'SENDER',
            'Payor' => [
                'ResponsibleParty' => [
                    'AccountNumber' => $this->account_number,
                    'CountryCode' => WC()->countries->get_base_country(),
                ],
            ],
        ];
        $request['RequestedShipment']['RateRequestTypes'] = 'LIST' === $this->request_type ? 'LIST' : 'NONE';

        // Special case for Virgin Islands.
        if ('VI' === $package['destination']['state']) {
            $package['destination']['country'] = 'VI';
        }

        $request['RequestedShipment']['Recipient'] = [
            'Address' => [
                'StreetLines' => [$package['destination']['address'], $package['destination']['address_2']],
                'Residential' => $this->residential,
                'PostalCode' => str_replace(' ', '', strtoupper($package['destination']['postcode'])),
                'City' => strtoupper($package['destination']['city']),
                'StateOrProvinceCode' => strlen($package['destination']['state']) == 2 ? strtoupper($package['destination']['state']) : '',
                'CountryCode' => $package['destination']['country'],
            ],
        ];

        return apply_filters('woocommerce_freight_api_request', $request);
    }

    /**
     * get_freight_requests function.
     *
     * @access private
     *
     * @param  $freight_packages Array of packages to ship
     * @param  $package        array the package passed from WooCommerce
     * @param  $request_type   Used if making a certain type of request i.e. freight, smartpost
     *
     * @return array
     */
    private function get_freight_requests($freight_packages, $package, $request_type = '')
    {
        $requests = [];

        // All reguests for this package get this data
        $package_request = $this->get_freight_api_request($package);

        if ($freight_packages) {
            // Freight Supports a Max of 99 per request
            $parcel_chunks = array_chunk($freight_packages, 99);

            foreach ($parcel_chunks as $parcels) {
                $request = $package_request;
                $total_value = 0;
                $total_packages = 0;
                $total_weight = 0;
                $commodities = [];
                $freight_class = '';

                // Store parcels as line items
                $request['RequestedShipment']['RequestedPackageLineItems'] = [];

                foreach ($parcels as $key => $parcel) {
                    $parcel_request = $parcel;
                    $total_value += $parcel['InsuredValue']['Amount'] * $parcel['GroupPackageCount'];
                    $total_packages += $parcel['GroupPackageCount'];
                    $parcel_packages = $parcel['GroupPackageCount'];
                    $total_weight += $parcel['Weight']['Value'] * $parcel_packages;

                    if ('freight' === $request_type) {
                        // Get the highest freight class for shipment
                        if (isset($parcel['freight_class']) && $parcel['freight_class'] > $freight_class) {
                            $freight_class = $parcel['freight_class'];
                        }
                    } else {
                        // Work out the commodities for CA shipments
                        if ($parcel_request['packed_products']) {
                            foreach ($parcel_request['packed_products'] as $product) {
                                if (isset($commodities[$product->get_id()])) {
                                    $commodities[$product->get_id()]['Quantity']++;
                                    $commodities[$product->get_id()]['CustomsValue']['Amount'] += round($product->get_price());
                                    continue;
                                }
                                $commodities[$product->get_id()] = [
                                    'Name' => sanitize_title($product->get_title()),
                                    'NumberOfPieces' => 1,
                                    'Description' => '',
                                    'CountryOfManufacture' => ($country = get_post_meta($product->get_id(),
                                        'CountryOfManufacture', true)) ? $country : WC()->countries->get_base_country(),
                                    'Weight' => [
                                        'Units' => 'LB',
                                        'Value' => max('0.5', round(wc_get_weight($product->get_weight(), 'lbs'), 2)),
                                    ],
                                    'Quantity' => $parcel['GroupPackageCount'],
                                    'UnitPrice' => [
                                        'Amount' => round($product->get_price()),
                                        'Currency' => get_woocommerce_currency(),
                                    ],
                                    'CustomsValue' => [
                                        'Amount' => $parcel['InsuredValue']['Amount'] * $parcel['GroupPackageCount'],
                                        'Currency' => get_woocommerce_currency(),
                                    ],
                                ];
                            }
                        }

                        if (!empty($parcel_request['package_id'])) {
                            // Is this valid for a ONE rate? Smart post does not support it
                            if ($this->freight_one_rate && '' === $request_type && in_array($parcel_request['package_id'],
                                    $this->freight_one_rate_package_ids) && 'US' === $package['destination']['country'] && 'US' === $this->origin_country) {
                                $request['RequestedShipment']['PackagingType'] = $parcel_request['package_id'];
                                $request['RequestedShipment']['SpecialServicesRequested']['SpecialServiceTypes'] = 'FREIGHT_ONE_RATE';
                            } else if (in_array($parcel_request['package_id'], wp_list_pluck($this->default_boxes, 'id'))) {
                                $request['RequestedShipment']['PackagingType'] = $parcel_request['package_id'];
                            }
                        }
                    }

                    // Remove temp elements
                    unset($parcel_request['freight_class']);
                    unset($parcel_request['packed_products']);
                    unset($parcel_request['package_id']);

                    if (!$this->insure_contents || 'smartpost' === $request_type || in_array($request['RequestedShipment']['PackagingType'],
                            ['FREIGHT_ENVELOPE', 'FREIGHT_PAK'])) {
                        unset($parcel_request['InsuredValue']);
                    }

                    $parcel_request = array_merge(['SequenceNumber' => $key + 1], $parcel_request);
                    $request['RequestedShipment']['RequestedPackageLineItems'][] = $parcel_request;
                }

                // Size
                $request['RequestedShipment']['PackageCount'] = $total_packages;

                // Smart post
                if ('smartpost' === $request_type) {
                    $request['RequestedShipment']['SmartPostDetail'] = [
                        'Indicia' => 'PARCEL_SELECT',
                        'HubId' => $this->smartpost_hub,
                        'AncillaryEndorsement' => 'ADDRESS_CORRECTION',
                        'SpecialServices' => '',
                    ];
                    $request['RequestedShipment']['ServiceType'] = 'SMART_POST';

                    // Smart post does not support insurance, but is insured up to $100
                    if ($this->insure_contents && round($total_value) > 100) {
                        return false;
                    }
                } else if ($this->insure_contents && !in_array($request['RequestedShipment']['PackagingType'],
                        ['FREIGHT_ENVELOPE', 'FREIGHT_PAK'])) {
                    $request['RequestedShipment']['TotalInsuredValue'] = [
                        'Amount' => round($total_value),
                        'Currency' => get_woocommerce_currency(),
                    ];
                }

                if ('freight' === $request_type) {
                    $request['RequestedShipment']['Shipper'] = [
                        'Address' => [
                            'StreetLines' => [
                                strtoupper($this->freight_shipper_street), strtoupper($this->freight_shipper_street_2),
                            ],
                            'City' => strtoupper($this->freight_shipper_city),
                            'StateOrProvinceCode' => strtoupper($this->freight_shipper_state),
                            'PostalCode' => strtoupper($this->freight_shipper_postcode),
                            'CountryCode' => strtoupper($this->freight_shipper_country),
                            'Residential' => $this->freight_shipper_residential,
                        ],
                    ];
                    $request['CarrierCodes'] = 'FXFR';
                    $request['RequestedShipment']['FreightShipmentDetail'] = [
                        'FreightAccountNumber' => strtoupper($this->freight_number),
                        'FreightBillingContactAndAddress' => [
                            'Address' => [
                                'StreetLines' => [
                                    strtoupper($this->freight_billing_street), strtoupper($this->freight_billing_street_2),
                                ],
                                'City' => strtoupper($this->freight_billing_city),
                                'StateOrProvinceCode' => strtoupper($this->freight_billing_state),
                                'PostalCode' => strtoupper($this->freight_billing_postcode),
                                'CountryCode' => strtoupper($this->freight_billing_country),
                            ],
                        ],
                        'Role' => 'SHIPPER',
                        'PaymentType' => 'PREPAID',
                    ];

                    // Format freight class
                    $freight_class = $freight_class ? $freight_class : $this->freight_class;
                    $freight_class = $freight_class < 100 ? '0'.$freight_class : $freight_class;
                    $freight_class = 'CLASS_'.str_replace('.', '_', $freight_class);

                    $request['RequestedShipment']['FreightShipmentDetail']['LineItems'] = [
                        'FreightClass' => $freight_class,
                        'Packaging' => 'SKID',
                        'Weight' => [
                            'Units' => 'LB',
                            'Value' => round($total_weight, 2),
                        ],
                    ];
                    $request['RequestedShipment']['ShippingChargesPayment'] = [
                        'PaymentType' => 'SENDER',
                        'Payor' => [
                            'ResponsibleParty' => [
                                'AccountNumber' => strtoupper($this->freight_number),
                                'CountryCode' => WC()->countries->get_base_country(),
                            ],
                        ],
                    ];
                } else {
                    // Canada broker fees
                    if (($package['destination']['country'] == 'CA' || $package['destination']['country'] == 'US') && WC()->countries->get_base_country() !== $package['destination']['country']) {
                        $request['RequestedShipment']['CustomsClearanceDetail']['DutiesPayment'] = [
                            'PaymentType' => 'SENDER',
                            'Payor' => [
                                'ResponsibleParty' => [
                                    'AccountNumber' => strtoupper($this->account_number),
                                    'CountryCode' => WC()->countries->get_base_country(),
                                ],
                            ],
                        ];
                        $request['RequestedShipment']['CustomsClearanceDetail']['Commodities'] = array_values($commodities);
                    }
                }

                // Add request
                $requests[] = $request;
            }
        }

        return $requests;
    }

    /**
     * Get ground rates if the first request was using Freight packaging and which does not return ground rates.
     *
     * @param  array  $requests
     *
     * @return array
     */
    protected function get_ground_requests($requests)
    {
        $ground_requests = [];
        foreach ($requests as $request) {
            if (isset($request['RequestedShipment']['PackagingType']) &&
                'YOUR_PACKAGING' !== $request['RequestedShipment']['PackagingType'] &&
                !isset($request['RequestedShipment']['SpecialServicesRequested']['SpecialServiceTypes'])) {
                $request['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING';
                $ground_requests[] = $request;
            }
        }

        return $ground_requests;
    }

    /**
     * Calculate shipping cost.
     *
     * @param  mixed  $package  Package to ship.
     *
     * @version 3.4.9
     *
     * @since   1.0.0
     */
    public function calculate_shipping($package = [])
    {
        // Clear rates.
        $this->found_rates = [];
        $this->package = $package;

        // Debugging.
        $this->debug(__('FREIGHT SHIPPING debug mode is on - to hide these messages, turn debug mode off in the settings.',
            'woocommerce-shipping-freight'));

        // See if address is residential.
        $this->residential_address_validation($package);

        // Get requests.
        $freight_packages = $this->get_freight_packages($package);
        $freight_requests = $this->get_freight_requests($freight_packages, $package);

        if ($freight_requests) {
            $this->run_package_request($freight_requests);

            // Second request to get ground prices if necessary.
            if (!empty($this->custom_services['FREIGHT_GROUND']['enabled']) && !$this->is_shipping_internationally()) {
                $freight_ground_requests = $this->get_ground_requests($freight_requests);
                if ($freight_ground_requests) {
                    $this->run_package_request($freight_ground_requests);
                }
            }
        }

        if (!empty($this->custom_services['SMART_POST']['enabled']) && !empty($this->smartpost_hub) && $package['destination']['country'] == 'US' && ($smartpost_requests = $this->get_freight_requests($freight_packages,
                $package, 'smartpost'))) {
            $this->run_package_request($smartpost_requests);
        }

        if ($this->freight_enabled && ($freight_requests = $this->get_freight_requests($freight_packages, $package,
                'freight'))) {
            $this->run_package_request($freight_requests);
        }

        // Ensure rates were found for all packages.
        $packages_to_quote_count = sizeof($freight_requests);

        if ($this->found_rates) {
            foreach ($this->found_rates as $key => $value) {
                if ($value['packages'] < $packages_to_quote_count) {
                    unset($this->found_rates[$key]);
                } else {
                    $meta_data = [];
                    if (isset($value['meta_data'])) {
                        $meta_data = $value['meta_data'];
                    }

                    foreach ($freight_packages as $freight_package) {
                        $meta_data['Package '.$freight_package['GroupNumber']] = $this->get_rate_meta_data([
                            'length' => $freight_package['Dimensions']['Length'],
                            'width' => $freight_package['Dimensions']['Width'],
                            'height' => $freight_package['Dimensions']['Height'],
                            'weight' => $freight_package['Weight']['Value'],
                            'qty' => $freight_package['GroupPackageCount'],
                        ]);
                    }

                    $this->found_rates[$key]['meta_data'] = $meta_data;
                }
            }
        }

        $this->add_found_rates();
    }

    /**
     * Run requests and get/parse results
     *
     * @param  array  $requests
     */
    public function run_package_request($requests)
    {
        try {
            foreach ($requests as $key => $request) {
                $this->process_result($this->get_result($request));
            }
        } catch (Exception $e) {
            $this->debug(print_r($e, true), 'error');
            return false;
        }
    }

    /**
     * get_result function.
     *
     * @access private
     *
     * @param  mixed  $request
     *
     * @return array
     */
    private function get_result($request)
    {
        $this->debug('FREIGHT SHIPPING REQUEST: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info">'.print_r($request,
                true).'</pre>');
        $rate_soap_file_location = plugin_dir_path(dirname(__FILE__)).'api/'.($this->production ? 'production' : 'test').'/RateService_v'.$this->rateservice_version.'.wsdl';

        try {
            $client = new SoapClient($rate_soap_file_location, ['trace' => 1]);
            $result = $client->getRates($request);
        } catch (Exception $e) {
            $stream_context_args = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
            $soap_args = [
                'trace' => 1,
                'stream_context' => stream_context_create($stream_context_args),
            ];

            $client = new SoapClient($rate_soap_file_location, $soap_args);
            $result = $client->getRates($request);
        }

        $this->debug('FREIGHT SHIPPING RESPONSE: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info">'.print_r($result,
                true).'</pre>');

        wc_enqueue_js("
			jQuery('a.debug_reveal').on('click', function(){
				jQuery(this).closest('div').find('.debug_info').slideDown();
				jQuery(this).remove();
				return false;
			});
			jQuery('pre.debug_info').hide();
		");

        return $result;
    }

    /**
     * process_result function.
     *
     * @access private
     *
     * @param  mixed  $result
     *
     * @return void
     */
    private function process_result($result = '')
    {
        if ($result && !empty ($result->RateReplyDetails)) {
            $rate_reply_details = $result->RateReplyDetails;

            // Workaround for when an object is returned instead of array
            if (is_object($rate_reply_details) && isset($rate_reply_details->ServiceType)) {
                $rate_reply_details = [$rate_reply_details];
            }

            if (!is_array($rate_reply_details)) {
                return false;
            }

            foreach ($rate_reply_details as $quote) {
                if (is_array($quote->RatedShipmentDetails)) {
                    if ($this->request_type == "LIST") {
                        // LIST quotes return both ACCOUNT rates (in RatedShipmentDetails[1])
                        // and LIST rates (in RatedShipmentDetails[3])
                        foreach ($quote->RatedShipmentDetails as $i => $d) {
                            if (strstr($d->ShipmentRateDetail->RateType, 'PAYOR_LIST')) {
                                $details = $quote->RatedShipmentDetails[$i];
                                break;
                            }
                        }
                    } else {
                        // ACCOUNT quotes may return either ACCOUNT rates only OR
                        // ACCOUNT rates and LIST rates.
                        foreach ($quote->RatedShipmentDetails as $i => $d) {
                            if (strstr($d->ShipmentRateDetail->RateType, 'PAYOR_ACCOUNT')) {
                                $details = $quote->RatedShipmentDetails[$i];
                                break;
                            }
                        }
                    }
                } else {
                    $details = $quote->RatedShipmentDetails;
                }

                if (empty($details)) {
                    continue;
                }

                $rate_code = strval($quote->ServiceType);
                $rate_id = $this->get_rate_id($rate_code);
                $rate_name = strval($this->services[$quote->ServiceType]);
                $rate_cost = floatval($details->ShipmentRateDetail->TotalNetCharge->Amount);

                $this->prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost);
            }
        }
    }

    /**
     * Prepare rate.
     *
     * @access private
     *
     * @param  mixed  $rate_code  Rate code.
     * @param  mixed  $rate_id    Rate ID.
     * @param  mixed  $rate_name  Rate name.
     * @param  mixed  $rate_cost  Cost.
     */
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost)
    {
        // Name adjustment.
        if (!empty($this->custom_services[$rate_code]['name'])) {
            $rate_name = $this->custom_services[$rate_code]['name'];
        }

        // Cost adjustment %.
        if (!empty($this->custom_services[$rate_code]['adjustment_percent'])) {
            $rate_cost = $rate_cost + ($rate_cost * (floatval($this->custom_services[$rate_code]['adjustment_percent']) / 100));
        }
        // Cost adjustment.
        if (!empty($this->custom_services[$rate_code]['adjustment'])) {
            $rate_cost = $rate_cost + floatval($this->custom_services[$rate_code]['adjustment']);
        }

        // Enabled check.
        if (isset($this->custom_services[$rate_code]) && empty($this->custom_services[$rate_code]['enabled'])) {
            return;
        }

        // Merging.
        if (isset($this->found_rates[$rate_id])) {
            $rate_cost = $rate_cost + $this->found_rates[$rate_id]['cost'];
            $packages = 1 + $this->found_rates[$rate_id]['packages'];
        } else {
            $packages = 1;
        }

        // Sort.
        if (isset($this->custom_services[$rate_code]['order'])) {
            $sort = $this->custom_services[$rate_code]['order'];
        } else {
            $sort = 999;
        }

        $this->found_rates[$rate_id] = [
            'id' => $rate_id,
            'label' => $rate_name,
            'cost' => $rate_cost,
            'sort' => $sort,
            'packages' => $packages,
        ];
    }

    /**
     * Get meta data string for the shipping rate.
     *
     * @param  array  $params  Meta data info to join.
     *
     * @return string Rate meta data.
     * @since   3.4.9
     * @version 3.4.9
     *
     */
    private function get_rate_meta_data($params)
    {
        $meta_data = [];

        if (!empty($params['name'])) {
            $meta_data[] = $params['name'].' -';
        }

        if ($params['length'] && $params['width'] && $params['height']) {
            $meta_data[] = sprintf('%1$s × %2$s × %3$s (in)', $params['length'], $params['width'], $params['height']);
        }
        if ($params['weight']) {
            $meta_data[] = round($params['weight'], 2).'lbs';
        }
        if ($params['qty']) {
            $meta_data[] = '× '.$params['qty'];
        }

        return implode(' ', $meta_data);
    }

    /**
     * Add found rates to WooCommerce
     */
    public function add_found_rates()
    {
        if ($this->found_rates) {
            // remove ground rates if shipping internationally
            if ($this->is_shipping_internationally() && !$this->need_direct_distribution()) {
                unset($this->found_rates['freight:FREIGHT_GROUND']);
            }

            if ($this->offer_rates == 'all') {
                uasort($this->found_rates, [$this, 'sort_rates']);

                foreach ($this->found_rates as $key => $rate) {
                    $this->add_rate($rate);
                }
            } else {
                $cheapest_rate = '';

                foreach ($this->found_rates as $key => $rate) {
                    if (!$cheapest_rate || $cheapest_rate['cost'] > $rate['cost']) {
                        $cheapest_rate = $rate;
                    }
                }

                $cheapest_rate['label'] = $this->title;

                $this->add_rate($cheapest_rate);
            }
        }
    }

    /**
     * Determine if the current shipping is to be done internationally
     *
     * @return bool
     */
    public function is_shipping_internationally()
    {
        // compare base and package country: not equal for international shipping
        return (WC()->countries->get_base_country() !== $this->package['destination']['country'] && 'CA' !== $this->package['destination']['country'] && 'US' !== $this->package['destination']['country']);
    }

    /**
     *
     * @return bool
     */
    public function need_direct_distribution()
    {
        if ($this->direct_distribution) {
            if ('US' === WC()->countries->get_base_country() && 'CA' === $this->package['destination']['country']) {
                return true;
            }

            if ('CA' === WC()->countries->get_base_country() && 'US' === $this->package['destination']['country']) {
                return true;
            }
        }

        return false;
    }

    /**
     * sort_rates function.
     *
     * @param  mixed  $a
     * @param  mixed  $b
     *
     * @return int
     */
    public function sort_rates($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }
}