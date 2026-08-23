<?php

if (!defined('ABSPATH')) {
    exit;
}

function storefleet_is_merchant_owner()
{
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();

    return in_array(
        'seller',
        (array) $user->roles,
        true
    );
}

function storefleet_get_current_merchant_id()
{
    if (!storefleet_is_merchant_owner()) {
        return 0;
    }

    return get_current_user_id();
}