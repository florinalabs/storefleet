<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Branch Form Handler
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_handle_branch_actions'
);


function storefleet_handle_branch_actions()
{
    global $wp;
    global $wpdb;

    /*
    |--------------------------------------------------------------------------
    | Must Be Dokan Dashboard
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'dokan_is_seller_dashboard'
        )
        ||
        !dokan_is_seller_dashboard()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Must Be Branches Route
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $wp->query_vars[
                'storefleet-branches'
            ]
        )
    ) {
        return;
    }


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
    | Merchant Owner Only
    |--------------------------------------------------------------------------
    */

    if (!storefleet_is_merchant_owner()) {

        wp_die(
            esc_html__(
                'You are not authorized to manage branches.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Action
    |--------------------------------------------------------------------------
    */

    $action =
        isset(
            $_POST[
                'storefleet_branch_action'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_branch_action'
                    ]
                )
            )
            : '';


    if ($action === '') {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Create Branch
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        storefleet_create_branch_action();

        return;
    }
}


/*
|--------------------------------------------------------------------------
| Create Branch
|--------------------------------------------------------------------------
*/

function storefleet_create_branch_action()
{
    global $wpdb;

    check_admin_referer(
        'storefleet_create_branch',
        'storefleet_branch_nonce'
    );


    $merchant_id =
        storefleet_get_current_merchant_id();


    if (!$merchant_id) {

        wp_die(
            esc_html__(
                'Merchant account not found.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Name
    |--------------------------------------------------------------------------
    */

    $name =
        isset($_POST['branch_name'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['branch_name']
                )
            )
            : '';


    if ($name === '') {

        storefleet_redirect_branches(
            'missing-name'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Address
    |--------------------------------------------------------------------------
    */

    $address_line_1 =
        isset($_POST['address_line_1'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['address_line_1']
                )
            )
            : '';


    $address_line_2 =
        isset($_POST['address_line_2'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['address_line_2']
                )
            )
            : '';


    $city =
        isset($_POST['city'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['city']
                )
            )
            : '';


    $state =
        isset($_POST['state'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['state']
                )
            )
            : '';


    $postcode =
        isset($_POST['postcode'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['postcode']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Pickup Contact
    |--------------------------------------------------------------------------
    */

    $contact_name =
        isset($_POST['contact_name'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['contact_name']
                )
            )
            : '';


    $contact_phone =
        isset($_POST['contact_phone'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['contact_phone']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Coordinates
    |--------------------------------------------------------------------------
    */

    $latitude = null;

    if (
        isset($_POST['latitude'])
        &&
        $_POST['latitude'] !== ''
    ) {

        $latitude =
            (float) wp_unslash(
                $_POST['latitude']
            );

        if (
            $latitude < -90
            ||
            $latitude > 90
        ) {
            $latitude = null;
        }
    }


    $longitude = null;

    if (
        isset($_POST['longitude'])
        &&
        $_POST['longitude'] !== ''
    ) {

        $longitude =
            (float) wp_unslash(
                $_POST['longitude']
            );

        if (
            $longitude < -180
            ||
            $longitude > 180
        ) {
            $longitude = null;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    $is_active =
        isset($_POST['is_active'])
            ? 1
            : 0;


    /*
    |--------------------------------------------------------------------------
    | Slug
    |--------------------------------------------------------------------------
    */

    $slug =
        storefleet_generate_branch_slug(
            $merchant_id,
            $name
        );


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    $table =
        storefleet_branches_table();

    $now =
        current_time('mysql');


    $result =
        $wpdb->insert(
            $table,
            [
                'merchant_id' =>
                    $merchant_id,

                'name' =>
                    $name,

                'slug' =>
                    $slug,

                'address_line_1' =>
                    $address_line_1,

                'address_line_2' =>
                    $address_line_2,

                'city' =>
                    $city,

                'state' =>
                    $state,

                'postcode' =>
                    $postcode,

                'country' =>
                    'PH',

                'latitude' =>
                    $latitude,

                'longitude' =>
                    $longitude,

                'contact_name' =>
                    $contact_name,

                'contact_phone' =>
                    $contact_phone,

                'is_active' =>
                    $is_active,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ]
        );


    if ($result === false) {

        error_log(
            'StoreFleet branch insert error: ' .
            $wpdb->last_error
        );

        storefleet_redirect_branches(
            'branch-error'
        );
    }


    storefleet_redirect_branches(
        'branch-created'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
*/

function storefleet_redirect_branches(
    $notice = ''
) {
    $url =
        dokan_get_navigation_url(
            'storefleet-branches'
        );

    if ($notice !== '') {

        $url =
            add_query_arg(
                'sf_notice',
                sanitize_key($notice),
                $url
            );
    }

    wp_safe_redirect($url);

    exit;
}