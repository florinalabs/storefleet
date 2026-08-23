<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Frontend Assets
|--------------------------------------------------------------------------
*/

add_action(
    'wp_enqueue_scripts',
    function () {

        if (!is_user_logged_in()) {
            return;
        }

        if (
            !function_exists('dokan_is_seller_dashboard')
            ||
            !dokan_is_seller_dashboard()
        ) {
            return;
        }

        wp_enqueue_style(
            'storefleet-dashboard',
            STOREFLEET_URL .
                'assets/css/storefleet-dashboard.css',
            [],
            STOREFLEET_VERSION
        );
    }
);