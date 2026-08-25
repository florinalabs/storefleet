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

    if (
        !function_exists('dokan_is_seller_dashboard')
        ||
        !dokan_is_seller_dashboard()
    ) {
        return;
    }

    if (
        !isset(
            $wp->query_vars['storefleet-staff']
        )
    ) {
        return;
    }

    if (
        strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? ''
        ) !== 'POST'
    ) {
        return;
    }

    if (!storefleet_is_merchant_owner()) {
        wp_die(
            esc_html__(
                'You are not authorized to manage staff.',
                'storefleet-marketplace'
            )
        );
    }

    $action =
        isset($_POST['storefleet_staff_action'])
            ? sanitize_key(
                wp_unslash(
                    $_POST['storefleet_staff_action']
                )
            )
            : '';

    switch ($action) {

        case 'create':
            storefleet_create_staff_action();
            break;

        case 'update':
            storefleet_update_staff_action();
            break;

        case 'suspend':
            storefleet_suspend_staff_action();
            break;

        case 'reactivate':
            storefleet_reactivate_staff_action();
            break;
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
        absint(
            storefleet_get_current_merchant_id()
        );

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
    | Personal Information
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

    $phone =
        isset($_POST['phone'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['phone']
                )
            )
            : '';

    $password =
        isset($_POST['password'])
            ? (string) wp_unslash(
                $_POST['password']
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Validate Personal Information
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {
        storefleet_redirect_staff(
            'missing-name'
        );
    }

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

    if ($phone === '') {
        storefleet_redirect_staff(
            'missing-phone'
        );
    }

    if (strlen($password) < 10) {
        storefleet_redirect_staff(
            'weak-password'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Roles + Branch Access
    |--------------------------------------------------------------------------
    */

    $role_assignments =
        storefleet_get_posted_staff_role_assignments();

    $validation =
        storefleet_validate_staff_role_assignments(
            $merchant_id,
            $role_assignments
        );

    if (is_wp_error($validation)) {
        storefleet_redirect_staff(
            $validation->get_error_code()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Primary Legacy Role
    |--------------------------------------------------------------------------
    |
    | staff.staff_role remains temporarily for compatibility.
    |
    */

    $primary_role =
        storefleet_get_primary_role_from_assignments(
            $role_assignments
        );

    if ($primary_role === '') {
        storefleet_redirect_staff(
            'invalid-role'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress User
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

    if ($display_name === '') {
        $display_name =
            $username;
    }

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

    $user_id =
        absint(
            $user_id
        );


    /*
    |--------------------------------------------------------------------------
    | Staff Record
    |--------------------------------------------------------------------------
    */

    $now =
        current_time(
            'mysql'
        );

    $result =
        $wpdb->insert(
            storefleet_staff_table(),
            [
                'merchant_id' =>
                    $merchant_id,

                'user_id' =>
                    $user_id,

                /*
                |--------------------------------------------------------------------------
                | Legacy Compatibility
                |--------------------------------------------------------------------------
                */

                'staff_role' =>
                    $primary_role,

                'is_active' =>
                    1,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
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
    | Save Multi-Role Assignments
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_replace_staff_role_assignments(
            $staff_id,
            $merchant_id,
            $role_assignments
        )
    ) {
        storefleet_cleanup_failed_staff(
            $staff_id,
            $user_id
        );

        storefleet_redirect_staff(
            'staff-error'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | User Metadata
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

    update_user_meta(
        $user_id,
        'storefleet_staff_phone',
        $phone
    );


    storefleet_redirect_staff(
        'staff-created'
    );
}


/*
|--------------------------------------------------------------------------
| Update Staff
|--------------------------------------------------------------------------
*/

function storefleet_update_staff_action()
{
    global $wpdb;

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $staff_id =
        isset($_POST['staff_id'])
            ? absint(
                wp_unslash(
                    $_POST['staff_id']
                )
            )
            : 0;

    if (
        !$merchant_id
        ||
        !$staff_id
        ||
        !storefleet_merchant_owns_staff(
            $merchant_id,
            $staff_id
        )
    ) {
        storefleet_redirect_staff(
            'invalid-staff'
        );
    }

    check_admin_referer(
        'storefleet_update_staff_' .
        $staff_id,
        'storefleet_staff_nonce'
    );

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        storefleet_redirect_staff(
            'invalid-staff'
        );
    }

    $user_id =
        absint(
            $staff->user_id
        );


    /*
    |--------------------------------------------------------------------------
    | Personal Information
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

    $phone =
        isset($_POST['phone'])
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['phone']
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Validate Personal Information
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {
        storefleet_redirect_staff(
            'missing-name',
            $staff_id
        );
    }

    if (
        $email === ''
        ||
        !is_email($email)
    ) {
        storefleet_redirect_staff(
            'invalid-email',
            $staff_id
        );
    }

    $email_user_id =
        email_exists(
            $email
        );

    if (
        $email_user_id
        &&
        absint($email_user_id)
            !== $user_id
    ) {
        storefleet_redirect_staff(
            'email-exists',
            $staff_id
        );
    }

    if ($phone === '') {
        storefleet_redirect_staff(
            'missing-phone',
            $staff_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Roles + Branch Access
    |--------------------------------------------------------------------------
    */

    $role_assignments =
        storefleet_get_posted_staff_role_assignments();

    $validation =
        storefleet_validate_staff_role_assignments(
            $merchant_id,
            $role_assignments
        );

    if (is_wp_error($validation)) {

        storefleet_redirect_staff(
            $validation->get_error_code(),
            $staff_id
        );
    }

    $primary_role =
        storefleet_get_primary_role_from_assignments(
            $role_assignments
        );

    if ($primary_role === '') {
        storefleet_redirect_staff(
            'invalid-role',
            $staff_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update WordPress User
    |--------------------------------------------------------------------------
    */

    $display_name =
        trim(
            $first_name .
            ' ' .
            $last_name
        );

    if ($display_name === '') {
        $display_name =
            $staff->user_login;
    }

    $user_result =
        wp_update_user(
            [
                'ID' =>
                    $user_id,

                'user_email' =>
                    $email,

                'first_name' =>
                    $first_name,

                'last_name' =>
                    $last_name,

                'display_name' =>
                    $display_name,
            ]
        );

    if (is_wp_error($user_result)) {

        error_log(
            'StoreFleet staff WP user update failed: ' .
            $user_result->get_error_message()
        );

        storefleet_redirect_staff(
            'update-error',
            $staff_id
        );
    }

    update_user_meta(
        $user_id,
        'storefleet_staff_phone',
        $phone
    );


    /*
    |--------------------------------------------------------------------------
    | Keep Legacy Primary Role Synchronized
    |--------------------------------------------------------------------------
    */

    $staff_result =
        $wpdb->update(
            storefleet_staff_table(),
            [
                'staff_role' =>
                    $primary_role,

                'updated_at' =>
                    current_time(
                        'mysql'
                    ),
            ],
            [
                'id' =>
                    $staff_id,

                'merchant_id' =>
                    $merchant_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

    if ($staff_result === false) {

        error_log(
            'StoreFleet staff update failed: ' .
            $wpdb->last_error
        );

        storefleet_redirect_staff(
            'update-error',
            $staff_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Replace Multi-Role Assignments
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_replace_staff_role_assignments(
            $staff_id,
            $merchant_id,
            $role_assignments
        )
    ) {
        storefleet_redirect_staff(
            'update-error',
            $staff_id
        );
    }


    storefleet_redirect_staff(
        'staff-updated'
    );
}


/*
|--------------------------------------------------------------------------
| Suspend Staff
|--------------------------------------------------------------------------
*/

function storefleet_suspend_staff_action()
{
    global $wpdb;

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $staff_id =
        isset($_POST['staff_id'])
            ? absint(
                wp_unslash(
                    $_POST['staff_id']
                )
            )
            : 0;

    if (
        !$staff_id
        ||
        !storefleet_merchant_owns_staff(
            $merchant_id,
            $staff_id
        )
    ) {
        storefleet_redirect_staff(
            'invalid-staff'
        );
    }

    check_admin_referer(
        'storefleet_suspend_staff_' .
        $staff_id,
        'storefleet_staff_nonce'
    );

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        storefleet_redirect_staff(
            'invalid-staff'
        );
    }

    $result =
        $wpdb->update(
            storefleet_staff_table(),
            [
                'is_active' =>
                    0,

                'updated_at' =>
                    current_time(
                        'mysql'
                    ),
            ],
            [
                'id' =>
                    $staff_id,

                'merchant_id' =>
                    $merchant_id,
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

    if ($result === false) {
        storefleet_redirect_staff(
            'update-error'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Destroy Existing Sessions Immediately
    |--------------------------------------------------------------------------
    */

    $user_id =
        absint(
            $staff->user_id
        );

    if ($user_id) {

        $sessions =
            WP_Session_Tokens::get_instance(
                $user_id
            );

        $sessions->destroy_all();
    }

    storefleet_redirect_staff(
        'staff-suspended'
    );
}


/*
|--------------------------------------------------------------------------
| Reactivate Staff
|--------------------------------------------------------------------------
*/

function storefleet_reactivate_staff_action()
{
    global $wpdb;

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $staff_id =
        isset($_POST['staff_id'])
            ? absint(
                wp_unslash(
                    $_POST['staff_id']
                )
            )
            : 0;

    if (
        !$staff_id
        ||
        !storefleet_merchant_owns_staff(
            $merchant_id,
            $staff_id
        )
    ) {
        storefleet_redirect_staff(
            'invalid-staff'
        );
    }

    check_admin_referer(
        'storefleet_reactivate_staff_' .
        $staff_id,
        'storefleet_staff_nonce'
    );

    $result =
        $wpdb->update(
            storefleet_staff_table(),
            [
                'is_active' =>
                    1,

                'updated_at' =>
                    current_time(
                        'mysql'
                    ),
            ],
            [
                'id' =>
                    $staff_id,

                'merchant_id' =>
                    $merchant_id,
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

    if ($result === false) {
        storefleet_redirect_staff(
            'update-error'
        );
    }

    storefleet_redirect_staff(
        'staff-reactivated'
    );
}


/*
|--------------------------------------------------------------------------
| Parse Posted Role Assignments
|--------------------------------------------------------------------------
|
| NEW FRONTEND FORMAT:
|
| roles[manager][enabled] = 1
| roles[manager][scope_type] = all_branches
|
| roles[cashier][enabled] = 1
| roles[cashier][scope_type] = selected_branches
| roles[cashier][branch_ids][] = 1
| roles[cashier][branch_ids][] = 2
|
| roles[delivery_staff][enabled] = 1
| roles[delivery_staff][branch_ids][] = 1
|
|--------------------------------------------------------------------------
| Legacy Compatibility
|--------------------------------------------------------------------------
|
| Until staff-dashboard.php is patched, we still support:
|
| staff_role = cashier
| branch_ids[] = 1
|
*/

function storefleet_get_posted_staff_role_assignments()
{
    $allowed_roles =
        storefleet_get_staff_roles();

    $assignments = [];


    /*
    |--------------------------------------------------------------------------
    | New Multi-Role POST Format
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['roles'])
        &&
        is_array($_POST['roles'])
    ) {
        $posted_roles =
            wp_unslash(
                $_POST['roles']
            );

        foreach (
            $allowed_roles as
            $role_key => $role_label
        ) {
            if (
                !isset(
                    $posted_roles[$role_key]
                )
            ) {
                continue;
            }

            $posted_role =
                $posted_roles[$role_key];

            if (!is_array($posted_role)) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Role Must Be Explicitly Enabled
            |--------------------------------------------------------------------------
            */

            $enabled =
                !empty(
                    $posted_role['enabled']
                );

            if (!$enabled) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Scope
            |--------------------------------------------------------------------------
            */

            $scope_type =
                isset(
                    $posted_role['scope_type']
                )
                    ? sanitize_key(
                        $posted_role['scope_type']
                    )
                    : 'selected_branches';

            if (
                !in_array(
                    $scope_type,
                    [
                        'selected_branches',
                        'all_branches',
                    ],
                    true
                )
            ) {
                $scope_type =
                    'selected_branches';
            }


            /*
            |--------------------------------------------------------------------------
            | Branch IDs
            |--------------------------------------------------------------------------
            */

            $branch_ids = [];

            if (
                isset(
                    $posted_role['branch_ids']
                )
                &&
                is_array(
                    $posted_role['branch_ids']
                )
            ) {
                $branch_ids =
                    array_values(
                        array_unique(
                            array_filter(
                                array_map(
                                    'absint',
                                    $posted_role[
                                        'branch_ids'
                                    ]
                                )
                            )
                        )
                    );
            }

            sort(
                $branch_ids,
                SORT_NUMERIC
            );


            $assignments[$role_key] = [
                'role_key' =>
                    $role_key,

                'scope_type' =>
                    $scope_type,

                'branch_ids' =>
                    $branch_ids,
            ];
        }

        return $assignments;
    }


    /*
    |--------------------------------------------------------------------------
    | Legacy Single-Role POST Format
    |--------------------------------------------------------------------------
    */

    $staff_role =
        isset($_POST['staff_role'])
            ? sanitize_key(
                wp_unslash(
                    $_POST['staff_role']
                )
            )
            : '';

    if (
        $staff_role === ''
        ||
        !isset(
            $allowed_roles[$staff_role]
        )
    ) {
        return [];
    }

    $branch_ids =
        storefleet_get_posted_staff_branch_ids();

    $assignments[$staff_role] = [
        'role_key' =>
            $staff_role,

        'scope_type' =>
            'selected_branches',

        'branch_ids' =>
            $branch_ids,
    ];

    return $assignments;
}


/*
|--------------------------------------------------------------------------
| Validate Role Assignments
|--------------------------------------------------------------------------
*/

function storefleet_validate_staff_role_assignments(
    $merchant_id,
    array $assignments
) {
    $merchant_id =
        absint(
            $merchant_id
        );

    if (!$merchant_id) {
        return new WP_Error(
            'invalid-role'
        );
    }

    if (empty($assignments)) {
        return new WP_Error(
            'invalid-role'
        );
    }

    $allowed_roles =
        storefleet_get_staff_roles();


    foreach (
        $assignments as
        $role_key => $assignment
    ) {
        $role_key =
            sanitize_key(
                $role_key
            );

        if (
            $role_key === ''
            ||
            !isset(
                $allowed_roles[$role_key]
            )
        ) {
            return new WP_Error(
                'invalid-role'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Scope
        |--------------------------------------------------------------------------
        */

        $scope_type =
            sanitize_key(
                $assignment['scope_type']
                ?? ''
            );

        if (
            !in_array(
                $scope_type,
                [
                    'selected_branches',
                    'all_branches',
                ],
                true
            )
        ) {
            return new WP_Error(
                'invalid-role'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Selected Branches Require At Least One Branch
        |--------------------------------------------------------------------------
        */

        $branch_ids =
            isset(
                $assignment['branch_ids']
            )
            &&
            is_array(
                $assignment['branch_ids']
            )
                ? array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                'absint',
                                $assignment[
                                    'branch_ids'
                                ]
                            )
                        )
                    )
                )
                : [];

        if (
            $scope_type ===
                'selected_branches'
            &&
            empty($branch_ids)
        ) {
            return new WP_Error(
                'no-branch'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Every Selected Branch Must Belong To Merchant
        |--------------------------------------------------------------------------
        */

        foreach (
            $branch_ids as $branch_id
        ) {
            if (
                !storefleet_merchant_owns_branch(
                    $merchant_id,
                    $branch_id
                )
            ) {
                return new WP_Error(
                    'invalid-branch'
                );
            }
        }
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Get Primary Legacy Role
|--------------------------------------------------------------------------
|
| The old staff.staff_role column remains populated during migration.
|
| When staff has multiple roles we choose the first role based on the
| canonical StoreFleet role order.
|
*/

function storefleet_get_primary_role_from_assignments(
    array $assignments
) {
    if (empty($assignments)) {
        return '';
    }

    $allowed_roles =
        storefleet_get_staff_roles();

    foreach (
        $allowed_roles as
        $role_key => $role_label
    ) {
        if (
            isset(
                $assignments[$role_key]
            )
        ) {
            return $role_key;
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Replace Staff Role Assignments
|--------------------------------------------------------------------------
|
| This updates:
|
| wp_storefleet_staff_roles
| wp_storefleet_staff_role_branches
|
| and synchronizes legacy:
|
| wp_storefleet_staff.staff_role
| wp_storefleet_staff_branches
|
| so older code remains functional during the transition.
|
*/

function storefleet_replace_staff_role_assignments(
    $staff_id,
    $merchant_id,
    array $assignments
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    $merchant_id =
        absint(
            $merchant_id
        );

    if (
        !$staff_id
        ||
        !$merchant_id
        ||
        empty($assignments)
    ) {
        return false;
    }

    if (
        !storefleet_merchant_owns_staff(
            $merchant_id,
            $staff_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | New Tables Required For Multi-Role
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_staff_role_tables_ready'
        )
        ||
        !storefleet_staff_role_tables_ready()
    ) {
        /*
        |--------------------------------------------------------------------------
        | Legacy Fallback
        |--------------------------------------------------------------------------
        |
        | Allows current single-role frontend to continue operating if an
        | environment has not run the new database migration yet.
        |
        */

        if (count($assignments) !== 1) {
            return false;
        }

        $assignment =
            reset(
                $assignments
            );

        $primary_role =
            sanitize_key(
                $assignment['role_key']
                ?? ''
            );

        $scope_type =
            sanitize_key(
                $assignment['scope_type']
                ?? 'selected_branches'
            );

        if (
            $scope_type ===
            'all_branches'
        ) {
            $branch_ids =
                storefleet_get_all_merchant_branch_ids(
                    $merchant_id
                );
        } else {
            $branch_ids =
                $assignment['branch_ids']
                ?? [];
        }

        $staff_updated =
            $wpdb->update(
                storefleet_staff_table(),
                [
                    'staff_role' =>
                        $primary_role,

                    'updated_at' =>
                        current_time(
                            'mysql'
                        ),
                ],
                [
                    'id' =>
                        $staff_id,

                    'merchant_id' =>
                        $merchant_id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%d',
                ]
            );

        if ($staff_updated === false) {
            return false;
        }

        return
            storefleet_replace_staff_branch_assignments(
                $staff_id,
                $branch_ids
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    $roles_table =
        storefleet_staff_roles_table();

    $role_branches_table =
        storefleet_staff_role_branches_table();

    $legacy_branches_table =
        storefleet_staff_branches_table();


    /*
    |--------------------------------------------------------------------------
    | Transaction
    |--------------------------------------------------------------------------
    */

    $wpdb->query(
        'START TRANSACTION'
    );


    /*
    |--------------------------------------------------------------------------
    | Existing Staff Role IDs
    |--------------------------------------------------------------------------
    */

    $existing_role_ids =
        $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$roles_table}
                WHERE staff_id = %d
                ",
                $staff_id
            )
        );

    $existing_role_ids =
        array_values(
            array_filter(
                array_map(
                    'absint',
                    $existing_role_ids
                )
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Role Branch Assignments
    |--------------------------------------------------------------------------
    */

    if (!empty($existing_role_ids)) {

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count(
                        $existing_role_ids
                    ),
                    '%d'
                )
            );

        $delete_role_branches_sql =
            "
            DELETE FROM {$role_branches_table}
            WHERE staff_role_id
            IN ({$placeholders})
            ";

        $delete_role_branches =
            $wpdb->query(
                $wpdb->prepare(
                    $delete_role_branches_sql,
                    ...$existing_role_ids
                )
            );

        if (
            $delete_role_branches
            === false
        ) {
            error_log(
                'StoreFleet role branch delete failed: ' .
                $wpdb->last_error
            );

            $wpdb->query(
                'ROLLBACK'
            );

            return false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Roles
    |--------------------------------------------------------------------------
    */

    $delete_roles =
        $wpdb->delete(
            $roles_table,
            [
                'staff_id' =>
                    $staff_id,
            ],
            [
                '%d',
            ]
        );

    if ($delete_roles === false) {

        error_log(
            'StoreFleet staff role delete failed: ' .
            $wpdb->last_error
        );

        $wpdb->query(
            'ROLLBACK'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Legacy Branch Union
    |--------------------------------------------------------------------------
    */

    $delete_legacy =
        $wpdb->delete(
            $legacy_branches_table,
            [
                'staff_id' =>
                    $staff_id,
            ],
            [
                '%d',
            ]
        );

    if ($delete_legacy === false) {

        error_log(
            'StoreFleet legacy staff branch delete failed: ' .
            $wpdb->last_error
        );

        $wpdb->query(
            'ROLLBACK'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Insert New Roles
    |--------------------------------------------------------------------------
    */

    $now =
        current_time(
            'mysql'
        );

    $legacy_branch_ids = [];

    foreach (
        $assignments as
        $role_key => $assignment
    ) {
        $role_key =
            sanitize_key(
                $role_key
            );

        $scope_type =
            sanitize_key(
                $assignment['scope_type']
                ?? 'selected_branches'
            );

        $branch_ids =
            isset(
                $assignment['branch_ids']
            )
            &&
            is_array(
                $assignment['branch_ids']
            )
                ? array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                'absint',
                                $assignment[
                                    'branch_ids'
                                ]
                            )
                        )
                    )
                )
                : [];


        /*
        |--------------------------------------------------------------------------
        | Insert Staff Role
        |--------------------------------------------------------------------------
        */

        $role_result =
            $wpdb->insert(
                $roles_table,
                [
                    'staff_id' =>
                        $staff_id,

                    'role_key' =>
                        $role_key,

                    'scope_type' =>
                        $scope_type,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        if ($role_result === false) {

            error_log(
                'StoreFleet staff role insert failed: ' .
                $wpdb->last_error
            );

            $wpdb->query(
                'ROLLBACK'
            );

            return false;
        }

        $staff_role_id =
            absint(
                $wpdb->insert_id
            );


        /*
        |--------------------------------------------------------------------------
        | Expand All-Branches Scope
        |--------------------------------------------------------------------------
        */

        if (
            $scope_type ===
            'all_branches'
        ) {
            $effective_branch_ids =
                storefleet_get_all_merchant_branch_ids(
                    $merchant_id
                );
        } else {
            $effective_branch_ids =
                $branch_ids;
        }


        /*
        |--------------------------------------------------------------------------
        | Save Role-Specific Branches
        |--------------------------------------------------------------------------
        |
        | all_branches does not need rows in the role-branch table.
        |
        | The helper resolves all merchant branches dynamically.
        |
        */

        if (
            $scope_type ===
            'selected_branches'
        ) {
            foreach (
                $branch_ids as $branch_id
            ) {
                $branch_result =
                    $wpdb->insert(
                        $role_branches_table,
                        [
                            'staff_role_id' =>
                                $staff_role_id,

                            'branch_id' =>
                                $branch_id,

                            'created_at' =>
                                $now,
                        ],
                        [
                            '%d',
                            '%d',
                            '%s',
                        ]
                    );

                if ($branch_result === false) {

                    error_log(
                        'StoreFleet staff role branch insert failed: ' .
                        $wpdb->last_error
                    );

                    $wpdb->query(
                        'ROLLBACK'
                    );

                    return false;
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Build Legacy Branch Union
        |--------------------------------------------------------------------------
        */

        $legacy_branch_ids =
            array_merge(
                $legacy_branch_ids,
                $effective_branch_ids
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Legacy Branch Union
    |--------------------------------------------------------------------------
    */

    $legacy_branch_ids =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $legacy_branch_ids
                    )
                )
            )
        );

    sort(
        $legacy_branch_ids,
        SORT_NUMERIC
    );


    /*
    |--------------------------------------------------------------------------
    | Insert Legacy Staff Branch Union
    |--------------------------------------------------------------------------
    */

    foreach (
        $legacy_branch_ids as $branch_id
    ) {
        $legacy_result =
            $wpdb->insert(
                $legacy_branches_table,
                [
                    'staff_id' =>
                        $staff_id,

                    'branch_id' =>
                        $branch_id,
                ],
                [
                    '%d',
                    '%d',
                ]
            );

        if ($legacy_result === false) {

            error_log(
                'StoreFleet legacy branch sync failed: ' .
                $wpdb->last_error
            );

            $wpdb->query(
                'ROLLBACK'
            );

            return false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Keep Legacy Primary Role Synchronized
    |--------------------------------------------------------------------------
    */

    $primary_role =
        storefleet_get_primary_role_from_assignments(
            $assignments
        );

    $staff_update =
        $wpdb->update(
            storefleet_staff_table(),
            [
                'staff_role' =>
                    $primary_role,

                'updated_at' =>
                    $now,
            ],
            [
                'id' =>
                    $staff_id,

                'merchant_id' =>
                    $merchant_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

    if ($staff_update === false) {

        error_log(
            'StoreFleet legacy staff role sync failed: ' .
            $wpdb->last_error
        );

        $wpdb->query(
            'ROLLBACK'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $wpdb->query(
        'COMMIT'
    );

    return true;
}


/*
|--------------------------------------------------------------------------
| Get All Merchant Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_get_all_merchant_branch_ids(
    $merchant_id
) {
    global $wpdb;

    $merchant_id =
        absint(
            $merchant_id
        );

    if (!$merchant_id) {
        return [];
    }

    $table =
        storefleet_branches_table();

    $branch_ids =
        $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$table}
                WHERE merchant_id = %d
                ORDER BY id ASC
                ",
                $merchant_id
            )
        );

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    $branch_ids
                )
            )
        )
    );
}


/*
|--------------------------------------------------------------------------
| Legacy Posted Branch IDs
|--------------------------------------------------------------------------
|
| Kept because the current dashboard still submits:
|
| branch_ids[]
|
| until we patch staff-dashboard.php next.
|
*/

function storefleet_get_posted_staff_branch_ids()
{
    if (
        !isset($_POST['branch_ids'])
        ||
        !is_array($_POST['branch_ids'])
    ) {
        return [];
    }

    return array_values(
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


/*
|--------------------------------------------------------------------------
| Legacy Replace Staff Branch Assignments
|--------------------------------------------------------------------------
|
| Kept for temporary backwards compatibility.
|
*/

function storefleet_replace_staff_branch_assignments(
    $staff_id,
    array $branch_ids
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return false;
    }

    $branch_ids =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $branch_ids
                    )
                )
            )
        );

    $table =
        storefleet_staff_branches_table();

    $wpdb->query(
        'START TRANSACTION'
    );

    $delete_result =
        $wpdb->delete(
            $table,
            [
                'staff_id' =>
                    $staff_id,
            ],
            [
                '%d',
            ]
        );

    if ($delete_result === false) {

        $wpdb->query(
            'ROLLBACK'
        );

        return false;
    }

    foreach (
        $branch_ids as $branch_id
    ) {
        $result =
            $wpdb->insert(
                $table,
                [
                    'staff_id' =>
                        $staff_id,

                    'branch_id' =>
                        $branch_id,
                ],
                [
                    '%d',
                    '%d',
                ]
            );

        if ($result === false) {

            error_log(
                'StoreFleet staff branch assignment failed: ' .
                $wpdb->last_error
            );

            $wpdb->query(
                'ROLLBACK'
            );

            return false;
        }
    }

    $wpdb->query(
        'COMMIT'
    );

    return true;
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

    $staff_id =
        absint(
            $staff_id
        );

    if ($staff_id) {

        /*
        |--------------------------------------------------------------------------
        | New Role Tables
        |--------------------------------------------------------------------------
        */

        if (
            function_exists(
                'storefleet_staff_role_tables_ready'
            )
            &&
            storefleet_staff_role_tables_ready()
        ) {
            $roles_table =
                storefleet_staff_roles_table();

            $role_branches_table =
                storefleet_staff_role_branches_table();

            $role_ids =
                $wpdb->get_col(
                    $wpdb->prepare(
                        "
                        SELECT id
                        FROM {$roles_table}
                        WHERE staff_id = %d
                        ",
                        $staff_id
                    )
                );

            $role_ids =
                array_values(
                    array_filter(
                        array_map(
                            'absint',
                            $role_ids
                        )
                    )
                );

            if (!empty($role_ids)) {

                $placeholders =
                    implode(
                        ',',
                        array_fill(
                            0,
                            count($role_ids),
                            '%d'
                        )
                    );

                $wpdb->query(
                    $wpdb->prepare(
                        "
                        DELETE FROM {$role_branches_table}
                        WHERE staff_role_id
                        IN ({$placeholders})
                        ",
                        ...$role_ids
                    )
                );
            }

            $wpdb->delete(
                $roles_table,
                [
                    'staff_id' =>
                        $staff_id,
                ],
                [
                    '%d',
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Legacy Branch Assignments
        |--------------------------------------------------------------------------
        */

        $wpdb->delete(
            storefleet_staff_branches_table(),
            [
                'staff_id' =>
                    $staff_id,
            ],
            [
                '%d',
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Staff Record
        |--------------------------------------------------------------------------
        */

        $wpdb->delete(
            storefleet_staff_table(),
            [
                'id' =>
                    $staff_id,
            ],
            [
                '%d',
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
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        return;
    }

    require_once ABSPATH .
        'wp-admin/includes/user.php';

    wp_delete_user(
        $user_id
    );
}


/*
|--------------------------------------------------------------------------
| Redirect Staff Page
|--------------------------------------------------------------------------
*/

function storefleet_redirect_staff(
    $notice = '',
    $edit_staff_id = 0
) {
    $url =
        dokan_get_navigation_url(
            'storefleet-staff'
        );

    $args = [];

    if ($notice !== '') {

        $args['sf_notice'] =
            sanitize_key(
                $notice
            );
    }

    if ($edit_staff_id) {

        $args['sf_edit_staff'] =
            absint(
                $edit_staff_id
            );
    }

    if (!empty($args)) {

        $url =
            add_query_arg(
                $args,
                $url
            );
    }

    wp_safe_redirect(
        $url
    );

    exit;
}