<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Merchant Staff
|--------------------------------------------------------------------------
*/

function storefleet_get_merchant_staff($merchant_id)
{
    global $wpdb;

    $merchant_id = absint($merchant_id);

    if (!$merchant_id) {
        return [];
    }

    $staff_table = storefleet_staff_table();
    $users_table = $wpdb->users;

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                s.*,
                u.user_login,
                u.user_email,
                u.display_name
            FROM {$staff_table} s
            INNER JOIN {$users_table} u
                ON u.ID = s.user_id
            WHERE s.merchant_id = %d
            ORDER BY
                s.is_active DESC,
                u.display_name ASC
            ",
            $merchant_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get One Staff Record
|--------------------------------------------------------------------------
*/

function storefleet_get_staff($staff_id)
{
    global $wpdb;

    $staff_id = absint($staff_id);

    if (!$staff_id) {
        return null;
    }

    $staff_table = storefleet_staff_table();
    $users_table = $wpdb->users;

    return $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT
                s.*,
                u.user_login,
                u.user_email,
                u.display_name
            FROM {$staff_table} s
            INNER JOIN {$users_table} u
                ON u.ID = s.user_id
            WHERE s.id = %d
            LIMIT 1
            ",
            $staff_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Merchant Owns Staff
|--------------------------------------------------------------------------
*/

function storefleet_merchant_owns_staff(
    $merchant_id,
    $staff_id
) {
    global $wpdb;

    $merchant_id = absint($merchant_id);
    $staff_id = absint($staff_id);

    if (!$merchant_id || !$staff_id) {
        return false;
    }

    $table = storefleet_staff_table();

    $found = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$table}
            WHERE id = %d
            AND merchant_id = %d
            LIMIT 1
            ",
            $staff_id,
            $merchant_id
        )
    );

    return !empty($found);
}


/*
|--------------------------------------------------------------------------
| Get Staff Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_branch_ids($staff_id)
{
    global $wpdb;

    $staff_id = absint($staff_id);

    if (!$staff_id) {
        return [];
    }

    $table =
        storefleet_staff_branches_table();

    $branch_ids = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT branch_id
            FROM {$table}
            WHERE staff_id = %d
            ORDER BY branch_id ASC
            ",
            $staff_id
        )
    );

    return array_map(
        'absint',
        $branch_ids
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_branches($staff_id)
{
    global $wpdb;

    $staff_id = absint($staff_id);

    if (!$staff_id) {
        return [];
    }

    $assignments =
        storefleet_staff_branches_table();

    $branches =
        storefleet_branches_table();

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT b.*
            FROM {$branches} b
            INNER JOIN {$assignments} sb
                ON sb.branch_id = b.id
            WHERE sb.staff_id = %d
            ORDER BY b.name ASC
            ",
            $staff_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Staff Role Label
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_label($role)
{
    $roles =
        storefleet_get_staff_roles();

    return $roles[$role] ?? $role;
}


/*
|--------------------------------------------------------------------------
| Generate Staff Username
|--------------------------------------------------------------------------
*/

function storefleet_generate_staff_username(
    $email,
    $first_name = '',
    $last_name = ''
) {
    $name_base =
        trim(
            $first_name .
            '.' .
            $last_name,
            '.'
        );

    $base =
        sanitize_user(
            strtolower($name_base),
            true
        );

    if ($base === '') {

        $email_parts =
            explode('@', $email);

        $base =
            sanitize_user(
                strtolower(
                    $email_parts[0] ?? ''
                ),
                true
            );
    }

    if ($base === '') {
        $base = 'storefleetstaff';
    }

    $username = $base;
    $counter = 2;

    while (username_exists($username)) {

        $username =
            $base .
            $counter;

        $counter++;
    }

    return $username;
}


/*
|--------------------------------------------------------------------------
| Staff Branch Names
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_branch_names(
    $staff_id
) {
    $branches =
        storefleet_get_staff_branches(
            $staff_id
        );

    if (empty($branches)) {
        return [];
    }

    return array_map(
        function ($branch) {
            return $branch->name;
        },
        $branches
    );
}