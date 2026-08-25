<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Delivery Active Branch User Meta
|--------------------------------------------------------------------------
*/

function storefleet_delivery_active_branch_meta_key()
{
    return 'storefleet_active_branch_id';
}


/*
|--------------------------------------------------------------------------
| Current User Is Merchant Owner
|--------------------------------------------------------------------------
*/

function storefleet_delivery_is_merchant_owner()
{
    return
        is_user_logged_in()
        &&
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner();
}


/*
|--------------------------------------------------------------------------
| Current User Is StoreFleet Staff
|--------------------------------------------------------------------------
*/

function storefleet_delivery_is_staff()
{
    return
        is_user_logged_in()
        &&
        function_exists(
            'storefleet_is_staff_user'
        )
        &&
        storefleet_is_staff_user();
}


/*
|--------------------------------------------------------------------------
| Merchant Delivery Branches
|--------------------------------------------------------------------------
|
| Reuses the existing StoreFleet Branch module.
|
*/

function storefleet_get_current_merchant_delivery_branches()
{
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
        ||
        !function_exists(
            'storefleet_get_merchant_branches'
        )
    ) {
        return [];
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    if (!$merchant_id) {
        return [];
    }

    return
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );
}


/*
|--------------------------------------------------------------------------
| Merchant Can Use Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_merchant_can_select_delivery_branch(
    $merchant_id,
    $branch_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$merchant_id
        ||
        !$branch_id
        ||
        !function_exists(
            'storefleet_merchant_owns_branch'
        )
        ||
        !storefleet_merchant_owns_branch(
            $merchant_id,
            $branch_id
        )
        ||
        !function_exists(
            'storefleet_get_branch'
        )
    ) {
        return false;
    }

    $branch =
        storefleet_get_branch(
            $branch_id
        );

    if (!$branch) {
        return false;
    }

    return
        !isset($branch->is_active)
        ||
        (int) $branch->is_active === 1;
}


/*
|--------------------------------------------------------------------------
| Current Merchant Delivery Branch ID
|--------------------------------------------------------------------------
*/

function storefleet_get_current_merchant_delivery_branch_id()
{
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
    ) {
        return 0;
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $user_id =
        get_current_user_id();

    if (
        !$merchant_id
        ||
        !$user_id
    ) {
        return 0;
    }

    $branches =
        storefleet_get_current_merchant_delivery_branches();

    if (empty($branches)) {
        delete_user_meta(
            $user_id,
            storefleet_delivery_active_branch_meta_key()
        );

        return 0;
    }

    $saved_branch_id =
        absint(
            get_user_meta(
                $user_id,
                storefleet_delivery_active_branch_meta_key(),
                true
            )
        );

    if (
        $saved_branch_id
        &&
        storefleet_merchant_can_select_delivery_branch(
            $merchant_id,
            $saved_branch_id
        )
    ) {
        return $saved_branch_id;
    }

    $first_branch =
        reset(
            $branches
        );

    $branch_id =
        absint(
            $first_branch->id
            ?? 0
        );

    if (!$branch_id) {
        return 0;
    }

    update_user_meta(
        $user_id,
        storefleet_delivery_active_branch_meta_key(),
        $branch_id
    );

    return $branch_id;
}


