<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {

    add_menu_page(
        'StoreFleet',
        'StoreFleet',
        'manage_options',
        'storefleet',
        'storefleet_admin_dashboard',
        'dashicons-store',
        2
    );

    add_submenu_page(
        'storefleet',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'storefleet',
        'storefleet_admin_dashboard'
    );

    add_submenu_page(
        'storefleet',
        'Pricing',
        'Pricing',
        'manage_options',
        'storefleet-pricing',
        'storefleet_pricing_page'
    );
});