<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map freight classes to shipping classes
 */
class WC_Freight_Mapping
{
    public $freight_class;
    public $display_freight_class;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->classes = include(dirname(__FILE__).'/data/data-freight-classes.php');

        add_filter('woocommerce_get_shipping_classes', [$this, 'get_shipping_classes']);
        add_filter('woocommerce_shipping_classes_columns', [$this, 'add_shipping_class_column']);
        add_action('woocommerce_shipping_classes_column_freight-class', [$this, 'display_freight_class_column']);
        add_action('woocommerce_shipping_classes_save_class', [$this, 'save_shipping_class'], 10, 2);
    }

    /**
     * Change shipping classes data
     *
     * @param  array
     *
     * @return array
     */
    public function get_shipping_classes($classes)
    {
        foreach ($classes as $class) {
            $class->freight_class = version_compare(WC_VERSION, '3.6', 'ge') ? get_term_meta($class->term_id,
                'freight_class', true) : get_woocommerce_term_meta($class->term_id, 'freight_class', true);
            $class->display_freight_class = $class->freight_class ? $this->classes[$class->freight_class] : '-';
        }

        return $classes;
    }

    /**
     * Change columns on shipping clases screen.
     *
     * @param  array
     *
     * @return array
     */
    public function add_shipping_class_column($columns)
    {
        $columns['freight-class'] = __('Freight Class', 'woocommerce-shipping-freight');
        return $columns;
    }

    /**
     * Output html for column
     */
    public function display_freight_class_column()
    {
        ?>
        <div class="view">{{ data.display_freight_class }}</div>
        <div class="edit">
            <select name="freight_class" data-attribute="freight_class" data-value="{{ data.freight_class }}">
                <option value=""><?php
                    esc_html_e('Default', 'woocommerce-shipping-freight'); ?></option>
                <?php
                foreach ($this->classes as $key => $value) : ?>
                    <option value="<?php
                    echo esc_attr($key); ?>"><?php
                        echo esc_html($value); ?></option>
                <?php
                endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Save class during ajax save event.
     */
    public function save_shipping_class($term_id, $data)
    {
        if (!empty($term_id) && isset($data['freight_class'])) {
            // $term_id is an array when add new class and its int
            // when editing the class.
            if (is_array($term_id)) {
                $term_id = $term_id['term_id'];
            }

            update_term_meta($term_id, 'freight_class', sanitize_text_field($data['freight_class']));
        }
    }
}

new WC_Freight_Mapping();
