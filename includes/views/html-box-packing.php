<tr valign="top" id="packing_options">
    <th scope="row" class="titledesc"><?php
        _e('Box Sizes', 'woocommerce-shipping-freight'); ?></th>
    <td class="forminp">
        <style type="text/css">
            .freight_boxes td, .freight_services td {
                vertical-align: middle;
                padding: 4px 7px;
            }

            .freight_services th, .freight_boxes th {
                padding: 9px 7px;
            }

            .freight_boxes td input {
                margin-right: 4px;
            }

            .freight_boxes .check-column {
                vertical-align: middle;
                text-align: left;
                padding: 0 7px;
            }

            .freight_services th.sort {
                width: 16px;
                padding: 0 16px;
            }

            .freight_services td.sort {
                cursor: move;
                width: 16px;
                padding: 0 16px;
                cursor: move;
                background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center;
            }
        </style>
        <table class="freight_boxes widefat">
            <thead>
            <tr>
                <th class="check-column"><input type="checkbox"/></th>
                <th><?php
                    _e('Name', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Length', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Width', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Height', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Weight of Box', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Max Weight', 'woocommerce-shipping-freight'); ?></th>
                <th><?php
                    _e('Enabled', 'woocommerce-shipping-freight'); ?></th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th colspan="3">
                    <a href="#" class="button plus insert"><?php
                        _e('Add Box', 'woocommerce-shipping-freight'); ?></a>
                    <a href="#" class="button minus remove"><?php
                        _e('Remove selected box(es)', 'woocommerce-shipping-freight'); ?></a>
                </th>
                <th colspan="6">
                    <small class="description"><?php
                        _e('Items will be packed into these boxes depending based on item dimensions and volume. Dimensions will be passed to Freight Shipping and used for packing. Items not fitting into boxes will be packed individually.',
                            'woocommerce-shipping-freight'); ?></small>
                </th>
            </tr>
            </tfoot>
            <tbody id="rates">
            <?php
            if ($this->default_boxes) {
                foreach ($this->default_boxes as $key => $box) { ?>
                    <tr>
                        <td class="check-column"></td>
                        <td><?php
                            echo $box['name']; ?></td>
                        <td><input type="text" size="5" readonly value="<?php
                            echo esc_attr($box['length']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" readonly value="<?php
                            echo esc_attr($box['width']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" readonly value="<?php
                            echo esc_attr($box['height']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" readonly value="<?php
                            echo esc_attr($box['box_weight']); ?>"/>lbs
                        </td>
                        <td><input type="text" size="5" readonly value="<?php
                            echo esc_attr($box['max_weight']); ?>"/>lbs
                        </td>
                        <td><input type="checkbox" name="boxes_enabled[<?php
                            echo $box['id']; ?>]" <?php
                            checked(!isset($this->boxes[$box['id']]['enabled']) || $this->boxes[$box['id']]['enabled'] == 1,
                                true); ?> /></td>
                    </tr>
                    <?php
                }
            }
            if ($this->boxes) {
                foreach ($this->boxes as $key => $box) {
                    if (!is_numeric($key)) {
                        continue;
                    } ?>
                    <tr>
                        <td class="check-column"><input type="checkbox"/></td>
                        <td><input type="text" size="10" name="boxes_name[<?php
                            echo $key; ?>]" value="<?php
                            echo isset($box['name']) ? esc_attr($box['name']) : ''; ?>"/></td>
                        <td><input type="text" size="5" name="boxes_length[<?php
                            echo $key; ?>]" value="<?php
                            echo esc_attr($box['length']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" name="boxes_width[<?php
                            echo $key; ?>]" value="<?php
                            echo esc_attr($box['width']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" name="boxes_height[<?php
                            echo $key; ?>]" value="<?php
                            echo esc_attr($box['height']); ?>"/>in
                        </td>
                        <td><input type="text" size="5" name="boxes_box_weight[<?php
                            echo $key; ?>]" value="<?php
                            echo esc_attr($box['box_weight']); ?>"/>lbs
                        </td>
                        <td><input type="text" size="5" name="boxes_max_weight[<?php
                            echo $key; ?>]" value="<?php
                            echo esc_attr($box['max_weight']); ?>"/>lbs
                        </td>
                        <td><input type="checkbox" name="boxes_enabled[<?php
                            echo $key; ?>]" <?php
                            checked($box['enabled'], true); ?> /></td>
                    </tr>
                    <?php
                }
            }
            ?>
            </tbody>
        </table>
        <script type="text/javascript">
            jQuery(window).load(function () {
                jQuery('#woocommerce_freight_packing_method').change(function () {
                    if (jQuery(this).val() === 'box_packing')
                        jQuery('#packing_options').show();
                    else
                        jQuery('#packing_options').hide();
                }).change();

                jQuery('#woocommerce_freight_enabled').change(function () {
                    if (jQuery(this).is(':checked')) {
                        var $table = jQuery('#woocommerce_freight_freight_enabled').closest('table');
                        $table.find('tr:not(:first)').show();
                    } else {
                        var $table = jQuery('#woocommerce_freight_freight_enabled').closest('table');
                        $table.find('tr:not(:first)').hide();
                    }

                }).change();

                jQuery('.freight_boxes .insert').click(function () {
                    var $tbody = jQuery('.freight_boxes').find('tbody');
                    var size = $tbody.find('tr').size();
                    var code = '<tr class="new">\
							<td class="check-column"><input type="checkbox" /></td>\
							<td><input type="text" size="10" name="boxes_name[' + size + ']" /></td>\
							<td><input type="text" size="5" name="boxes_length[' + size + ']" />in</td>\
							<td><input type="text" size="5" name="boxes_width[' + size + ']" />in</td>\
							<td><input type="text" size="5" name="boxes_height[' + size + ']" />in</td>\
							<td><input type="text" size="5" name="boxes_box_weight[' + size + ']" />lbs</td>\
							<td><input type="text" size="5" name="boxes_max_weight[' + size + ']" />lbs</td>\
							<td><input type="checkbox" name="boxes_enabled[' + size + ']" /></td>\
						</tr>';
                    $tbody.append(code);
                    return false;
                });

                jQuery('.freight_boxes .remove').click(function () {
                    var $tbody = jQuery('.freight_boxes').find('tbody');
                    $tbody.find('.check-column input:checked').each(function () {
                        jQuery(this).closest('tr').hide().find('input').val('');
                    });
                    return false;
                });

                // Ordering
                jQuery('.freight_services tbody').sortable({
                    items: 'tr',
                    cursor: 'move',
                    axis: 'y',
                    handle: '.sort',
                    scrollSensitivity: 40,
                    forcePlaceholderSize: true,
                    helper: 'clone',
                    opacity: 0.65,
                    placeholder: 'wc-metabox-sortable-placeholder',
                    start: function (event, ui) {
                        ui.item.css('baclbsround-color', '#f6f6f6');
                    },
                    stop: function (event, ui) {
                        ui.item.removeAttr('style');
                        freight_services_row_indexes();
                    }
                });

                function freight_services_row_indexes() {
                    jQuery('.freight_services tbody tr').each(function (index, el) {
                        jQuery('input.order', el).val(parseInt(jQuery(el).index('.freight_services tr')));
                    });
                }
            });
        </script>
    </td>
</tr>