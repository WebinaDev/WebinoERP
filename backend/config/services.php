<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'webinoserver' => [
        /*
         | Operators must create WebinoServerManager panel API tokens with at least
         | these Sanctum abilities so ERP can provision sites and manage domains.
         */
        'required_abilities' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WEBINOSERVER_REQUIRED_ABILITIES', 'platform.manage,domains.manage'))
        ))),
    ],

];
