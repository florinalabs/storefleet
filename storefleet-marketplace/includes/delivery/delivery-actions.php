<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Delivery Form Actions
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_handle_delivery_actions',
    5
);


function storefleet_handle_delivery_actions()
{
    /*
    |--------------------------------------------------------------------------
    | POST Only
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            $_SERVER['REQUEST_METHOD']
            ?? ''
        ) !== 'POST'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Get Action
    |--------------------------------------------------------------------------
    */

    $action =
        isset(
            $_POST[
                'storefleet_delivery_action'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_delivery_action'
                    ]
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Backward Compatible Branch Action
    |--------------------------------------------------------------------------
    |
    | Current React selector already posts this field.
    |
    */

    if (
        $action === ''
        &&
        isset(
            $_POST[
                'storefleet_delivery_branch_action'
            ]
        )
    ) {
        $legacy_action =
            sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_delivery_branch_action'
                    ]
                )
            );

        if (
            $legacy_action ===
            'switch_branch'
        ) {
            $action =
                'switch_branch';
        }
    }


    if ($action === '') {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Switch Merchant Delivery Branch
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'switch_branch'
    ) {
        storefleet_switch_delivery_branch_action();

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Future Delivery Actions
    |--------------------------------------------------------------------------
    |
    | assign
    | dispatch
    | cancel
    | retry
    |
    */
}


/*
|--------------------------------------------------------------------------
| Switch Merchant Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_switch_delivery_branch_action()
{
    /*
    |--------------------------------------------------------------------------
    | Merchant Owner Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_delivery_is_merchant_owner'
        )
        ||
        !storefleet_delivery_is_merchant_owner()
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to switch this StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Nonce
    |--------------------------------------------------------------------------
    */

    check_admin_referer(
        'storefleet_switch_delivery_branch',
        'storefleet_delivery_branch_nonce'
    );


    /*
    |--------------------------------------------------------------------------
    | Branch ID
    |--------------------------------------------------------------------------
    */

    $branch_id =
        isset(
            $_POST[
                'storefleet_branch_id'
            ]
        )
            ? absint(
                wp_unslash(
                    $_POST[
                        'storefleet_branch_id'
                    ]
                )
            )
            : 0;


    if (!$branch_id) {
        storefleet_redirect_delivery();
    }


    /*
    |--------------------------------------------------------------------------
    | Validate And Save
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_set_current_merchant_delivery_branch(
            $branch_id
        )
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to access the selected StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Branch Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    storefleet_redirect_delivery();
}


/*
|--------------------------------------------------------------------------
| Delivery Redirect
|--------------------------------------------------------------------------
*/

function storefleet_redirect_delivery()
{
    wp_safe_redirect(
        storefleet_get_dokan_delivery_react_url()
    );

    exit;
}