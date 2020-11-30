<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping Freight Core
 *
 * @extends WC_Shipping_Method
 */
class WC_Shipping_Freight extends WC_Shipping_Method
{
    private $default_boxes;
    private $found_rates;
    private $services;

    public function __construct($instance_id = 0)
    {
        $this->id = 'freight';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Freight Shipping', 'woocommerce-shipping-freight');
        $this->method_description = __('The Freight Shipping extension obtains rates dynamically via API during cart/checkout.', 'woocommerce-shipping-freight');
        $this->default_boxes = include(__DIR__.'/data/data-box-sizes.php');
        $this->services = include(__DIR__.'/data/data-service-codes.php');
        $this->supports = ['shipping-zones', 'instance-settings', 'settings',];
        $this->init();
    }

    /**
     * See if destination qualifies for freight shipping
     *
     * @param  array  $package
     *
     * @return  bool
     * @since   2.0.0
     */
    public function is_available($package)
    {
        if (empty($package['destination']['country'])) {
            return false;
        }

        return apply_filters('woocommerce_shipping_'.$this->id.'_is_available', true, $package);
    }

    /**
     * Properties setter
     *
     * @return  void
     * @since   2.0.0
     */
    private function set_settings()
    {
        $this->title = $this->get_option('title', $this->method_title);
        $this->origin = apply_filters('woocommerce_freight_origin_postal_code', str_replace(' ', '', strtoupper($this->get_option('origin'))));
        $this->origin_country = apply_filters('woocommerce_freight_origin_country_code', WC()->countries->get_base_country());
        $this->api_url = $this->get_option('api_url');
        $this->api_user = $this->get_option('api_user');
        $this->api_pass = $this->get_option('api_pass');
        /*$this->production = (($bool = $this->get_option('production')) && $bool === 'yes');*/
        $this->debug = (($bool = $this->get_option('debug')) && $bool === 'yes');
        $this->request_type = $this->get_option('request_type', 'LIST');
        $this->packing_method = $this->get_option('packing_method', 'per_item');
        $this->boxes = $this->get_option('boxes', []);
        /*$this->custom_services = $this->get_option('services', []);*/
        $this->offer_rates = $this->get_option('offer_rates', 'all');
        $this->residential = (($bool = $this->get_option('residential')) && $bool === 'yes');
        $this->freight_enabled = (($bool = $this->get_option('freight_enabled')) && $bool === 'yes');

        if ($this->freight_enabled) {
            $this->freight_class = str_replace(['CLASS_', '.'], ['', '_'], $this->get_option('freight_class'));
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
    }

    /**
     *
     * @since 2.0.0
     */
    private function init()
    {
        $this->init_form_fields();
        $this->set_settings();

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);
    }

    /**
     * Process settings on save
     *
     * @return  void
     * @since   2.0.0
     */
    public function process_admin_options()
    {
        parent::process_admin_options();

        $this->set_settings();
    }

