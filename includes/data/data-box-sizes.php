<?php

/**
 * Add additional custom crate sizes and packaging here.
 * The 'id' in the below array should correspond to the API endpoint's
 * expected value for its respective packaging.
 *
 * Note: Accurate weights and dimensions are critical for auto-packaging
 * to function properly.
 */

return [
    [
        'name' => 'Crate',
        'id' => 'CRATE',
        'enabled' => true,
        'max_weight' => 250,
        'box_weight' => 0,
        'length' => 12,
        'width' => 12,
        'height' => 12,
    ]
];
