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


    /*
    |--------------------------------------------------------------------------
    | Must Be Dokan Dashboard
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'dokan_is_seller_dashboard'
        ) ||
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
            $_SERVER[
                'REQUEST_METHOD'
            ] ?? ''
        ) !== 'POST'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owner Only
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_is_merchant_owner()
    ) {
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
        isset(
            $_POST['branch_name']
        )
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
        isset(
            $_POST['address_line_1']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['address_line_1']
                )
            )
            : '';


    $address_line_2 =
        isset(
            $_POST['address_line_2']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['address_line_2']
                )
            )
            : '';


    $city =
        isset(
            $_POST['city']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['city']
                )
            )
            : '';


    $state =
        isset(
            $_POST['state']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['state']
                )
            )
            : '';


    $postcode =
        isset(
            $_POST['postcode']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['postcode']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Required Address Fields
    |--------------------------------------------------------------------------
    */

    if (
        $address_line_1 === '' ||
        $city === '' ||
        $state === ''
    ) {
        storefleet_redirect_branches(
            'missing-address'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Pickup Contact
    |--------------------------------------------------------------------------
    */

    $contact_name =
        isset(
            $_POST['contact_name']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['contact_name']
                )
            )
            : '';


    $contact_phone =
        isset(
            $_POST['contact_phone']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['contact_phone']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Automatic Geocoding
    |--------------------------------------------------------------------------
    |
    | Latitude and longitude are never trusted from merchant form input.
    |
    */

    $coordinates =
        storefleet_geocode_branch_address(
            array(
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
                    'Philippines',
            )
        );


    if (
        is_wp_error(
            $coordinates
        )
    ) {
        error_log(
            sprintf(
                'StoreFleet branch geocoding failed for merchant #%d: %s',
                $merchant_id,
                $coordinates->get_error_message()
            )
        );


        storefleet_redirect_branches(
            'geocode-failed'
        );
    }


    $latitude =
        (float)
        $coordinates['latitude'];


    $longitude =
        (float)
        $coordinates['longitude'];


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    $is_active =
        isset(
            $_POST['is_active']
        )
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
        current_time(
            'mysql'
        );


    $result =
        $wpdb->insert(
            $table,
            array(
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
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%f',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            )
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
                sanitize_key(
                    $notice
                ),
                $url
            );
    }


    wp_safe_redirect(
        $url
    );


    exit;
}