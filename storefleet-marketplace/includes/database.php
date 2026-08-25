<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Install / Upgrade StoreFleet Database
|--------------------------------------------------------------------------
*/

function storefleet_install_database()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate =
        $wpdb->get_charset_collate();


    /*
    |--------------------------------------------------------------------------
    | Branches
    |--------------------------------------------------------------------------
    */

    $branches_table =
        storefleet_branches_table();

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

            PRIMARY KEY  (id),
            KEY merchant_id (merchant_id),
            UNIQUE KEY merchant_branch_slug (merchant_id,slug)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    |
    | staff_role remains temporarily for backward compatibility.
    |
    | New multi-role assignments are stored in:
    |
    | wp_storefleet_staff_roles
    |
    */

    $staff_table =
        storefleet_staff_table();

    $staff_sql = "
        CREATE TABLE {$staff_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            merchant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,

            staff_role VARCHAR(50) NOT NULL,

            is_active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            KEY merchant_id (merchant_id),
            KEY user_id (user_id),
            UNIQUE KEY merchant_staff (merchant_id,user_id)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Legacy Staff Branch Assignments
    |--------------------------------------------------------------------------
    |
    | Keep this table during the migration period.
    |
    | Existing:
    |
    | staff
    |   └── branches
    |
    | Current:
    |
    | staff
    |   └── role
    |       └── branches
    |
    */

    $staff_branches_table =
        storefleet_staff_branches_table();

    $staff_branches_sql = "
        CREATE TABLE {$staff_branches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            staff_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY  (id),
            KEY staff_id (staff_id),
            KEY branch_id (branch_id),
            UNIQUE KEY staff_branch (staff_id,branch_id)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Staff Role Assignments
    |--------------------------------------------------------------------------
    |
    | One staff member may have multiple operational roles.
    |
    | scope_type:
    |
    | selected_branches
    | all_branches
    |
    */

    $staff_roles_table =
        storefleet_staff_roles_table();

    $staff_roles_sql = "
        CREATE TABLE {$staff_roles_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            staff_id BIGINT UNSIGNED NOT NULL,

            role_key VARCHAR(50) NOT NULL,

            scope_type VARCHAR(30)
                NOT NULL
                DEFAULT 'selected_branches',

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            KEY staff_id (staff_id),
            KEY role_key (role_key),
            KEY scope_type (scope_type),
            UNIQUE KEY staff_role (staff_id,role_key)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Staff Role Branch Assignments
    |--------------------------------------------------------------------------
    |
    | Branch permissions belong to a specific staff role.
    |
    | Example:
    |
    | Cashier
    |   - Makati
    |   - BGC
    |
    | Inventory Staff
    |   - Makati
    |
    */

    $staff_role_branches_table =
        storefleet_staff_role_branches_table();

    $staff_role_branches_sql = "
        CREATE TABLE {$staff_role_branches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            staff_role_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,

            created_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            KEY staff_role_id (staff_role_id),
            KEY branch_id (branch_id),
            UNIQUE KEY staff_role_branch (staff_role_id,branch_id)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Product Branch Availability
    |--------------------------------------------------------------------------
    |
    | Stores explicit product → branch assignments.
    |
    | IMPORTANT:
    |
    | This table represents:
    |
    |     "Which branches sell this product?"
    |
    | It does NOT represent:
    |
    |     "How much stock exists at each branch?"
    |
    | Branch inventory remains stored separately in:
    |
    | wp_storefleet_branch_inventory
    |
    |--------------------------------------------------------------------------
    | Product Branch Modes
    |--------------------------------------------------------------------------
    |
    | Product mode is stored later using WooCommerce product meta:
    |
    | _storefleet_branch_mode
    |
    | Values:
    |
    | all
    |     Product is available at every active merchant branch.
    |
    | selected
    |     Product is available only at branch IDs stored in this table.
    |
    | Existing products with no meta will later be treated as:
    |
    | all
    |
    | This keeps existing merchant products backwards compatible.
    |
    */

    $product_branches_table =
        $wpdb->prefix .
        'storefleet_product_branches';

    $product_branches_sql = "
        CREATE TABLE {$product_branches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            product_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,

            created_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY branch_id (branch_id),
            UNIQUE KEY product_branch (product_id,branch_id)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Branch Inventory
    |--------------------------------------------------------------------------
    |
    | Branch inventory is intentionally separate from product availability.
    |
    | Example:
    |
    | Product availability:
    |
    | Coke
    |   Makati = yes
    |   BGC    = yes
    |
    | Future branch inventory:
    |
    | Coke
    |   Makati = 60
    |   BGC    = 40
    |
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

            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY product_id (product_id),
            UNIQUE KEY branch_product (branch_id,product_id)
        ) {$charset_collate};
    ";


    /*
    |--------------------------------------------------------------------------
    | Create / Upgrade Tables
    |--------------------------------------------------------------------------
    |
    | Keep each dbDelta() call independent so one table definition is easier
    | to diagnose during development.
    |
    */

    dbDelta(
        $branches_sql
    );

    dbDelta(
        $staff_sql
    );

    dbDelta(
        $staff_branches_sql
    );

    dbDelta(
        $staff_roles_sql
    );

    dbDelta(
        $staff_role_branches_sql
    );

    dbDelta(
        $product_branches_sql
    );

    dbDelta(
        $inventory_sql
    );


    /*
    |--------------------------------------------------------------------------
    | Migrate Legacy Staff Roles
    |--------------------------------------------------------------------------
    |
    | Existing:
    |
    | wp_storefleet_staff.staff_role
    |
    | Becomes:
    |
    | wp_storefleet_staff_roles.role_key
    |
    | INSERT IGNORE makes this safe to run multiple times.
    |
    */

    storefleet_migrate_legacy_staff_roles();


    /*
    |--------------------------------------------------------------------------
    | Database Version
    |--------------------------------------------------------------------------
    */

    update_option(
        'storefleet_db_version',
        STOREFLEET_DB_VERSION
    );
}


/*
|--------------------------------------------------------------------------
| Migrate Existing Staff To Multi-Role Model
|--------------------------------------------------------------------------
|
| This migration is intentionally idempotent.
|
| Running it repeatedly will not duplicate roles or branch assignments.
|
*/

function storefleet_migrate_legacy_staff_roles()
{
    global $wpdb;


    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    $staff_table =
        storefleet_staff_table();

    $legacy_branches_table =
        storefleet_staff_branches_table();

    $roles_table =
        storefleet_staff_roles_table();

    $role_branches_table =
        storefleet_staff_role_branches_table();


    /*
    |--------------------------------------------------------------------------
    | Safety
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_database_table_exists(
            $staff_table
        )
        ||
        !storefleet_database_table_exists(
            $roles_table
        )
        ||
        !storefleet_database_table_exists(
            $role_branches_table
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | 1. Migrate Legacy Role
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | staff_id = 1
    | staff_role = delivery_staff
    |
    | becomes:
    |
    | staff_roles
    | staff_id = 1
    | role_key = delivery_staff
    |
    */

    $wpdb->query(
        "
        INSERT IGNORE INTO {$roles_table}
        (
            staff_id,
            role_key,
            scope_type,
            created_at,
            updated_at
        )

        SELECT
            s.id,
            s.staff_role,
            'selected_branches',
            NOW(),
            NOW()

        FROM {$staff_table} s

        WHERE
            s.staff_role IS NOT NULL
            AND s.staff_role <> ''
        "
    );


    /*
    |--------------------------------------------------------------------------
    | 2. Migrate Legacy Branch Assignments
    |--------------------------------------------------------------------------
    |
    | Existing:
    |
    | staff_id → branch_id
    |
    | becomes:
    |
    | staff_role_id → branch_id
    |
    | The branch is assigned to the staff member's original legacy role.
    |
    */

    if (
        storefleet_database_table_exists(
            $legacy_branches_table
        )
    ) {
        $wpdb->query(
            "
            INSERT IGNORE INTO {$role_branches_table}
            (
                staff_role_id,
                branch_id,
                created_at
            )

            SELECT
                sr.id,
                sb.branch_id,
                NOW()

            FROM {$legacy_branches_table} sb

            INNER JOIN {$staff_table} s
                ON s.id = sb.staff_id

            INNER JOIN {$roles_table} sr
                ON sr.staff_id = s.id
                AND sr.role_key = s.staff_role

            WHERE
                s.staff_role IS NOT NULL
                AND s.staff_role <> ''
            "
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Migration Complete
    |--------------------------------------------------------------------------
    */

    return true;
}