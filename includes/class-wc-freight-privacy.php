<?php

if (!class_exists('WC_Abstract_Privacy')) {
    return;
}

class WC_Freight_Privacy extends WC_Abstract_Privacy
{
    public function __construct()
    {
        parent::__construct(__('Freight', 'woocommerce-shipping-freight'));
    }

    /**
     * Gets the message of the privacy to display.
     *
     * @return string
     * @since  2.0.0
     */
    public function get_privacy_message()
    {
        return wpautop(sprintf(__('By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>',
            'woocommerce-shipping-freight'), '#'));
    }
}

new WC_Freight_Privacy();
