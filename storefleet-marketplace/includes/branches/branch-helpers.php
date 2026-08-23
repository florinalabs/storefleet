<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Merchant Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_merchant_branches(
    $merchant_id,
    $active_only = false
) {
    global $wpdb;

    $merchant_id = absint($merchant_id);

    if (!$merchant_id) {
        return [];
    }

    $table = storefleet_branches_table();

    if ($active_only) {

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$table}
                WHERE merchant_id = %d
                AND is_active = 1
                ORDER BY name ASC
                ",
                $merchant_id
            )
        );
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$table}
            WHERE merchant_id = %d
            ORDER BY
                is_active DESC,
                name ASC
            ",
            $merchant_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get One Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_branch($branch_id)
{
    global $wpdb;

    $branch_id = absint($branch_id);

    if (!$branch_id) {
        return null;
    }

    $table = storefleet_branches_table();

    return $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$table}
            WHERE id = %d
            LIMIT 1
            ",
            $branch_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Merchant Owns Branch
|--------------------------------------------------------------------------
*/

function storefleet_merchant_owns_branch(
    $merchant_id,
    $branch_id
) {
    global $wpdb;

    $merchant_id = absint($merchant_id);
    $branch_id = absint($branch_id);

    if (!$merchant_id || !$branch_id) {
        return false;
    }

    $table = storefleet_branches_table();

    $found = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$table}
            WHERE id = %d
            AND merchant_id = %d
            LIMIT 1
            ",
            $branch_id,
            $merchant_id
        )
    );

    return !empty($found);
}


/*
|--------------------------------------------------------------------------
| Generate Unique Branch Slug
|--------------------------------------------------------------------------
*/

function storefleet_generate_branch_slug(
    $merchant_id,
    $branch_name
) {
    global $wpdb;

    $merchant_id = absint($merchant_id);

    $table = storefleet_branches_table();

    $base_slug = sanitize_title($branch_name);

    if ($base_slug === '') {
        $base_slug = 'branch';
    }

    $slug = $base_slug;
    $counter = 2;

    while (true) {

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$table}
                WHERE merchant_id = %d
                AND slug = %s
                LIMIT 1
                ",
                $merchant_id,
                $slug
            )
        );

        if (!$existing) {
            return $slug;
        }

        $slug =
            $base_slug .
            '-' .
            $counter;

        $counter++;
    }
}


/*
|--------------------------------------------------------------------------
| Get Branch Address
|--------------------------------------------------------------------------
*/

function storefleet_get_branch_address($branch)
{
    if (!$branch) {
        return '';
    }

    $parts = array_filter(
        [
            $branch->address_line_1 ?? '',
            $branch->address_line_2 ?? '',
            $branch->city ?? '',
            $branch->state ?? '',
            $branch->postcode ?? '',
        ]
    );

    return implode(
        ', ',
        $parts
    );
}