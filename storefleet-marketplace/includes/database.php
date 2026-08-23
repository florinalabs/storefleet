<?php

if (!defined('ABSPATH')) {
    exit;
}

function storefleet_install_database()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    /*
    |--------------------------------------------------------------------------
    | Branches
    |--------------------------------------------------------------------------
    */

    $branches_table = storefleet_branches_table();

    $branches_sql = "
        CREATE TABLE {$branches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,

            address_line_1 VARCHAR(255) DEFAULT '',
            address_line_2 VARCHAR(255) DEFAULT '',

            city VARCHAR(191) DEFAULT '',
            state VARCHAR(191) DEFAULT '',
            postcode VARCHAR(50) DEFAULT '',
            country VARCHAR(10) DEFAULT 'PH',

            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,

            contact_name VARCHAR(191) DEFAULT '',
            contact_phone VARCHAR(50) DEFAULT '',

            is_active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            PRIMARY KEY (id),

            KEY merchant_id (merchant_id),

            UNIQUE KEY merchant_branch_slug (
                merchant_id,
                slug
            )
        ) {$charset_collate};
    ";

    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    $staff_table = storefleet_staff_table();

    $staff_sql = "
        CREATE TABLE {$staff_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,

            staff_role VARCHAR(50) NOT NULL,

            is_active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            PRIMARY KEY (id),

            KEY merchant_id (merchant_id),
            KEY user_id (user_id),

            UNIQUE KEY merchant_staff (
                merchant_id,
                user_id
            )
        ) {$charset_collate};
    ";

    /*
    |--------------------------------------------------------------------------
    | Staff Branch Assignments
    |--------------------------------------------------------------------------
    */

    $staff_branches_table =
        storefleet_staff_branches_table();

    $staff_branches_sql = "
        CREATE TABLE {$staff_branches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            staff_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY (id),

            KEY staff_id (staff_id),
            KEY branch_id (branch_id),

            UNIQUE KEY staff_branch (
                staff_id,
                branch_id
            )
        ) {$charset_collate};
    ";

    /*
    |--------------------------------------------------------------------------
    | Branch Inventory
    |--------------------------------------------------------------------------
    */

    $inventory_table =
        storefleet_branch_inventory_table();

    $inventory_sql = "
        CREATE TABLE {$inventory_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            branch_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,

            stock_qty DECIMAL(20,6)
                NOT NULL DEFAULT 0,

            reserved_qty DECIMAL(20,6)
                NOT NULL DEFAULT 0,

            low_stock_threshold DECIMAL(20,6)
                DEFAULT NULL,

            updated_at DATETIME NOT NULL,

            PRIMARY KEY (id),

            KEY branch_id (branch_id),
            KEY product_id (product_id),

            UNIQUE KEY branch_product (
                branch_id,
                product_id
            )
        ) {$charset_collate};
    ";

    dbDelta($branches_sql);
    dbDelta($staff_sql);
    dbDelta($staff_branches_sql);
    dbDelta($inventory_sql);

    update_option(
        'storefleet_db_version',
        STOREFLEET_DB_VERSION
    );
}