<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Shipping_Freight class deprecated.
 *
 * This class serves only WC < 2.6 and will be removed by WC 2.8
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
    public function __construct()
    {
        $this->id = 'freight';
        $this->method_title = __('Freight', 'woocommerce-shipping-freight');
        $this->method_description = __('The <strong>Freight Shipping</strong> extension obtains rates dynamically from the Bedrock API during cart/checkout.',
            'woocommerce-shipping-freight');
        $this->rateservice_version = 16;
        $this->addressvalidationservice_version = 2;
        $this->default_boxes = include('data/data-box-sizes.php');
        $this->services = include('data/data-service-codes.php');
        $this->init();
    }

    /**
     * init function.
     */
    private function init()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', $this->method_title);
        $this->availability = $this->get_option('availability', 'all');
        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->countries = $this->get_option('countries', []);
        $this->origin = apply_filters('woocommerce_freight_origin_postal_code',
            str_replace(' ', '', strtoupper($this->get_option('origin'))));
        $this->origin_country = apply_filters('woocommerce_freight_origin_country_code',
            WC()->countries->get_base_country());
        $this->account_number = $this->get_option('account_number');
        $this->meter_number = $this->get_option('meter_number');
        $this->smartpost_hub = $this->get_option('smartpost_hub');
        $this->api_key = $this->get_option('api_key');
        $this->api_pass = $this->get_option('api_pass');
        $this->production = ($bool = $this->get_option('production')) && $bool == 'yes' ? true : false;
        $this->debug = ($bool = $this->get_option('debug')) && $bool == 'yes' ? true : false;
        $this->insure_contents = ($bool = $this->get_option('insure_contents')) && $bool == 'yes' ? true : false;
        $this->request_type = $this->get_option('request_type', 'LIST');
        $this->packing_method = $this->get_option('packing_method', 'per_item');
        $this->boxes = $this->get_option('boxes', []);
        $this->custom_services = $this->get_option('services', []);
        $this->offer_rates = $this->get_option('offer_rates', 'all');
        $this->residential = ($bool = $this->get_option('residential')) && $bool == 'yes' ? true : false;
        $this->freight_enabled = ($bool = $this->get_option('freight_enabled')) && $bool == 'yes' ? true : false;
        $this->freight_one_rate = ($bool = $this->get_option('freight_one_rate')) && $bool == 'yes' ? true : false;
        $this->direct_distribution = ($bool = $this->get_option('direct_distribution')) && $bool == 'yes' ? true : false;
        $this->freight_one_rate_package_ids = [
            'FREIGHT_SMALL_BOX',
            'FREIGHT_MEDIUM_BOX',
            'FREIGHT_LARGE_BOX',
            'FREIGHT_EXTRA_LARGE_BOX',
            'FREIGHT_PAK',
            'FREIGHT_ENVELOPE',
        ];

        if ($this->freight_enabled) {
            $this->freight_class = $this->get_option('freight_class');
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
            $this->freight_shipper_residential = ($bool = $this->get_option('freight_shipper_residential')) && $bool == 'yes' ? true : false;
            $this->freight_class = str_replace(['CLASS_', '.'], ['', '_'], $this->freight_class);

            // Make the city field show in the calculator
            add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');

            // Add freight class option for shipping classes
            if (is_admin() && !class_exists('WC_Freight_Mapping')) {
                include('class-wc-freight-mapping.php');
            }
        }

        // Insure contents requires matching currency to country
        switch (WC()->countries->get_base_country()) {
            case 'US' :
                if ('USD' !== get_woocommerce_currency()) {
                    $this->insure_contents = false;
                }
                break;
        }

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);
    }

    /**
     * Load admin scripts
     *
     * @return void
     * @since   2.0.0
     */
    public function load_admin_scripts()
    {
        wp_enqueue_script('jquery-ui-sortable');
    }

    /**
     *
     * @param  array  $package
     *
     * @return bool
     * @since   2.0.0
     */
    public function is_available($package)
    {
        if ('no' === $this->enabled || empty($package['destination']['country'])) {
            return false;
        }

        if ('specific' === $this->availability) {
            if (is_array($this->countries) && !in_array($package['destination']['country'], $this->countries)) {
                return false;
            }
        } else if ('excluding' === $this->availability) {
            if (is_array($this->countries) && (in_array($package['destination']['country'],
                        $this->countries) || !$package['destination']['country'])) {
                return false;
            }
        }

        return apply_filters('woocommerce_shipping_'.$this->id.'_is_available', true, $package);
    }

    /**
     * Output a message
     *
     * @since   2.0.0
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug) {
            wc_add_notice($message, $type);
        }
    }

    /**
     *
     * @since   2.0.0
     */
    private function environment_check()
    {
        if (get_woocommerce_currency() !== 'USD' || WC()->countries->get_base_country() !== 'US') {
            echo '
                <div class="error">
                    <p>'.__('Freight Shipping requires that the WooCommerce currency is set to US Dollars and that the base country/region is set to United States.', 'woocommerce-shipping-freight').'</p>
                </div>
            ';
        } else if (!$this->origin && $this->enabled === 'yes') {
            echo '
                <div class="error">
                    <p>'.__('Freight Shipping is enabled, but the origin postcode has not been set.', 'woocommerce-shipping-freight').'</p>
                </div>
            ';
        }
    }

    /**
     *
     * @since   2.0.0
     */
    public function admin_options()
    {
        $this->environment_check();
        parent::admin_options();
    }

    /**
     *
     * @since   2.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = include('data/data-settings-deprecated.php');
    }

    /**
     *
     * @since   2.0.0
     */
    public function generate_services_html()
    {
        ob_start();
        include('views/html-services.php');
        return ob_get_clean();
    }

    /**
     *
     * @since   2.0.0
     */
    public function generate_box_packing_html()
    {
        ob_start();
        include('views/html-box-packing.php');
        return ob_get_clean();
    }

    /**
     *
     *
     * @param  mixed  $key
     */
    public function validate_box_packing_field( $key ) {
        $boxes_name       = isset( $_POST['boxes_name'] ) ? $_POST['boxes_name'] : array();
        $boxes_length     = isset( $_POST['boxes_length'] ) ? $_POST['boxes_length'] : array();
        $boxes_width      = isset( $_POST['boxes_width'] ) ? $_POST['boxes_width'] : array();
        $boxes_height     = isset( $_POST['boxes_height'] ) ? $_POST['boxes_height'] : array();
        $boxes_box_weight = isset( $_POST['boxes_box_weight'] ) ? $_POST['boxes_box_weight'] : array();
        $boxes_max_weight = isset( $_POST['boxes_max_weight'] ) ? $_POST['boxes_max_weight'] :  array();
        $boxes_enabled    = isset( $_POST['boxes_enabled'] ) ? $_POST['boxes_enabled'] : array();

        $boxes = array();

        if ( ! empty( $boxes_length ) && sizeof( $boxes_length ) > 0 ) {
            for ( $i = 0; $i <= max( array_keys( $boxes_length ) ); $i ++ ) {

                if ( ! isset( $boxes_length[ $i ] ) )
                    continue;

                if ( $boxes_length[ $i ] && $boxes_width[ $i ] && $boxes_height[ $i ] ) {

                    $boxes[] = array(
                        'name'       => wc_clean( $boxes_name[ $i ] ),
                        'length'     => floatval( $boxes_length[ $i ] ),
                        'width'      => floatval( $boxes_width[ $i ] ),
                        'height'     => floatval( $boxes_height[ $i ] ),
                        'box_weight' => floatval( $boxes_box_weight[ $i ] ),
                        'max_weight' => floatval( $boxes_max_weight[ $i ] ),
                        'enabled'    => isset( $boxes_enabled[ $i ] ) ? true : false
                    );
                }
            }
        }
        foreach ( $this->default_boxes as $box ) {
            $boxes[ $box['id'] ] = array(
                'enabled' => isset( $boxes_enabled[ $box['id'] ] ) ? true : false
            );
        }
        return $boxes;
    }

    /**
     * validate_services_field function.
     *
     * @param  mixed  $key
     * @since   2.0.0
     */
    public function validate_services_field($key)
    {
        $services = [];
        $posted_services = $_POST['freight_service'];

        foreach ($posted_services as $code => $settings) {
            $services[$code] = [
                'name' => woocommerce_clean($settings['name']),
                'order' => woocommerce_clean($settings['order']),
                'enabled' => isset($settings['enabled']) ? true : false,
                'adjustment' => woocommerce_clean($settings['adjustment']),
                'adjustment_percent' => str_replace('%', '', woocommerce_clean($settings['adjustment_percent'])),
            ];
        }
        return $services;
    }

    /**
     * Get packages - divide the WC package into packages/parcels
     *
     * @since   2.0.0
     */
    public function get_freight_packages($package)
    {
        switch ($this->packing_method) {
            /*case 'box_packing':
                return $this->box_shipping( $package );
            break;*/
            case 'per_item':
            default :
                return $this->per_item_shipping($package);
                break;
        }
    }

    /**
     * Get the freight class
     *
     * @param   int  $shipping_class_id
     * @return  string
     * @since   2.0.0
     */
    public function get_freight_class($shipping_class_id)
    {
        $class = version_compare(WC_VERSION, '3.6', 'ge') ? get_term_meta($shipping_class_id, 'freight_class',
            true) : get_woocommerce_term_meta($shipping_class_id, 'freight_class', true);
        return $class ? $class : '';
    }

    /**
     * per_item_shipping function.
     *
     * @param   mixed  $package
     * @return  array
     * @since   2.0.0
     */
    private function per_item_shipping($package)
    {
        $to_ship = [];
        $group_id = 1;

        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'), $item_id), 'notice');
                continue;
            }

            if (!$values['data']->get_weight()) {
                $this->debug(sprintf(__('Product # is missing weight. Aborting.', 'woocommerce-shipping-freight'), $item_id), 'error');
                return;
            }

            $group = [];

            $group = [
                'GroupNumber' => $group_id,
                'GroupPackageCount' => $values['quantity'],
                'Weight' => [
                    'Value' => max('0.5', round(woocommerce_get_weight($values['data']->get_weight(), 'lbs'), 2)),
                    'Units' => 'LB',
                ],
                'packed_products' => [$values['data']],
            ];

            if ($values['data']->length && $values['data']->height && $values['data']->width) {
                $dimensions = [$values['data']->length, $values['data']->width, $values['data']->height];

                sort($dimensions);

                $group['Dimensions'] = [
                    'Length' => max(1, round(woocommerce_get_dimension($dimensions[2], 'in'), 2)),
                    'Width' => max(1, round(woocommerce_get_dimension($dimensions[1], 'in'), 2)),
                    'Height' => max(1, round(woocommerce_get_dimension($dimensions[0], 'in'), 2)),
                    'Units' => 'IN',
                ];
            }

            /*$group['InsuredValue'] = array(
                'Amount'   => round( $values['data']->get_price() ),
                'Currency' => get_woocommerce_currency()
            );*/

            $to_ship[] = $group;
            $group_id++;
        }
        return $to_ship;
    }

    /**
     *
     *
     * @param   mixed  $package
     * @return  array
     * @since   2.0.0
     */
    private function box_shipping($package)
    {
        if (!class_exists('WC_Boxpack')) {
            include_once 'box-packer/class-wc-boxpack.php';
        }

        $boxpack = new WC_Boxpack();

        // Merge default boxes
        foreach ($this->default_boxes as $key => $box) {
            $box['enabled'] = isset($this->boxes[$box['id']]['enabled']) ? $this->boxes[$box['id']]['enabled'] : true;
            $this->boxes[] = $box;
        }

        // Define boxes
        foreach ($this->boxes as $key => $box) {
            if (!is_numeric($key)) {
                continue;
            }

            if (!$box['enabled']) {
                continue;
            }

            $newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight']);

            if (isset($box['id'])) {
                $newbox->set_id(current(explode(':', $box['id'])));
            }

            if ($box['max_weight']) {
                $newbox->set_max_weight($box['max_weight']);
            }
        }

        // Add items
        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'), $item_id),
                    'notice');
                continue;
            }

            if ($values['data']->length && $values['data']->height && $values['data']->width && $values['data']->weight) {
                $dimensions = [$values['data']->length, $values['data']->height, $values['data']->width];

                for ($i = 0; $i < $values['quantity']; $i++) {
                    $boxpack->add_item(
                        woocommerce_get_dimension($dimensions[2], 'in'),
                        woocommerce_get_dimension($dimensions[1], 'in'),
                        woocommerce_get_dimension($dimensions[0], 'in'),
                        woocommerce_get_weight($values['data']->get_weight(), 'lbs'),
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

        $boxpack->pack();
        $packages = $boxpack->get_packages();
        $to_ship = [];
        $group_id = 1;

        foreach ($packages as $package) {
            if ($package->unpacked === true) {
                $this->debug('Unpacked Item');
            } else {
                $this->debug('Packed '.$package->id.' - '.$package->length.'x'.$package->width.'x'.$package->height);
            }

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

                if ($response->HighestSeverity === 'SUCCESS') {
                    if (is_array($response->AddressResults)) {
                        $addressResult = $response->AddressResults[0];
                    } else {
                        $addressResult = $response->AddressResults;
                    }

                    if ($addressResult->ProposedAddressDetails->ResidentialStatus === 'BUSINESS') {
                        $residential = false;
                    } else if ($addressResult->ProposedAddressDetails->ResidentialStatus === 'RESIDENTIAL') {
                        $residential = true;
                    }
                }

            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        $this->residential = apply_filters('woocommerce_freight_address_type', $residential, $package);

        if ($this->residential == false) {
            $this->debug(__('Business Address', 'woocommerce-shipping-freight'));
        }
    }

    /**
     *
     * @param  mixed  $package
     * @return array
     */
    private function get_freight_api_request($package)
    {
        $request = [];

        // todo: for xml api

        // Prepare Shipping Request for API
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
        $request['RequestedShipment']['RateRequestTypes'] = $this->request_type === 'LIST' ? 'LIST' : 'NONE';

        // Special case for Virgin Islands.
        if ('VI' === $package['destination']['state']) {
            $package['destination']['country'] = 'VI';
        }

        $request['RequestedShipment']['Recipient'] = [
            'Address' => [
                'Residential' => $this->residential,
                'PostalCode' => str_replace(' ', '', strtoupper($package['destination']['postcode'])),
                'City' => strtoupper($package['destination']['city']),
                'StateOrProvinceCode' => strlen($package['destination']['state']) == 2 ? strtoupper($package['destination']['state']) : '',
                'CountryCode' => $package['destination']['country'],
            ],
        ];

        return $request;
    }

    /**
     * get_freight_requests function.
     *
     * @param  $freight_packages array  packages to ship
     * @param  $package          array  package passed from WooCommerce
     * @param  $request_type (i.e. freight)
     * @return array
     */
    private function get_freight_requests($freight_packages, $package, $request_type = '')
    {
        $requests = [];

        // All requests for this package get this data
        $package_request = $this->get_freight_api_request($package);

        if ($freight_packages) {
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
                    }
                    else {
                        // Work out the commodities for CA shipments
                        if ($parcel_request['packed_products']) {
                            foreach ($parcel_request['packed_products'] as $product) {
                                if (isset($commodities[$product->id])) {
                                    $commodities[$product->id]['Quantity']++;
                                    $commodities[$product->id]['CustomsValue']['Amount'] += round($product->get_price());
                                    continue;
                                }
                                $commodities[$product->id] = [
                                    'Name' => sanitize_title($product->get_title()),
                                    'NumberOfPieces' => 1,
                                    'Description' => '',
                                    'CountryOfManufacture' => ($country = get_post_meta($product->id, 'CountryOfManufacture',
                                        true)) ? $country : WC()->countries->get_base_country(),
                                    'Weight' => [
                                        'Units' => 'LB',
                                        'Value' => max('0.5',
                                            round(woocommerce_get_weight($product->get_weight(), 'lbs'), 2)),
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

                        // Is this valid for a ONE rate? Smart post does not support it
                        if ($this->freight_one_rate && '' === $request_type && in_array($parcel_request['package_id'],
                                $this->freight_one_rate_package_ids) && 'US' === $package['destination']['country'] && 'US' === $this->origin_country) {
                            $request['RequestedShipment']['PackagingType'] = $parcel_request['package_id'];
                            $request['RequestedShipment']['SpecialServicesRequested']['SpecialServiceTypes'] = 'FREIGHT_ONE_RATE';
                        }
                    }

                    // Remove temp elements
                    unset($parcel_request['freight_class']);
                    unset($parcel_request['packed_products']);
                    unset($parcel_request['package_id']);

                    if (!$this->insure_contents || 'smartpost' === $request_type) {
                        unset($parcel_request['InsuredValue']);
                    }

                    $parcel_request = array_merge(['SequenceNumber' => $key + 1], $parcel_request);
                    $request['RequestedShipment']['RequestedPackageLineItems'][] = $parcel_request;
                }

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
                } else if ($this->insure_contents) {
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
                    //todo: update carrier code
                    //todo: check below fields
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
     *
     * @param  mixed  $package
     */
    public function calculate_shipping($package = [])
    {
        $this->found_rates = [];
        $this->package = $package;

        // Debugging
        $this->debug(__('Freight Shipping debug mode is on - to hide these messages, turn debug mode off in the settings.',
            'woocommerce-shipping-freight'));

        // See if address is residential
        $this->residential_address_validation($package);

        // Get requests
        $freight_packages = $this->get_freight_packages($package);
        $freight_requests = $this->get_freight_requests($freight_packages, $package);

        if ($freight_requests) {
            $this->run_package_request($freight_requests);
        }

        if (!empty($this->custom_services['SMART_POST']['enabled'])
            && !empty($this->smartpost_hub) && $package['destination']['country'] === 'US'
            && ($smartpost_requests = $this->get_freight_requests($freight_packages, $package, 'smartpost'))) {
                $this->run_package_request($smartpost_requests);
        }

        if ($this->freight_enabled && ($freight_requests = $this->get_freight_requests($freight_packages, $package, 'freight'))) {
            $this->run_package_request($freight_requests);
        }

        // Ensure rates were found for all packages
        $packages_to_quote_count = count($freight_requests);

        if ($this->found_rates) {
            foreach ($this->found_rates as $key => $value) {
                if ($value['packages'] < $packages_to_quote_count) {
                    unset($this->found_rates[$key]);
                }
            }
        }
        $this->add_found_rates();
    }

    /**
     * Run requests and parse results
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
     *
     * @param  mixed  $request
     * @return array
     */
    private function get_result($request)
    {
        $this->debug('Freight Shipping API REQUEST: <a href="#" class="debug_reveal">Reveal</a>
            <pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">
                '.print_r($request, true).'
            </pre>');

        // todo: XML API client
        $client = new SoapClient(plugin_dir_path(__DIR__).'api/'.($this->production ? 'production' : 'test').'/RateService_v'.$this->rateservice_version.'.wsdl', ['trace' => 1]);
        $result = $client->getRates($request);

        $this->debug('Freight Shipping API RESPONSE: <a href="#" class="debug_reveal">Reveal</a>
            <pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">
                '.print_r($result, true).'
            </pre>');

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
     * todo: whole thing
     * @param  mixed  $result
     * @return void
     */
    private function process_result($result = null)
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
                    if ($this->request_type === "LIST") {
                        // LIST quotes return both ACCOUNT rates (in RatedShipmentDetails[1])
                        // and LIST rates (in RatedShipmentDetails[3])
                        foreach ($quote->RatedShipmentDetails as $i => $d) {
                            if (strpos($d->ShipmentRateDetail->RateType, 'PAYOR_LIST') !== false) {
                                $details = $quote->RatedShipmentDetails[$i];
                                break;
                            }
                        }
                    } else {
                        // ACCOUNT quotes may return either ACCOUNT rates only OR
                        // ACCOUNT rates and LIST rates.
                        foreach ($quote->RatedShipmentDetails as $i => $d) {
                            if (strpos($d->ShipmentRateDetail->RateType, 'PAYOR_ACCOUNT') !== false) {
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

                $rate_code = (string) $quote->ServiceType;
                $rate_id = $this->id.':'.$rate_code;
                $rate_name = (string) $this->services[$quote->ServiceType];
                $rate_cost = (float) $details->ShipmentRateDetail->TotalNetCharge->Amount;

                $this->prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost);
            }
        }
    }

    /**
     *
     *
     * @param  mixed  $rate_code
     * @param  mixed  $rate_id
     * @param  mixed  $rate_name
     * @param  mixed  $rate_cost
     */
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost)
    {
        // Name adjustment
        if (!empty($this->custom_services[$rate_code]['name'])) {
            $rate_name = $this->custom_services[$rate_code]['name'];
        }

        // Cost adjustment %
        if (!empty($this->custom_services[$rate_code]['adjustment_percent'])) {
            $rate_cost += ($rate_cost * ((float) $this->custom_services[$rate_code]['adjustment_percent'] / 100));
        }
        // Cost adjustment
        if (!empty($this->custom_services[$rate_code]['adjustment'])) {
            $rate_cost += (float) $this->custom_services[$rate_code]['adjustment'];
        }

        // Enabled check
        if (isset($this->custom_services[$rate_code]) && empty($this->custom_services[$rate_code]['enabled'])) {
            return;
        }

        // Merging
        if (isset($this->found_rates[$rate_id])) {
            $rate_cost = $rate_cost + $this->found_rates[$rate_id]['cost'];
            $packages = 1 + $this->found_rates[$rate_id]['packages'];
        } else {
            $packages = 1;
        }

        // Sort
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
     * Add found rates to WooCommerce
     *
     */
    public function add_found_rates()
    {
        if ($this->found_rates) {
            // remove ground rates if shipping internationally
            if ($this->is_shipping_internationally() && !$this->need_direct_distribution()) {
                unset($this->found_rates['freight:FREIGHT_GROUND']);
            }

            if ($this->offer_rates === 'all') {
                uasort($this->found_rates, [$this, 'sort_rates']);
                foreach ($this->found_rates as $key => $rate) {
                    $this->add_rate($rate);
                }
            } else {
                $cheapest_rate = null;
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
     * todo: remove
     * Determine if the current shipping is to be done internationally
     *
     * @return bool
     */
    public function is_shipping_internationally()
    {
        // compare base and package country: not equal for international shipping
        return WC()->countries->get_base_country() !== $this->package['destination']['country'];
    }

    /**
     * todo: remove
     * Checks to see if we need to return international ground direct distribution rates.
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
     *
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