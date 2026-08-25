<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Staff Integration
|--------------------------------------------------------------------------
|
| StoreFleet staff remain WordPress users with the:
|
| storefleet_staff
|
| role.
|
| They are NOT Dokan vendors.
|
| This integration connects a StoreFleet employee to their owning Dokan
| merchant so the employee can operate inside the Dokan frontend dashboard.
|
| Dokan context:
|
| vendor_staff capability
| +
| _vendor_id = merchant owner WordPress user ID
|
| StoreFleet remains the source of truth for:
|
| - staff identity
| - merchant ownership
| - active/suspended status
| - StoreFleet roles
| - permissions
| - role-specific branch access
|
*/


/*
|--------------------------------------------------------------------------
| Dokan Dashboard URL
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_staff_dashboard_url()
{
    if (
        function_exists(
            'dokan_get_page_url'
        )
    ) {
        $url =
            dokan_get_page_url(
                'dashboard',
                'dokan_pages'
            );

        if (!empty($url)) {
            return $url;
        }
    }

    return home_url(
        '/dashboard/'
    );
}


/*
|--------------------------------------------------------------------------
| Is StoreFleet Staff WordPress User
|--------------------------------------------------------------------------
*/

function storefleet_is_storefleet_staff_wp_user(
    $user_id
) {
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        return false;
    }

    $user =
        get_userdata(
            $user_id
        );

    if (
        !($user instanceof WP_User)
    ) {
        return false;
    }

    return in_array(
        'storefleet_staff',
        (array) $user->roles,
        true
    );
}


/*
|--------------------------------------------------------------------------
| Resolve StoreFleet Staff Merchant
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_dokan_merchant_id(
    $user_id
) {
    $user_id =
        absint(
            $user_id
        );

    if (
        !$user_id
        ||
        !function_exists(
            'storefleet_get_staff_by_user_id'
        )
    ) {
        return 0;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user_id
        );

    if (!$staff) {
        return 0;
    }

    if (
        (int) $staff->is_active
        !== 1
    ) {
        return 0;
    }

    return absint(
        $staff->merchant_id
    );
}


/*
|--------------------------------------------------------------------------
| Validate Dokan Merchant Owner
|--------------------------------------------------------------------------
*/

