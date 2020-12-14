<?php

if (!defined('ABSPATH'))
    exit;

/**
 * Shipping Freight Core
 *
 * @extends WC_Shipping_Method
 */
class WC_Shipping_Freight extends WC_Shipping_Method
{
    private $debug;
    private $default_boxes;
    private $found_rates;
    private $services;
    private $orderCount = 0;
    private $noShippingProducts;

    public function __construct($instance_id = 0)
    {
        $this->id = 'freight';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Freight Shipping', 'woocommerce-shipping-freight');
        $this->method_description = __('The Freight Shipping extension obtains rates dynamically via API during cart/checkout.', 'woocommerce-shipping-freight');
        $this->supports = ['shipping-zones', 'instance-settings', 'settings'];

        /*$this->default_boxes = include(__DIR__.'/data/data-box-sizes.php');
        $this->services = include(__DIR__.'/data/data-service-codes.php');*/

        $this->init();
    }

    /**
     * See if destination qualifies for freight shipping
     *
     * @param  array  $package
     * @return  bool
     * @since   2.0.0
     */
    public function is_available($package)
    {
        if (empty($package['destination']['country']))
            return false;

        return apply_filters('woocommerce_shipping_'.$this->id.'_is_available', true, $package);
    }

    /**
     * Set the current class properties.
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
        $this->debug = (($bool = $this->get_option('debug')) && $bool === 'yes');
        /*$this->request_type = $this->get_option('request_type', 'LIST');*/
        /*$this->packing_method = $this->get_option('packing_method', 'per_item');*/
        /*$this->boxes = $this->get_option('boxes', []);*/
        /*$this->custom_services = $this->get_option('services', []);*/
        /*$this->offer_rates = $this->get_option('offer_rates', 'all');*/
        $this->freight_enabled = (($bool = $this->get_option('freight_enabled')) && $bool === 'yes');