    /**
     * Load admin scripts
     *
     * @return  void
     * @since   2.0.0
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
     *
     * @return  void
     * @since   2.0.0
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug || (current_user_can('manage_options') && 'error' == $type)) {
            wc_add_notice($message, $type);
        }
    }

    /**
     *
     * @since 2.0.0
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = include(__DIR__.'/data/data-settings.php');
        $freight_classes = include(__DIR__.'/data/data-freight-classes.php');

        $this->form_fields = [
            'freight_enabled' => [
                'title' => __('Enable', 'woocommerce-shipping-freight'),
                'label' => __('Enable Freight', 'woocommerce-shipping-freight'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'api' => [
                'title' => __('API Settings', 'woocommerce-shipping-freight'),
                'type' => 'title',
                'description' => __('Description should go here',
                    'woocommerce-shipping-freight'),
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
                'description' => __('Note: These rates require the customers ZIP.',
                    'woocommerce-shipping-freight'),
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
                'description' => sprintf(__('This is the default freight class for shipments. This can be overridden using <a href="%s">shipping classes</a>', 'woocommerce-shipping-freight'), $shipping_class_link),
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
     *
     *
     * @return  string
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
     *
     * @return  string
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
     *
     * @return  array
     * @since   2.0.0
     */
    public function validate_box_packing_field($key)
    {
        $boxes_name = $_POST['boxes_name'] ?? [];
        $boxes_length = $_POST['boxes_length'] ?? [];
        $boxes_width = $_POST['boxes_width'] ?? [];
        $boxes_height = $_POST['boxes_height'] ?? [];
        $boxes_box_weight = $_POST['boxes_box_weight'] ?? [];
        $boxes_max_weight = $_POST['boxes_max_weight'] ?? [];
        $boxes_enabled = $_POST['boxes_enabled'] ?? [];

        $boxes = [];

        if (!empty($boxes_length) && count($boxes_length) > 0) {
            for ($i = 0; $i <= max(array_keys($boxes_length)); $i++) {
                if (!isset($boxes_length[$i])) {
                    continue;
                }

                if ($boxes_length[$i] && $boxes_width[$i] && $boxes_height[$i]) {
                    $boxes[] = [
                        'name' => wc_clean($boxes_name[$i]),
                        'length' => (float) $boxes_length[$i],
                        'width' => (float) $boxes_width[$i],
                        'height' => (float) $boxes_height[$i],
                        'box_weight' => (float) $boxes_box_weight[$i],
                        'max_weight' => (float) $boxes_max_weight[$i],
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
     *
     *
     * @param  mixed  $key
     *
     * @return  array
     * @since   2.0.0
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
     * Get packages. Divide the WC package into packages/parcels
     *
     * @param  array  $package
     *
     * @return  array
     * @since   2.0.0
     */
    public function get_freight_packages($package)
    {
        switch ($this->packing_method) {
            /* this feature is out of scope.
             * case 'box_packing':
                return $this->box_shipping($package);
                break;*/
            case 'per_item':
            default:
                return $this->per_item_shipping($package);
                break;
        }
    }

    /**
     * Get the freight class
     *
     * @param  int  $shipping_class_id
     *
     * @return  string
     * @since   2.0.0
     */
    public function get_freight_class($shipping_class_id)
    {
        $class = get_term_meta($shipping_class_id, 'freight_class', true);

        return $class ?? null;
    }

    /**
     * Pack items individually.
     *
     * @param  mixed  $package  Package to ship.
     *
     * @return  mixed
     * @since   2.0.0
     */
    private function per_item_shipping($package)
    {
        $to_ship = [];
        $group_id = 1;

        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'),
                    $item_id), 'notice');
                continue;
            }

            if (!$values['data']->get_weight()) {
                $this->debug(sprintf(__('Product # is missing weight. Aborting.', 'woocommerce-shipping-freight'),
                    $item_id), 'error');
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
            $to_ship[] = $group;
            $group_id++;
        }

        return $to_ship;
    }

    /**
     * Pack into boxes with weights and dimensions.
     *
     * @param  mixed  $package  Package to ship.
     *
     * @return  array
     * @since   2.0.0
     */
    private function box_shipping($package)
    {
        if (!class_exists('WC_Boxpack')) {
            include_once 'box-packer/class-wc-boxpack.php';
        }

        $boxpack = new WC_Boxpack(['prefer_packets' => true]);

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
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'woocommerce-shipping-freight'), $item_id), 'notice');
                continue;
            }

            if ($values['data']->get_length() && $values['data']->get_height() && $values['data']->get_width() && $values['data']->get_weight()) {
                $dimensions = [$values['data']->get_length(), $values['data']->get_height(), $values['data']->get_width()];

                for ($i = 0; $i < $values['quantity']; $i++) {
                    $boxpack->add_item(
                        wc_get_dimension($dimensions[2], 'in'),
                        wc_get_dimension($dimensions[1], 'in'),
                        wc_get_dimension($dimensions[0], 'in'),
                        wc_get_weight($values['data']->get_weight(), 'lbs'), $values['data']->get_price(), ['data' => $values['data'],]
                    );
                }
            } else {
                $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'woocommerce-shipping-freight'), $item_id), 'error');
                return;
            }
        }

        // Pack it.
        $boxpack->pack();
        $packages = $boxpack->get_packages();
        $to_ship = [];
        $group_id = 1;

        foreach ($packages as $package) {
            $this->debug(($package->unpacked ? 'Unpacked Item ' : 'Packed ').$package->id.' - '.$package->length.'x'.$package->width.'x'.$package->height);
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
                $group['freight_class'] = $highest_freight_class ?? null;
            }
            $to_ship[] = $group;
            $group_id++;
        }
        return $to_ship;
    }

    /**
     * todo: feature is out of scope
     * See if address is residential
     */
    /*public function residential_address_validation($package)
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
                        'User' => $this->api_user,
                        'Password' => $this->api_pass,
                    ],
                ];
                $request['TransactionDetail'] = ['CustomerTransactionId' => ' *** Address Validation Request v2 from WooCommerce ***'];
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
                    } else {
                        if ($addressResult->ProposedAddressDetails->ResidentialStatus == 'RESIDENTIAL') {
                            $residential = true;
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }

        $this->residential = apply_filters('woocommerce_freight_address_type', $residential, $package);

        if ($this->residential == false) {
            $this->debug(__('Business Address', 'woocommerce-shipping-freight'));
        }
    }*/

    private function buildXML($data)
    {

    }

    /**
     * todo: the primary request method
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
        // Drop in arbitrary delivery dates since we just need a quote.
        if (!array_key_exists('DropDate', $data)) {
            $data['ErrorReporting']['DropDate'] = false;
            $data['DropDate'] = (new DateTime('tomorrow'))->format('m/d/Y H:i');
        }

        if (!array_key_exists('Pickup', $data)) {
            $data['ErrorReporting']['Pickup'] = false;
            $data['Pickup'] = (new DateTime('+4 days'))->format('m/d/Y H:i');
        }

        // Just for a cleaner xml code
        if (is_array($data['items']['RatesRequest_Item']) && !empty($data['items']['RatesRequest_Item'])) {
            $this->items = $data['items']['RatesRequest_Item'];
        }

        $this->writer = new XMLWriter;

        // Arbitrary just for quotes.
        $sServiceFlagPickup = 'LGDC';
        $sServiceFlagDelivery = 'RSDC';
        $sMode = 'LTL';
        $sCarrierName = 'OLD DOMINION FREIGHT LINE INC';

        $this->writer->openMemory();
        $this->writer->startDocument();

        $this->writer->startElement('service-request');
        $this->writer->writeElement('service-id', 'XMLRating');
        $this->writer->writeElement('request-id', '08112016001');

        $this->writer->startElement('data');
        $this->writer->startElement('RateRequest');
        $this->writer->startElement('Constraints');

        $this->writer->writeElement('Mode', $sMode);
        $this->writer->startElement('ServiceFlags');
        $this->writer->startElement('ServiceFlag');
        $this->writer->writeAttribute('code', $sServiceFlagPickup);
        $this->writer->endElement();

        $this->writer->startElement('ServiceFlag');
        $this->writer->writeAttribute('code', $sServiceFlagDelivery);
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->writeElement('Contract', '');

        $this->writer->startElement('Carriers');
        $this->writer->startElement('Carrier');
        $this->writer->writeAttribute('name', $sCarrierName);
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->writeElement('Carrier', '');
        $this->writer->writeElement('PaymentTerms', $this->items['PaymentTerms']);
        $this->writer->endElement();

        $this->writer->startElement('Items');
        foreach ($this->items as $k => $v):
            $this->writer->startElement('Item');
            $this->writer->writeAttribute('sequence', $k);
            $this->writer->writeAttribute('freightClass', $this->items[$k]['FreightClass']);

            $this->writer->startElement('Weight');
            $this->writer->writeAttribute('units', $this->items[$k]['Weight']['UnitOfMeasure']);
            $this->writer->text($this->items[$k]['Weight']['WeightAmt']);
            $this->writer->endElement();

            $this->writer->startElement('Dimensions');
            $this->writer->writeAttribute('length', $this->items[$k]['Dimensions']['Length']);
            $this->writer->writeAttribute('width', $this->items[$k]['Dimensions']['Width']);
            $this->writer->writeAttribute('height', $this->items[$k]['Dimensions']['Height']);
            $this->writer->writeAttribute('units', $this->items[$k]['Dimensions']['UnitOfMeasure']);
            $this->writer->endElement();
            $this->writer->endElement();
        endforeach;
        $this->writer->endElement();

        $this->writer->startElement('Events');
        $this->writer->startElement('Event');
        $this->writer->writeAttribute('sequence', 1);
        $this->writer->writeAttribute('type', 'Pickup');
        $this->writer->writeAttribute('date', $data['Pickup']);

        $this->writer->startElement('Location');
        $this->writer->writeElement('Zip', $data['origin_postcode']);
        $this->writer->writeElement('Country', 'USA');
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->startElement('Event');
        $this->writer->writeAttribute('sequence', 2);
        $this->writer->writeAttribute('type', 'Drop');
        $this->writer->writeAttribute('date', $data['DropDate']);

        $this->writer->startElement('Location');
        $this->writer->writeElement('Zip', $data['destination_postcode']);
        $this->writer->writeElement('Country', 'USA');
        $this->writer->endElement();

        $this->writer->endElement();
        $this->writer->endElement();
        $this->writer->writeElement('ReferenceNumbers', '');
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->endElement();
        $this->writer->endDocument();

        return $this->writer->outputMemory(true);




        //$request = [];

        // Prepare Shipping Request for Freight.
        /*$request = [
            'userid' => $this->api_user,
            'password' => $this->api_pass,
        ];*/



        //$request['ReturnTransitAndCommit'] = false;
        /*$request['RequestedShipment']['PreferredCurrency'] = get_woocommerce_currency();
        $request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP';
        $request['RequestedShipment']['ShipTimestamp'] = date('c', strtotime('+1 Weekday'));
        $request['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING';
        $request['RequestedShipment']['Shipper'] = [
            'Address' => [
                'PostalCode' => $this->origin,
                'CountryCode' => $this->origin_country,
            ],
        ];*/

        /*$request['RequestedShipment']['RateRequestTypes'] = 'LIST' === $this->request_type ? 'LIST' : 'NONE';*/

        /*$request['RequestedShipment']['Recipient'] = [
            'Address' => [
                'StreetLines' => [$package['destination']['address'], $package['destination']['address_2']],
                'Residential' => $this->residential,
                'PostalCode' => str_replace(' ', '', strtoupper($package['destination']['postcode'])),
                'City' => strtoupper($package['destination']['city']),
                'StateOrProvinceCode' => strlen($package['destination']['state']) == 2 ? strtoupper($package['destination']['state']) : '',
                'CountryCode' => $package['destination']['country'],
            ],
        ];*/

        return apply_filters('woocommerce_freight_api_request', $request);
    }

    /**
     *
     *
     * @param  $freight_packages array  Packages to ship
     * @param  $package          array  The package passed from WooCommerce
     * @param  $request_type     string
     *
     * @return array
     */
    private function get_freight_requests($freight_packages, $package, $request_type = '')
    {
        $requests = [];

        // All requests for this package get this data
        $package_request = $this->get_freight_api_request($package);

        if ($freight_packages) {
            // Max of 99 per request
            // todo: this might need to be adjusted
            $parcel_chunks = array_chunk($freight_packages, 99);

            foreach ($parcel_chunks as $parcels) {
                $request = $package_request;
                $total_packages = 0;
                $total_weight = 0;
                $freight_class = '';

                // Store as line items
                $request['RequestedShipment']['RequestedPackageLineItems'] = [];

                foreach ($parcels as $key => $parcel) {
                    $parcel_request = $parcel;
                    $total_packages += $parcel['GroupPackageCount'];
                    $parcel_packages = $parcel['GroupPackageCount'];
                    $total_weight += $parcel['Weight']['Value'] * $parcel_packages;

                    if ('freight' === $request_type) {
                        // Get the highest freight class for shipment
                        if (isset($parcel['freight_class']) && $parcel['freight_class'] > $freight_class) {
                            $freight_class = $parcel['freight_class'];
                        }
                    }

                    // Remove temp elements
                    unset($parcel_request['freight_class']);
                    unset($parcel_request['packed_products']);
                    unset($parcel_request['package_id']);

                    $parcel_request = array_merge(['SequenceNumber' => $key + 1], $parcel_request);
                    $request['RequestedShipment']['RequestedPackageLineItems'][] = $parcel_request;
                }

                // Size
                $request['RequestedShipment']['PackageCount'] = $total_packages;

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
                    $freight_class = $freight_class ?? $this->freight_class;
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
                }
                // Add request
                $requests[] = $request;
            }
        }
        return $requests;
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
        $this->debug(__('FREIGHT SHIPPING debug mode is on - to hide these messages, turn debug mode off in the settings.', 'woocommerce-shipping-freight'));

        // See if address is residential.
        /*$this->residential_address_validation($package);*/

        // Get requests.
        $freight_packages = $this->get_freight_packages($package);
        $freight_requests = $this->get_freight_requests($freight_packages, $package);

        if ($freight_requests) {
            $this->run_package_request($freight_requests);
        }

        if ($this->freight_enabled && ($freight_requests = $this->get_freight_requests($freight_packages, $package, 'freight'))) {
            $this->run_package_request($freight_requests);
        }

        // Ensure rates were found for all packages.
        $packages_to_quote_count = count($freight_requests);

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
     */
    private function get_result($request)
    {
        $this->debug('FREIGHT SHIPPING REQUEST: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info">'.print_r($request,
                true).'</pre>');

        try {
            $payload = $this->buildXML($request);

            $data = [
                'userid' => $this->api_user,
                'password' => $this->api_pass,
                'request' => $payload,
            ];

            $request['TransactionDetail'] = [
                'CustomerTransactionId' => ' *** WooCommerce Rate Request ***',
            ];

            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                ],
            ];

            $response = simplexml_load_string(
                file_get_contents(
                    $this->api,//api url
                    null,
                    stream_context_create($options)
                )
            );

            $rates = base64_decode($response->data[0]);
            $result = simplexml_load_string($rates);

            /*if (!$result->StatusCode) {
                //status code is failure
                //throw exception
            }*/

            /*$client = new SoapClient($rate_soap_file_location, ['trace' => 1]);*/
            /*$result = $client->getRates($request);*/
        } catch (Exception $e) {
            //$stream_context_args = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
            /*$soap_args = [
                'trace' => 1,
                'stream_context' => stream_context_create($stream_context_args),
            ];*/
            /*$client = new SoapClient($rate_soap_file_location, $soap_args);
            $result = $client->getRates($request);*/
        }

        $this->debug('FREIGHT SHIPPING RESPONSE: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info">'.print_r($result, true).'</pre>');

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
     *
     *
     * @param  mixed  $result
     *
     * @return void
     * @since  2.0.0
     */
    private function process_result($result = '')
    {
        /*if ($result && !empty ($result->RateReplyDetails)) {
            $rate_reply_details = $result->RateReplyDetails;*/

        // Workaround for when an object is returned instead of array
        /*if (is_object($rate_reply_details) && isset($rate_reply_details->ServiceType)) {
            $rate_reply_details = [$rate_reply_details];
        }*/

        /*if (!is_array($rate_reply_details)) {
            return false;
        }*/

        //foreach ($rate_reply_details as $quote) {
            /*if (is_array($quote->RatedShipmentDetails)) {
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
            }*/

            /*if (empty($details)) {
                continue;
            }

            $rate_code = (string) $quote->ServiceType;
            $rate_id = $this->get_rate_id($rate_code);
            $rate_name = (string) $this->services[$quote->ServiceType];
            $rate_cost = (float) $details->ShipmentRateDetail->TotalNetCharge->Amount;

            $this->prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost);
        }*/
        //}
    }

    /**
     *
     *
     * @param  mixed  $rate_code  Rate code.
     * @param  mixed  $rate_id    Rate ID.
     * @param  mixed  $rate_name  Rate name.
     * @param  mixed  $rate_cost  Cost.
     *
     * @since  2.0.0
     */
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost)
    {
        // Name adjustment.
        /*if (!empty($this->custom_services[$rate_code]['name'])) {
            $rate_name = $this->custom_services[$rate_code]['name'];
        }*/

        // Cost adjustment %.
        /*if (!empty($this->custom_services[$rate_code]['adjustment_percent'])) {
            $rate_cost += ($rate_cost * ((float) $this->custom_services[$rate_code]['adjustment_percent'] / 100));
        }*/

        // Cost adjustment.
        /*if (!empty($this->custom_services[$rate_code]['adjustment'])) {
            $rate_cost += (float) $this->custom_services[$rate_code]['adjustment'];
        }*/

        // Enabled check.
        /*if (isset($this->custom_services[$rate_code]) && empty($this->custom_services[$rate_code]['enabled'])) {
            return;
        }*/

        // Merging.
        /*if (isset($this->found_rates[$rate_id])) {
            $rate_cost = $rate_cost + $this->found_rates[$rate_id]['cost'];
            $packages = 1 + $this->found_rates[$rate_id]['packages'];
        } else {
            $packages = 1;
        }*/

        // Sort.
        /*if (isset($this->custom_services[$rate_code]['order'])) {
            $sort = $this->custom_services[$rate_code]['order'];
        } else {
            $sort = 999;
        }*/
        /*$this->found_rates[$rate_id] = [
            'id' => $rate_id,
            'label' => $rate_name,
            'cost' => $rate_cost,
            'sort' => $sort,
            'packages' => $packages,
        ];*/
    }

    /**
     * Get meta data string for the shipping rate.
     *
     * @param  array  $params  Meta data info to join.
     *
     * @return  string Rate meta data.
     * @since   2.0.0
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
     *
     * @return void
     * @since  2.0.0
     */
    public function add_found_rates()
    {
        if ($this->found_rates) {
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
     * sort_rates function.
     *
     * @param  mixed  $a
     * @param  mixed  $b
     *
     * @return int
     * @since  2.0.0
     */
    public function sort_rates($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }
}