/*
|--------------------------------------------------------------------------
| Current Merchant Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_current_merchant_delivery_branch()
{
    $branch_id =
        storefleet_get_current_merchant_delivery_branch_id();

    if (
        !$branch_id
        ||
        !function_exists(
            'storefleet_get_branch'
        )
    ) {
        return null;
    }

    return
        storefleet_get_branch(
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Set Current Merchant Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_set_current_merchant_delivery_branch(
    $branch_id
) {
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
    ) {
        return false;
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$merchant_id
        ||
        !$branch_id
        ||
        !storefleet_merchant_can_select_delivery_branch(
            $merchant_id,
            $branch_id
        )
    ) {
        return false;
    }

    update_user_meta(
        get_current_user_id(),
        storefleet_delivery_active_branch_meta_key(),
        $branch_id
    );

    return true;
}


/*
|--------------------------------------------------------------------------
| Delivery React URL
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_delivery_react_url()
{
    if (
        function_exists(
            'storefleet_get_dokan_react_dashboard_url'
        )
    ) {
        return
            storefleet_get_dokan_react_dashboard_url(
                'storefleet-delivery'
            );
    }

    if (
        function_exists(
            'dokan_get_navigation_url'
        )
    ) {
        return
            dokan_get_navigation_url(
                'new'
            )
            .
            '#storefleet-delivery';
    }

    return
        home_url(
            '/dashboard/new/#storefleet-delivery'
        );
}


/*
|--------------------------------------------------------------------------
| Current Delivery Permissions
|--------------------------------------------------------------------------
*/

function storefleet_get_current_dokan_delivery_permissions()
{
    $permissions = [
        'branchId' =>
            0,

        'branchName' =>
            '',

        'view' =>
            false,

        'manage' =>
            false,

        'rider' =>
            false,

        'isMerchant' =>
            false,

        'isStaff' =>
            false,

        'branches' =>
            [],

        'branchNonce' =>
            '',
    ];


    /*
    |--------------------------------------------------------------------------
    | Merchant / Vendor Owner
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_delivery_is_merchant_owner()
    ) {
        $branch_id =
            storefleet_get_current_merchant_delivery_branch_id();

        if (!$branch_id) {
            return $permissions;
        }

        $branch =
            storefleet_get_current_merchant_delivery_branch();

        $permissions['branchId'] =
            $branch_id;

        $permissions['branchName'] =
            $branch
                ? sanitize_text_field(
                    $branch->name
                    ?? ''
                )
                : '';

        $permissions['isMerchant'] =
            true;

        $permissions['branchNonce'] =
            wp_create_nonce(
                'storefleet_switch_delivery_branch'
            );


        /*
        |--------------------------------------------------------------------------
        | Merchant Owner Has Full Delivery Access
        |--------------------------------------------------------------------------
        |
        | Branch ownership has already been validated above.
        |
        */

        $permissions['view'] =
            true;

        $permissions['manage'] =
            true;

        $permissions['rider'] =
            false;


        /*
        |--------------------------------------------------------------------------
        | Merchant Branch Selector
        |--------------------------------------------------------------------------
        */

        $branches =
            storefleet_get_current_merchant_delivery_branches();

        foreach (
            $branches as $merchant_branch
        ) {
            $merchant_branch_id =
                absint(
                    $merchant_branch->id
                    ?? 0
                );

            if (!$merchant_branch_id) {
                continue;
            }

            $permissions['branches'][] = [
                'id' =>
                    $merchant_branch_id,

                'name' =>
                    sanitize_text_field(
                        $merchant_branch->name
                        ?? ''
                    ),
            ];
        }

        return $permissions;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_delivery_is_staff()
        ||
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return $permissions;
    }

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (!$branch_id) {
        return $permissions;
    }

    $permissions['branchId'] =
        $branch_id;

    $permissions['isStaff'] =
        true;

    if (
        function_exists(
            'storefleet_get_current_staff_branch'
        )
    ) {
        $branch =
            storefleet_get_current_staff_branch();

        if (
            $branch
            &&
            isset($branch->name)
        ) {
            $permissions['branchName'] =
                sanitize_text_field(
                    $branch->name
                );
        }
    }

    if (
        !function_exists(
            'storefleet_current_staff_can'
        )
    ) {
        return $permissions;
    }

    $permissions['view'] =
        (bool)
        storefleet_current_staff_can(
            'delivery.view',
            $branch_id
        );

    $permissions['manage'] =
        (bool)
        storefleet_current_staff_can(
            'delivery.manage',
            $branch_id
        );

    $permissions['rider'] =
        (bool)
        storefleet_current_staff_can(
            'delivery.rider',
            $branch_id
        );

    return $permissions;
}