        if ($this->freight_enabled) {
            $this->freight_class = str_replace(['CLASS_', '.'], ['', '_'], $this->get_option('freight_class'));
            $this->freight_shipper_street = $this->get_option('freight_shipper_street');
            $this->freight_shipper_street_2 = $this->get_option('freight_shipper_street_2');
            $this->freight_shipper_city = $this->get_option('freight_shipper_city');
            $this->freight_shipper_state = $this->get_option('freight_shipper_state');
            $this->freight_shipper_postcode = $this->get_option('freight_shipper_postcode');
            $this->freight_shipper_country = $this->get_option('freight_shipper_country');
        }
    }

    /**
     * Initialize the form fields and custom settings in wp-admin
     *
     * @return void
     * @since 2.0.0
     */
    private function init()
    {
        $this->init_form_fields();
        $this->set_settings();

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);

        if ($this->debug) {
            require (__DIR__).'../vendor/autoload.php';
        }
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
     * @return  void
     * @since   2.0.0
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug || current_user_can('manage_options')) {
            wc_add_notice($message, $type);
        }
    }

    /**
     *
     */
    public function no_shipping_notice()
    {
        if (isset($this->noShippingProducts) && is_array($this->noShippingProducts)) {
            foreach ($this->noShippingProducts as $product) {
                echo '
                    <ul class="woocommerce-error" role="alert">
                        <li>The product <strong>'.$product->get_title().'</strong> cannot be shipped and is not included in shipping pricing. Please contact us for details.</li>
                    </ul>
                ';
            }
        }
    }

    /**
     * Define the wp-admin form fields
     *
     * @return void
     * @since 2.0.0
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = include(__DIR__.'/data/data-settings.php');
        $this->form_fields = include(__DIR__.'data/data-form-fields.php');
    }

    /**
     * Build the services html view in wp-admin
     *
     * @return  string
     * @since   2.0.0
     */
    /*public function generate_services_html()
    {
        ob_start();
        include('views/html-services.php');
        return ob_get_clean();
    }*/

    /**
     * Build the box packing html view in wp-admin
     *
     * @return  string
     * @since   2.0.0
     */
    /*public function generate_box_packing_html()
    {
        ob_start();
        include('views/html-box-packing.php');
        return ob_get_clean();
    }*/

    /**
     * Check the box packing fields and verify.
     *
     * @param  mixed  $key
     * @return  array
     * @since   2.0.0
     */
    /*public function validate_box_packing_field($key)
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
    }*/

    /**
     * Check the services fields and verify.
     *
     * @param  mixed  $key
     * @return  array
     * @since   2.0.0
     */
    /*public function validate_services_field($key)
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
    }*/

    /**
     * Get packages. Divide the WC package into packages/parcels
     *
     * @param   array  $package
     * @return  array
     * @since   2.0.0
     */
    public function get_freight_packages($package)
    {
        switch ($this->packing_method) {
            case 'per_item':
            default:
                return $this->per_item_shipping($package);
                break;
        }
    }

    /**
     * Get the freight class
     *
     * @param   int     $shipping_class_id
     * @return  string
     * @since   2.0.0
     */
    public function get_freight_class($shipping_class_id)
    {
        $class = get_term_meta($shipping_class_id, 'freight_class', true);

        return $class ?? false;
    }

    /**
     * Pack items individually.
     *
     * @param   mixed  $package  Package to ship.
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
                $this->debug(sprintf(__('Product <strong>'.$values['data']->get_title().' : %s</strong> is missing weight.', 'woocommerce-shipping-freight'),
                    $item_id), 'error');

                $this->noShippingProducts[] = $values['data'];

                add_action('woocommerce_after_cart_table', [$this, 'no_shipping_notice'], 10, 0);

                continue;
            }

            /*if (!$values['data']->get_length() || !$values['data']->get_width() || $values['data']->get_height()) {
                $this->debug(sprintf(__('Product <strong>'.$values['data']->get_title().' : %s</strong> is missing length, width or height.',
                    'woocommerce-shipping-freight'), $item_id), 'error');

                $this->noShippingProducts[] = $values['data'];
                add_action('woocommerce_after_cart_table', [$this, 'no_shipping_notice'], 10, 0);

                continue;
            }*/

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
     * Build the XML request
     *
     * @param  mixed  $package
     * @return SimpleXMLElement
     * @since 2.0.0
     */
    private function get_freight_api_request($package)
    {
        $data = $package;
+d($data);
        if (!array_key_exists('DropDate', $data)) {
            $data['DropDate'] = (new DateTime('tomorrow'))->format('m/d/Y H:i');
        }

        if (!array_key_exists('Pickup', $data)) {
            $data['Pickup'] = (new DateTime('+4 days'))->format('m/d/Y H:i');
        }

        $this->writer = new XMLWriter;
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
        $this->writer->writeElement('PaymentTerms', '');
        $this->writer->endElement();

        $this->writer->startElement('Items');

        $this->writer->startElement('Item');
        $this->writer->writeAttribute('sequence', '1');
        $this->writer->writeAttribute('freightClass', $this->freight_class);

        $this->writer->startElement('Weight');
        $this->writer->writeAttribute('units', 'lb');
        $this->writer->text($data['Weight']['Value']);
        $this->writer->endElement();

        $this->writer->startElement('Dimensions');
        $this->writer->writeAttribute('length', $data['Dimensions']['Length']);
        $this->writer->writeAttribute('width', $data['Dimensions']['Width']);
        $this->writer->writeAttribute('height', $data['Dimensions']['Height']);
        $this->writer->writeAttribute('units', 'inches');
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->endElement();

        $this->writer->startElement('Events');
        $this->writer->startElement('Event');
        $this->writer->writeAttribute('sequence', 1);
        $this->writer->writeAttribute('type', 'Pickup');

        $this->writer->writeAttribute('date', $data['Pickup']);

        $this->writer->startElement('Location');
        $this->writer->writeElement('Zip', $this->origin);
        $this->writer->writeElement('Country', 'USA');
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->startElement('Event');
        $this->writer->writeAttribute('sequence', 2);
        $this->writer->writeAttribute('type', 'Drop');
        $this->writer->writeAttribute('date', $data['DropDate']. "12:01");

        $this->writer->startElement('Location');
        $this->writer->writeElement('Zip', $this->package['destination']['postcode']);
        $this->writer->writeElement('Country', 'USA');
        $this->writer->endElement();

        $this->writer->endElement();
        $this->writer->endElement();
        $this->writer->writeElement('ReferenceNumbers', '');
        $this->writer->endElement();
        $this->writer->endElement();

        $this->writer->endElement();
        $this->writer->endDocument();

        $payload = $this->writer->outputMemory(true);

        $this->debug('FREIGHT SHIPPING REQUEST (get_freight_api_request:820): <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info">'.print_r('Date/Time: '.date('Y-m-d H:i:s').'<br />API endpoint: '.$this->api_url.'<br />Payload: '.$payload, true).'</pre>');

        return apply_filters('woocommerce_freight_api_request', $payload);
    }

    /**
     * Calculate shipping cost.
     *
     * @param  mixed  $package  Package to ship.
     * @return void
     * @since  2.0.0
     */
    public function calculate_shipping($package = [])
    {
        $this->found_rates = [];
        $this->package = $package;

        $this->debug(__('FREIGHT SHIPPING debug mode is on - to hide these messages, turn debug mode off in the settings.', 'woocommerce-shipping-freight'));

        $freight_packages = $this->get_freight_packages($package);

        +d($this, $this->cart);

        foreach ($freight_packages as $freight_package) {
            if ($this->freight_enabled && ($freight_request = $this->get_freight_api_request($freight_package))) {
                try {
                    $this->process_result($this->get_result($freight_request), $freight_package);
                } catch (Exception $e) {
                    $this->debug(print_r($e, true), 'error');

                    return false;
                }
            }

            $packages_to_quote_count[] = $freight_package;
        }

        $packages_to_quote_count = is_countable($freight_request) ? count($freight_request) : 1;
        //+d($this->found_rates);

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
                            'length' => $freight_package['Dimensions']['Length'] ?? 1,
                            'width' => $freight_package['Dimensions']['Width'] ?? 1,
                            'height' => $freight_package['Dimensions']['Height'] ?? 1,
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
     * Actually execute the API request call.
     *
     * @param  mixed  $request
     * @return SimpleXMLElement $result
     * @since 2.0.0
     */
    private function get_result($request)
    {
        try {
            $data = [
                'userid' => $this->api_user,
                'password' => $this->api_pass,
                'request' => $request,
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
                    $this->api_url,
                    null,
                    stream_context_create($options)
                )
            );

            $rates = base64_decode($response->data[0]);
            $result = simplexml_load_string($rates);

        } catch (Exception $e) {
            $this->debug('EXCEPTION (963) : '.$e->getMessage().'<a href="#" class="debug_reveal">Reveal</a>
                <pre class="debug_info">'.print_r([$data, $options, $response], true).'</pre>');
        }

        $this->debug('FREIGHT SHIPPING RESPONSE (967) : <a href="#" class="debug_reveal">Reveal</a>
            <pre class="debug_info">Freight Total + '.print_r($result, true).'</pre>');

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
     * Process the data returned by the API.
     * (Mostly just hand-off, as is. Use as needed)
     *
     * @param  mixed  $result
     * @return mixed
     * @since  2.0.0
     */
    private function process_result($result, $package)
    {
        if (!$result->StatusCode || (int) $result->StatusCode !== 0 || !$result->PriceSheets->PriceSheet->Service) {

            $this->debug('STATUS CODE NOT ZERO: <a href="#" class="debug_reveal">Reveal</a>
                <pre class="debug_info">'.print_r($result, true).'</pre>');

            wc_enqueue_js("
                jQuery('a.debug_reveal').on('click', function(){
                    jQuery(this).closest('div').find('.debug_info').slideDown();
                    jQuery(this).remove();
                    return false;
                });
                jQuery('pre.debug_info').hide();
		    ");

            return false;
        }

        //todo, if somsething has more than 1 qty check, duplicate that pricesheet.
        +d($PriceSheets);

        $PriceSheets = $result->PriceSheets;

        $this->prepare_rate($PriceSheets->PriceSheet->Total, $PriceSheets->PriceSheet->Service ?? false);
    }

    /**
     * Prepare the shipping rates for the cart
     *
     * @param  string|int  $total   Itemized total for the service
     * @param  string      $service Shipping service offered
     * @since  2.0.0
     */
    private function prepare_rate($total, $service)
    {
        $service = (string) $service;

        if (isset($this->services[$service]['name']) && !empty($this->services[$service]['name'])) {
            $rate_name = $this->services[$service]['name'];
        }

        if (!empty($this->services[$service]['adjustment_percent'])) {
            $total += ($service * ((float) $this->services[$service]['adjustment_percent'] / 100));
        }

        if (!empty($this->services[$service]['adjustment'])) {
            $total += (float) $this->services[$service]['adjustment'];
        }

        if (isset($this->services[$service]) && empty($this->services[$service]['enabled'])) {
            return false;
        }

        $rate_cost = $total + ($this->found_rates[$service]['cost'] ?? 0);
        $packages = 1 + ($this->found_rates[$service]['packages'] ?? 0);

        return $this->found_rates[$service] = [
            'id' => $service,
            'label' => $rate_name,
            'cost' => $rate_cost,
            'sort' => 1,
            'packages' => $packages,
        ];
    }

    /**
     * Get meta data string for the shipping rate.
     *
     * @param   array  $params  Meta data info to join.
     * @return  string          Rate meta data.
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
            uasort($this->found_rates, [$this, 'sort_rates']);

            foreach ($this->found_rates as $key => $rate) {
                $this->add_rate($rate);
            }
        }
    }

    /**
     * Sort the found rates in a particular order
     *
     * @param  mixed  $a
     * @param  mixed  $b
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
