<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Staff Actions
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_handle_staff_actions'
);


function storefleet_handle_staff_actions()
{
    global $wp;

    /*
    |--------------------------------------------------------------------------
    | Dokan Dashboard Only
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
    | Staff Route Only
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $wp->query_vars[
                'storefleet-staff'
            ]
        )
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | POST Requests Only
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
                'You are not authorized to manage staff.',
                'storefleet-marketplace'
            )
        );
    }


    $action =
        isset(
            $_POST[
                'storefleet_staff_action'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_staff_action'
                    ]
                )
            )
            : '';


    if ($action === 'create') {

        storefleet_create_staff_action();

        return;
    }
}


/*
|--------------------------------------------------------------------------
| Create Staff
|--------------------------------------------------------------------------
*/

function storefleet_create_staff_action()
{
    global $wpdb;

    check_admin_referer(
        'storefleet_create_staff',
        'storefleet_staff_nonce'
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
    | Basic Information
    |--------------------------------------------------------------------------
    */

    $first_name =
        isset($_POST['first_name'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['first_name']
                )
            )
            : '';


    $last_name =
        isset($_POST['last_name'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['last_name']
                )
            )
            : '';


    $email =
        isset($_POST['email'])
            ? sanitize_email(
                wp_unslash(
                    $_POST['email']
                )
            )
            : '';


    $password =
        isset($_POST['password'])
            ? (string) wp_unslash(
                $_POST['password']
            )
            : '';


    $staff_role =
        isset($_POST['staff_role'])
            ? sanitize_key(
                wp_unslash(
                    $_POST['staff_role']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {

        storefleet_redirect_staff(
            'missing-name'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if (
        $email === ''
        ||
        !is_email($email)
    ) {

        storefleet_redirect_staff(
            'invalid-email'
        );
    }


    if (email_exists($email)) {

        storefleet_redirect_staff(
            'email-exists'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if (strlen($password) < 10) {

        storefleet_redirect_staff(
            'weak-password'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Role
    |--------------------------------------------------------------------------
    */

    $allowed_roles =
        storefleet_get_staff_roles();


    if (
        !isset(
            $allowed_roles[
                $staff_role
            ]
        )
    ) {

        storefleet_redirect_staff(
            'invalid-role'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Assignments
    |--------------------------------------------------------------------------
    */

    $branch_ids = [];

    if (
        isset($_POST['branch_ids'])
        &&
        is_array($_POST['branch_ids'])
    ) {

        $branch_ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'absint',
                            wp_unslash(
                                $_POST['branch_ids']
                            )
                        )
                    )
                )
            );
    }


    if (empty($branch_ids)) {

        storefleet_redirect_staff(
            'no-branch'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Merchant Owns Every Selected Branch
    |--------------------------------------------------------------------------
    */

    foreach ($branch_ids as $branch_id) {

        if (
            !storefleet_merchant_owns_branch(
                $merchant_id,
                $branch_id
            )
        ) {

            storefleet_redirect_staff(
                'invalid-branch'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Username
    |--------------------------------------------------------------------------
    */

    $username =
        storefleet_generate_staff_username(
            $email,
            $first_name,
            $last_name
        );


    $display_name =
        trim(
            $first_name .
            ' ' .
            $last_name
        );


    /*
    |--------------------------------------------------------------------------
    | Create WordPress User
    |--------------------------------------------------------------------------
    */

    $user_id =
        wp_insert_user(
            [
                'user_login' =>
                    $username,

                'user_pass' =>
                    $password,

                'user_email' =>
                    $email,

                'first_name' =>
                    $first_name,

                'last_name' =>
                    $last_name,

                'display_name' =>
                    $display_name,

                'role' =>
                    'storefleet_staff',
            ]
        );


    if (is_wp_error($user_id)) {

        error_log(
            'StoreFleet staff WP user creation failed: ' .
            $user_id->get_error_message()
        );

        storefleet_redirect_staff(
            'user-error'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create StoreFleet Staff Record
    |--------------------------------------------------------------------------
    */

    $staff_table =
        storefleet_staff_table();

    $now =
        current_time('mysql');


    $result =
        $wpdb->insert(
            $staff_table,
            [
                'merchant_id' =>
                    $merchant_id,

                'user_id' =>
                    $user_id,

                'staff_role' =>
                    $staff_role,

                'is_active' =>
                    1,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ]
        );


    if ($result === false) {

        error_log(
            'StoreFleet staff insert failed: ' .
            $wpdb->last_error
        );

        storefleet_delete_failed_staff_user(
            $user_id
        );

        storefleet_redirect_staff(
            'staff-error'
        );
    }


    $staff_id =
        absint(
            $wpdb->insert_id
        );


    /*
    |--------------------------------------------------------------------------
    | Save Branch Assignments
    |--------------------------------------------------------------------------
    */

    $assignments_table =
        storefleet_staff_branches_table();


    foreach ($branch_ids as $branch_id) {

        $assignment_result =
            $wpdb->insert(
                $assignments_table,
                [
                    'staff_id' =>
                        $staff_id,

                    'branch_id' =>
                        $branch_id,
                ]
            );


        if ($assignment_result === false) {

            error_log(
                'StoreFleet staff branch assignment failed: ' .
                $wpdb->last_error
            );

            storefleet_cleanup_failed_staff(
                $staff_id,
                $user_id
            );

            storefleet_redirect_staff(
                'staff-error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Helpful User Metadata
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        'storefleet_staff_id',
        $staff_id
    );

    update_user_meta(
        $user_id,
        'storefleet_merchant_id',
        $merchant_id
    );


    storefleet_redirect_staff(
        'staff-created'
    );
}


/*
|--------------------------------------------------------------------------
| Clean Up Failed Staff Creation
|--------------------------------------------------------------------------
*/

function storefleet_cleanup_failed_staff(
    $staff_id,
    $user_id
) {
    global $wpdb;

    $staff_id = absint($staff_id);

    if ($staff_id) {

        $wpdb->delete(
            storefleet_staff_branches_table(),
            [
                'staff_id' =>
                    $staff_id,
            ]
        );

        $wpdb->delete(
            storefleet_staff_table(),
            [
                'id' =>
                    $staff_id,
            ]
        );
    }

    storefleet_delete_failed_staff_user(
        $user_id
    );
}


/*
|--------------------------------------------------------------------------
| Remove Failed WordPress User
|--------------------------------------------------------------------------
*/

function storefleet_delete_failed_staff_user(
    $user_id
) {
    $user_id = absint($user_id);

    if (!$user_id) {
        return;
    }

    require_once ABSPATH .
        'wp-admin/includes/user.php';

    wp_delete_user($user_id);
}


/*
|--------------------------------------------------------------------------
| Redirect Staff Page
|--------------------------------------------------------------------------
*/

function storefleet_redirect_staff(
    $notice = ''
) {
    $url =
        dokan_get_navigation_url(
            'storefleet-staff'
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