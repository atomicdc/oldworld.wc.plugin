<?php

/**
 * Add custom services here if ever offer more than freight or ODFL.
 * The key in the below array should correspond to the API endpoint's
 * expected value for its respective service.
 */

 return [
     'ODFL BLANKET' => [
         'name' => 'OLD DOMINION FREIGHT LINE INC',
         'enabled' => true,
         'adjustment' => 0.00,
         'adjustment_percent' => 0.00
     ],

     'CRATE' => [
         'name' => 'Per Crate, when total weight is greater than 250',
         'enabled' => true,
         'adjustment' => 0.00,
         'adjustment_percent' => 0.00
     ],
 ];

