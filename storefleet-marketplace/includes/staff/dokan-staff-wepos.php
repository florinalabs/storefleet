<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Staff ↔ wePOS Integration
|--------------------------------------------------------------------------
|
| Feature #30
|
| StoreFleet employees remain:
|
| WordPress role:
|   storefleet_staff
|
| They do NOT become:
|
|   vendor
|   seller
|   vendor_staff role
|   dokandar
|
| wePOS normally detects Dokan staff by checking the WordPress role
| "vendor_staff".
|
| StoreFleet instead bridges its own staff authorization into wePOS using:
|
| - wepos_is_vendor_staff
| - access_wepos
| - _vendor_id
|
| StoreFleet remains the source of truth for:
|
| - staff status
| - merchant ownership
| - StoreFleet role
| - pos.use permission
| - active StoreFleet branch
|
*/


/*
|--------------------------------------------------------------------------
| Get Real wePOS Frontend URL
|--------------------------------------------------------------------------
*/

function storefleet_get_wepos_frontend_url()
{
    return
        untrailingslashit(
            get_site_url()
        ) .
        '/wepos/#';
}


/*
|--------------------------------------------------------------------------
| Is StoreFleet Staff Eligible For POS
|--------------------------------------------------------------------------
|
| POS requires:
|
| 1. StoreFleet staff account
| 2. active staff
| 3. active StoreFleet branch
| 4. pos.use granted for THAT branch
|
*/

function storefleet_staff_can_access_wepos(
    $user_id = 0
) {
    $user_id =
        $user_id
            ? absint(
                $user_id
            )
            : get_current_user_id();

    if (!$user_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff WordPress Role
    |--------------------------------------------------------------------------
    */

    $user =
        get_userdata(
            $user_id
        );

    if (
        !($user instanceof WP_User)
        ||
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Record
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

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Logged-In User Only
    |--------------------------------------------------------------------------
    |
    | Branch context belongs to the current staff dashboard session.
    |
    */

    if (
        $user_id !==
        get_current_user_id()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Active StoreFleet Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return false;
    }

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet POS Permission + Branch
    |--------------------------------------------------------------------------
    |
    | SAME role must grant:
    |
    | pos.use
    |
    | and access to the current branch.
    |
    */

    if (
        !function_exists(
            'storefleet_staff_has_permission'
        )
    ) {
        return false;
    }

    return
        storefleet_staff_has_permission(
            $staff,
            'pos.use',
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Tell wePOS StoreFleet Staff Is Vendor Staff
|--------------------------------------------------------------------------
|
| wePOS's own Dokan integration checks:
|
| apply_filters(
|     'wepos_is_vendor_staff',
|     false
| )
|
| Its default callback checks specifically for the "vendor_staff" role.
|
| StoreFleet deliberately uses "storefleet_staff", so we extend that check
| here without changing the actual WordPress role.
|
*/

add_filter(
    'wepos_is_vendor_staff',
    'storefleet_wepos_is_vendor_staff',
    100,
    2
);


function storefleet_wepos_is_vendor_staff(
    $is_staff,
    $user_id = null
) {
    $user_id =
        $user_id
            ? absint(
                $user_id
            )
            : get_current_user_id();

    if (!$user_id) {
        return $is_staff;
    }

    $user =
        get_userdata(
            $user_id
        );

    if (
        !($user instanceof WP_User)
        ||
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $is_staff;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Controls Authorization
    |--------------------------------------------------------------------------
    */

    return
        storefleet_staff_can_access_wepos(
            $user_id
        );
}


/*
|--------------------------------------------------------------------------
| Dynamically Grant access_wepos
|--------------------------------------------------------------------------
|
| Do NOT permanently grant broad WooCommerce / Dokan capabilities.
|
| access_wepos is made available dynamically only when StoreFleet says:
|
| pos.use + current branch = allowed
|
| Priority 15 is intentional.
|
| wePOS itself applies its vendor → staff capability cascade later at
| priority 20. Therefore if the merchant/vendor has had POS access revoked,
| wePOS can still remove the capability afterward.
|
*/

add_filter(
    'user_has_cap',
    'storefleet_wepos_dynamic_access_capability',
    15,
    4
);


function storefleet_wepos_dynamic_access_capability(
    $allcaps,
    $caps,
    $args,
    $user
) {
    if (
        !($user instanceof WP_User)
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Handle access_wepos Checks
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'access_wepos',
            (array) $caps,
            true
        )
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | Grant / Deny From StoreFleet Permission Engine
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_staff_can_access_wepos(
            $user->ID
        )
    ) {
        $allcaps[
            'access_wepos'
        ] = true;
    } else {
        $allcaps[
            'access_wepos'
        ] = false;
    }

    return $allcaps;
}


/*
|--------------------------------------------------------------------------
| Replace StoreFleet POS Menu With Real wePOS
|--------------------------------------------------------------------------
|
| Our StoreFleet navigation filter runs at priority 9999.
|
| This filter runs afterward and replaces:
|
| StoreFleet POS placeholder
|
| with:
|
| real wePOS frontend
|
*/

add_filter(
    'dokan_get_dashboard_nav',
    'storefleet_replace_staff_pos_with_wepos',
    10000
);


function storefleet_replace_staff_pos_with_wepos(
    $navigation
) {
    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return $navigation;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove StoreFleet Placeholder POS
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $navigation[
                'storefleet-pos'
            ]
        )
    ) {
        unset(
            $navigation[
                'storefleet-pos'
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | No POS Permission For Current Branch
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_wepos()
    ) {
        return $navigation;
    }


    /*
    |--------------------------------------------------------------------------
    | Add Real wePOS Menu
    |--------------------------------------------------------------------------
    */

    $navigation[
        'storefleet-wepos'
    ] = [
        'title' =>
            'wePOS',

        'icon' =>
            '<i class="fas fa-cash-register"></i>',

        'url' =>
            storefleet_get_wepos_frontend_url(),

        'pos' =>
            20,

        'submenu' => [
            'storefleet-view-pos' => [
                'title' =>
                    'View POS',

                'icon' =>
                    '<i class="fas fa-desktop"></i>',

                'url' =>
                    storefleet_get_wepos_frontend_url(),

                'pos' =>
                    10,

                'target' =>
                    '_blank',
            ],
        ],
    ];


    /*
    |--------------------------------------------------------------------------
    | Preserve Navigation Ordering
    |--------------------------------------------------------------------------
    */

    uasort(
        $navigation,
        function (
            $item_a,
            $item_b
        ) {
            return
                absint(
                    $item_a['pos']
                    ?? 999
                )
                <=>
                absint(
                    $item_b['pos']
                    ?? 999
                );
        }
    );

    return $navigation;
}


/*
|--------------------------------------------------------------------------
| Redirect Old StoreFleet POS Placeholder
|--------------------------------------------------------------------------
|
| Existing links may still point to:
|
| /dashboard/storefleet-pos/
|
| Redirect authorized employees to the real wePOS application.
|
*/

add_action(
    'template_redirect',
    'storefleet_redirect_staff_pos_to_wepos',
    12
);


function storefleet_redirect_staff_pos_to_wepos()
{
    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }

    if (
        !function_exists(
            'storefleet_get_current_dokan_staff_route'
        )
    ) {
        return;
    }

    $route =
        storefleet_get_current_dokan_staff_route();

    if (
        $route !==
        'storefleet-pos'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Server-Side StoreFleet Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_wepos()
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to use StoreFleet POS for the selected branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet POS Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Real POS
    |--------------------------------------------------------------------------
    */

    wp_safe_redirect(
        storefleet_get_wepos_frontend_url()
    );

    exit;
}