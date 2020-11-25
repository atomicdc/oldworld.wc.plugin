<tr valign="top" id="service_options">

    <th scope="row" class="titledesc"><?php _e('Services', 'woocommerce-shipping-freight'); ?></th>
    <td class="forminp">

        <table class="freight_services widefat">

            <thead>
                <th class="sort">&nbsp;</th>
                <th><?php _e('Service Code', 'woocommerce-shipping-freight'); ?></th>
                <th><?php _e('Name', 'woocommerce-shipping-freight'); ?></th>
                <th><?php _e('Enabled', 'woocommerce-shipping-freight'); ?></th>
                <th><?= sprintf(__('Price Adjustment (%s)', 'woocommerce-shipping-freight'), get_woocommerce_currency_symbol()); ?></th>
                <th><?php _e('Price Adjustment (%)', 'woocommerce-shipping-freight'); ?></th>
            </thead>

            <tbody><?php
                $sort = 0;
                $this->ordered_services = [];

                foreach ($this->services as $code => $name) {
                    if (isset($this->custom_services[$code]['order'])) {
                        $sort = $this->custom_services[$code]['order'];
                    }

                    while (isset($this->ordered_services[$sort])) {
                        $sort++;
                    }

                    $this->ordered_services[$sort] = [$code, $name];
                    $sort++;
                }

                ksort($this->ordered_services);

                foreach ($this->ordered_services as $value) {
                    $code = $value[0];
                    $name = $value[1];
                    $checked = checked((!isset($this->custom_services[$code]['enabled'])
                        || !empty($this->custom_services[$code]['enabled'])), true); ?>

                    <tr>
                        <td class="sort">
                            <input type="hidden" class="order" name="freight_service[<?= $code; ?>][order]"
                                   value="<?= $this->custom_services[$code]['order'] ?? null; ?>" />
                        </td>

                        <td><strong><?= $code; ?></strong></td>

                        <td>
                            <input type="text" name="freight_service[<?= $code; ?>][name]" placeholder="<?= $name; ?>"
                                   value="<?= $this->custom_services[$code]['name'] ?? null; ?>" size="50" />
                        </td>

                        <td>
                            <input type="checkbox" name="freight_service[<?= $code; ?>][enabled]" <?= $checked; ?> />
                        </td>

                        <td>
                            <input type="text" name="freight_service[<?= $code; ?>][adjustment]" placeholder="N/A"
                                   value="<?= $this->custom_services[$code]['adjustment'] ?? null; ?>" size="4" />
                        </td>

                        <td>
                            <input type="text" name="freight_service[<?= $code; ?>][adjustment_percent]" placeholder="N/A"
                                   value="<?= $this->custom_services[$code]['adjustment_percent'] ?? null; ?>" size="4" />
                        </td>
                    </tr><?php
                } ?>

            </tbody>
        </table>

    </td>
</tr>