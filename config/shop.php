<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default User Password
    |--------------------------------------------------------------------------
    |
    | Pre-filled when staff create a user from the admin panel. Override it
    | per environment with SHOP_DEFAULT_USER_PASSWORD.
    |
    */

    'default_user_password' => env('SHOP_DEFAULT_USER_PASSWORD', 'password'),

];