function storefleet_is_valid_dokan_staff_merchant(
    $merchant_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );

    if (!$merchant_id) {
        return false;
    }

    $merchant =
        get_userdata(
            $merchant_id
        );

    if (
        !($merchant instanceof WP_User)
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Prefer Dokan's Seller Check
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'dokan_is_user_seller'
        )
    ) {
        return (bool)
            dokan_is_user_seller(
                $merchant_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Compatibility Fallback
    |--------------------------------------------------------------------------
    */

    return user_can(
        $merchant_id,
        'dokandar'
    );
}


/*
|--------------------------------------------------------------------------
| Synchronize Staff With Dokan Merchant Context
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We intentionally add:
|
| vendor_staff
|
| We intentionally DO NOT add:
|
| dokandar
|
| Therefore the StoreFleet employee can be recognized as staff associated
| with a vendor while remaining separate from the actual Dokan seller.
|
*/

function storefleet_sync_staff_dokan_context(
    $user_id
) {
    $user_id =
        absint(
            $user_id
        );

    if (
        !$user_id
        ||
        !storefleet_is_storefleet_staff_wp_user(
            $user_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve StoreFleet Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_staff_by_user_id'
        )
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user_id
        );

    if (!$staff) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff Required
    |--------------------------------------------------------------------------
    */

    if (
        (int) $staff->is_active
        !== 1
    ) {
        storefleet_remove_staff_dokan_context(
            $user_id
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Merchant
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        absint(
            $staff->merchant_id
        );

    if (
        !$merchant_id
        ||
        !storefleet_is_valid_dokan_staff_merchant(
            $merchant_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress User
    |--------------------------------------------------------------------------
    */

    $user =
        get_userdata(
            $user_id
        );

    if (
        !($user instanceof WP_User)
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan Staff Capability
    |--------------------------------------------------------------------------
    |
    | This allows Dokan to recognize the employee as vendor staff.
    |
    | Do NOT grant dokandar.
    |
    */

    if (
        !$user->has_cap(
            'vendor_staff'
        )
    ) {
        $user->add_cap(
            'vendor_staff'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan Vendor Relationship
    |--------------------------------------------------------------------------
    |
    | StoreFleet controls this value server-side.
    |
    */

    update_user_meta(
        $user_id,
        '_vendor_id',
        $merchant_id
    );


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Merchant Meta
    |--------------------------------------------------------------------------
    |
    | Keep the existing StoreFleet ownership metadata synchronized.
    |
    */

    update_user_meta(
        $user_id,
        'storefleet_merchant_id',
        $merchant_id
    );


    /*
    |--------------------------------------------------------------------------
    | Never Turn Staff Into Dokan Sellers
    |--------------------------------------------------------------------------
    |
    | We deliberately do NOT:
    |
    | - assign seller role
    | - add dokandar
    | - enable StoreFleet staff as independent vendors
    |
    */

    return true;
}


/*
|--------------------------------------------------------------------------
| Remove Dokan Staff Context
|--------------------------------------------------------------------------
*/

function storefleet_remove_staff_dokan_context(
    $user_id
) {
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        return;
    }

    $user =
        get_userdata(
            $user_id
        );

    if (
        $user instanceof WP_User
    ) {
        $user->remove_cap(
            'vendor_staff'
        );
    }

    delete_user_meta(
        $user_id,
        '_vendor_id'
    );
}


/*
|--------------------------------------------------------------------------
| Synchronize Staff When They Log In
|--------------------------------------------------------------------------
|
| wp_login runs before the final login redirect, allowing Dokan merchant
| context to exist before the employee reaches /dashboard/.
|
*/

add_action(
    'wp_login',
    'storefleet_sync_dokan_context_on_staff_login',
    20,
    2
);


function storefleet_sync_dokan_context_on_staff_login(
    $user_login,
    $user
) {
    if (
        !($user instanceof WP_User)
    ) {
        return;
    }

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return;
    }

    storefleet_sync_staff_dokan_context(
        $user->ID
    );
}


/*
|--------------------------------------------------------------------------
| Keep Current Staff Dokan Context Synchronized
|--------------------------------------------------------------------------
|
| This also upgrades existing StoreFleet staff accounts automatically.
|
| Existing employees do not need to be deleted/recreated.
|
*/

add_action(
    'init',
    'storefleet_sync_current_staff_dokan_context',
    20
);


function storefleet_sync_current_staff_dokan_context()
{
    if (!is_user_logged_in()) {
        return;
    }

    $user_id =
        get_current_user_id();

    if (
        !storefleet_is_storefleet_staff_wp_user(
            $user_id
        )
    ) {
        return;
    }

    storefleet_sync_staff_dokan_context(
        $user_id
    );
}


/*
|--------------------------------------------------------------------------
| Staff Login Redirect
|--------------------------------------------------------------------------
|
| staff-portal.php currently sends StoreFleet employees to /staff/.
|
| This filter runs later and changes the final destination to the Dokan
| frontend dashboard for Feature #30.
|
*/

add_filter(
    'login_redirect',
    'storefleet_dokan_staff_login_redirect',
    50,
    3
);


function storefleet_dokan_staff_login_redirect(
    $redirect_to,
    $requested_redirect_to,
    $user
) {
    if (
        !($user instanceof WP_User)
    ) {
        return $redirect_to;
    }

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $redirect_to;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user->ID
        );

    if (
        !$staff
        ||
        (int) $staff->is_active
            !== 1
    ) {
        return $redirect_to;
    }

    storefleet_sync_staff_dokan_context(
        $user->ID
    );

    return
        storefleet_get_dokan_staff_dashboard_url();
}


/*
|--------------------------------------------------------------------------
| Legacy /staff/ Redirect
|--------------------------------------------------------------------------
|
| Issue #28 introduced /staff/.
|
| Feature #30 makes Dokan the staff frontend, therefore /staff/ becomes a
| compatibility entry point.
|
| /staff/
|     ↓
| /dashboard/
|
*/

add_action(
    'template_redirect',
    'storefleet_redirect_staff_portal_to_dokan',
    1
);


function storefleet_redirect_staff_portal_to_dokan()
{
    if (
        !get_query_var(
            'storefleet_staff_portal'
        )
    ) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active
            !== 1
    ) {
        return;
    }

    storefleet_sync_staff_dokan_context(
        get_current_user_id()
    );

    wp_safe_redirect(
        storefleet_get_dokan_staff_dashboard_url()
    );

    exit;
}