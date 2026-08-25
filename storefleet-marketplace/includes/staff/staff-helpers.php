<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| New Staff Role Table Names
|--------------------------------------------------------------------------
|
| These tables will be created by our next database migration.
|
| Until those tables exist, the helpers below automatically fall back
| to the legacy:
|
| - staff.staff_role
| - storefleet_staff_branches
|
| This lets us deploy the changes safely without breaking existing staff.
|
*/

if (!function_exists('storefleet_staff_roles_table')) {

    function storefleet_staff_roles_table()
    {
        global $wpdb;

        return $wpdb->prefix .
            'storefleet_staff_roles';
    }
}


if (!function_exists('storefleet_staff_role_branches_table')) {

    function storefleet_staff_role_branches_table()
    {
        global $wpdb;

        return $wpdb->prefix .
            'storefleet_staff_role_branches';
    }
}


/*
|--------------------------------------------------------------------------
| Database Table Exists
|--------------------------------------------------------------------------
*/

function storefleet_database_table_exists(
    $table_name
) {
    global $wpdb;

    $table_name =
        (string) $table_name;

    if ($table_name === '') {
        return false;
    }

    static $cache = [];

    if (
        array_key_exists(
            $table_name,
            $cache
        )
    ) {
        return $cache[$table_name];
    }

    $found =
        $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like(
                    $table_name
                )
            )
        );

    $cache[$table_name] =
        $found === $table_name;

    return $cache[$table_name];
}


/*
|--------------------------------------------------------------------------
| New Staff Role Tables Ready
|--------------------------------------------------------------------------
*/

function storefleet_staff_role_tables_ready()
{
    return
        storefleet_database_table_exists(
            storefleet_staff_roles_table()
        )
        &&
        storefleet_database_table_exists(
            storefleet_staff_role_branches_table()
        );
}


/*
|--------------------------------------------------------------------------
| Get Merchant Staff
|--------------------------------------------------------------------------
*/

function storefleet_get_merchant_staff(
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

    $staff_table =
        storefleet_staff_table();

    $users_table =
        $wpdb->users;

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

function storefleet_get_staff(
    $staff_id
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return null;
    }

    $staff_table =
        storefleet_staff_table();

    $users_table =
        $wpdb->users;

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
| Get Staff Record By WordPress User
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_by_user_id(
    $user_id
) {
    global $wpdb;

    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        return null;
    }

    $staff_table =
        storefleet_staff_table();

    $users_table =
        $wpdb->users;

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
            WHERE s.user_id = %d
            LIMIT 1
            ",
            $user_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get Current Staff
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff()
{
    if (!is_user_logged_in()) {
        return null;
    }

    return storefleet_get_staff_by_user_id(
        get_current_user_id()
    );
}


/*
|--------------------------------------------------------------------------
| Get Current Staff ID
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_id()
{
    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return 0;
    }

    return absint(
        $staff->id
    );
}


/*
|--------------------------------------------------------------------------
| Is StoreFleet Staff User
|--------------------------------------------------------------------------
*/

function storefleet_is_staff_user(
    $user_id = 0
) {
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        $user_id =
            get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $user =
        get_userdata(
            $user_id
        );

    if (!$user) {
        return false;
    }

    return in_array(
        'storefleet_staff',
        (array) $user->roles,
        true
    );
}


/*
|--------------------------------------------------------------------------
| Is Active StoreFleet Staff
|--------------------------------------------------------------------------
*/

function storefleet_is_active_staff(
    $user_id = 0
) {
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        $user_id =
            get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user_id
        );

    if (!$staff) {
        return false;
    }

    return
        (int) $staff->is_active
        === 1;
}


/*
|--------------------------------------------------------------------------
| Current Operational Merchant ID
|--------------------------------------------------------------------------
*/

function storefleet_get_current_operational_merchant_id()
{
    if (!is_user_logged_in()) {
        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Merchant Owner
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner()
    ) {
        return absint(
            storefleet_get_current_merchant_id()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active
        !== 1
    ) {
        return 0;
    }

    return absint(
        $staff->merchant_id
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

    $merchant_id =
        absint(
            $merchant_id
        );

    $staff_id =
        absint(
            $staff_id
        );

    if (
        !$merchant_id
        ||
        !$staff_id
    ) {
        return false;
    }

    $table =
        storefleet_staff_table();

    $found =
        $wpdb->get_var(
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

    return !empty(
        $found
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Role Assignments
|--------------------------------------------------------------------------
|
| New system:
|
| staff
|   ├── cashier
|   ├── inventory_staff
|   └── delivery_staff
|
| Each returned assignment contains:
|
| - id
| - staff_id
| - role_key
| - scope_type
|
| If the new tables have not been migrated yet, this function creates a
| virtual legacy assignment from staff.staff_role.
|
*/

function storefleet_get_staff_role_assignments(
    $staff_id
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | New Role Table
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_staff_role_tables_ready()
    ) {
        $table =
            storefleet_staff_roles_table();

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT
                        id,
                        staff_id,
                        role_key,
                        scope_type,
                        created_at,
                        updated_at
                    FROM {$table}
                    WHERE staff_id = %d
                    ORDER BY id ASC
                    ",
                    $staff_id
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Migration Already Exists
        |--------------------------------------------------------------------------
        */

        if (!empty($rows)) {
            return $rows;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Legacy Fallback
    |--------------------------------------------------------------------------
    */

    $legacy_role =
        isset($staff->staff_role)
            ? sanitize_key(
                $staff->staff_role
            )
            : '';

    if ($legacy_role === '') {
        return [];
    }

    return [
        (object) [
            'id' =>
                0,

            'staff_id' =>
                $staff_id,

            'role_key' =>
                $legacy_role,

            'scope_type' =>
                'selected_branches',

            'created_at' =>
                null,

            'updated_at' =>
                null,

            'legacy' =>
                true,
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| Get Staff Role Keys
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_roles_for_staff(
    $staff_id
) {
    $assignments =
        storefleet_get_staff_role_assignments(
            $staff_id
        );

    if (empty($assignments)) {
        return [];
    }

    $roles = [];

    foreach (
        $assignments as $assignment
    ) {
        $role_key =
            sanitize_key(
                $assignment->role_key ?? ''
            );

        if ($role_key === '') {
            continue;
        }

        $roles[] =
            $role_key;
    }

    return array_values(
        array_unique(
            $roles
        )
    );
}


/*
|--------------------------------------------------------------------------
| Current Staff Role Keys
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_roles()
{
    $staff_id =
        storefleet_get_current_staff_id();

    if (!$staff_id) {
        return [];
    }

    return storefleet_get_staff_roles_for_staff(
        $staff_id
    );
}


/*
|--------------------------------------------------------------------------
| Backward-Compatible Current Staff Role
|--------------------------------------------------------------------------
|
| Some existing StoreFleet code expects one role.
|
| During migration this returns the first assigned role.
| New code should use storefleet_get_current_staff_roles().
|
*/

function storefleet_get_current_staff_role()
{
    $roles =
        storefleet_get_current_staff_roles();

    if (empty($roles)) {
        return '';
    }

    return
        $roles[0];
}


/*
|--------------------------------------------------------------------------
| Staff Has Role
|--------------------------------------------------------------------------
*/

function storefleet_staff_has_role(
    $staff_id,
    $roles
) {
    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return false;
    }

    $roles =
        is_array($roles)
            ? $roles
            : [$roles];

    $roles =
        array_values(
            array_filter(
                array_map(
                    'sanitize_key',
                    $roles
                )
            )
        );

    if (empty($roles)) {
        return false;
    }

    $staff_roles =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    return !empty(
        array_intersect(
            $roles,
            $staff_roles
        )
    );
}


/*
|--------------------------------------------------------------------------
| Current Staff Has Role
|--------------------------------------------------------------------------
*/

function storefleet_current_staff_has_role(
    $roles
) {
    $staff_id =
        storefleet_get_current_staff_id();

    if (!$staff_id) {
        return false;
    }

    return storefleet_staff_has_role(
        $staff_id,
        $roles
    );
}


/*
|--------------------------------------------------------------------------
| Get One Staff Role Assignment
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_assignment(
    $staff_id,
    $role_key
) {
    $staff_id =
        absint(
            $staff_id
        );

    $role_key =
        sanitize_key(
            $role_key
        );

    if (
        !$staff_id
        ||
        $role_key === ''
    ) {
        return null;
    }

    $assignments =
        storefleet_get_staff_role_assignments(
            $staff_id
        );

    foreach (
        $assignments as $assignment
    ) {
        if (
            sanitize_key(
                $assignment->role_key ?? ''
            )
            === $role_key
        ) {
            return $assignment;
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Staff Role Scope
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_scope(
    $staff_id,
    $role_key
) {
    $assignment =
        storefleet_get_staff_role_assignment(
            $staff_id,
            $role_key
        );

    if (!$assignment) {
        return '';
    }

    $scope =
        sanitize_key(
            $assignment->scope_type
            ?? ''
        );

    if (
        !in_array(
            $scope,
            [
                'selected_branches',
                'all_branches',
            ],
            true
        )
    ) {
        return
            'selected_branches';
    }

    return $scope;
}


/*
|--------------------------------------------------------------------------
| Get Legacy Staff Branch IDs
|--------------------------------------------------------------------------
|
| Used while migrating existing records.
|
*/

function storefleet_get_legacy_staff_branch_ids(
    $staff_id
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    $table =
        storefleet_staff_branches_table();

    $branch_ids =
        $wpdb->get_col(
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

    return array_values(
        array_unique(
            array_map(
                'absint',
                $branch_ids
            )
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Role Branch IDs
|--------------------------------------------------------------------------
|
| Example:
|
| Cashier:
|   Makati
|   BGC
|
| Delivery Staff:
|   Makati
|
*/

function storefleet_get_staff_role_branch_ids(
    $staff_id,
    $role_key
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    $role_key =
        sanitize_key(
            $role_key
        );

    if (
        !$staff_id
        ||
        $role_key === ''
    ) {
        return [];
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        return [];
    }

    $assignment =
        storefleet_get_staff_role_assignment(
            $staff_id,
            $role_key
        );

    if (!$assignment) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Legacy Assignment
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $assignment->legacy
        )
        ||
        !storefleet_staff_role_tables_ready()
    ) {
        return
            storefleet_get_legacy_staff_branch_ids(
                $staff_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | All Merchant Branches
    |--------------------------------------------------------------------------
    */

    $scope =
        storefleet_get_staff_role_scope(
            $staff_id,
            $role_key
        );

    if (
        $scope ===
        'all_branches'
    ) {
        $branches_table =
            storefleet_branches_table();

        $branch_ids =
            $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$branches_table}
                    WHERE merchant_id = %d
                    ORDER BY id ASC
                    ",
                    absint(
                        $staff->merchant_id
                    )
                )
            );

        return array_values(
            array_unique(
                array_map(
                    'absint',
                    $branch_ids
                )
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branches
    |--------------------------------------------------------------------------
    */

    $role_branches_table =
        storefleet_staff_role_branches_table();

    $roles_table =
        storefleet_staff_roles_table();

    $branches_table =
        storefleet_branches_table();

    $branch_ids =
        $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT b.id
                FROM {$role_branches_table} srb
                INNER JOIN {$roles_table} sr
                    ON sr.id = srb.staff_role_id
                INNER JOIN {$branches_table} b
                    ON b.id = srb.branch_id
                WHERE sr.staff_id = %d
                AND sr.role_key = %s
                AND b.merchant_id = %d
                ORDER BY b.id ASC
                ",
                $staff_id,
                $role_key,
                absint(
                    $staff->merchant_id
                )
            )
        );

    return array_values(
        array_unique(
            array_map(
                'absint',
                $branch_ids
            )
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Role Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_branches(
    $staff_id,
    $role_key
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        return [];
    }

    $branch_ids =
        storefleet_get_staff_role_branch_ids(
            $staff_id,
            $role_key
        );

    if (empty($branch_ids)) {
        return [];
    }

    $branches_table =
        storefleet_branches_table();

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($branch_ids),
                '%d'
            )
        );

    $query =
        "
        SELECT *
        FROM {$branches_table}
        WHERE merchant_id = %d
        AND id IN ({$placeholders})
        ORDER BY name ASC
        ";

    $args =
        array_merge(
            [
                absint(
                    $staff->merchant_id
                ),
            ],
            $branch_ids
        );

    return $wpdb->get_results(
        $wpdb->prepare(
            $query,
            ...$args
        )
    );
}


/*
|--------------------------------------------------------------------------
| Role Can Access Branch
|--------------------------------------------------------------------------
|
| This is the important role-specific branch authorization check.
|
| Example:
|
| Staff:
|   Cashier -> Makati + BGC
|   Delivery Staff -> Makati
|
| pos.use + BGC:
|   Cashier -> BGC = ALLOW
|
| delivery.rider + BGC:
|   Delivery Staff -> BGC = DENY
|
*/

function storefleet_staff_role_can_access_branch(
    $staff_id,
    $role_key,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    $role_key =
        sanitize_key(
            $role_key
        );

    if (
        !$staff_id
        ||
        !$branch_id
        ||
        $role_key === ''
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (
        !$staff
        ||
        (int) $staff->is_active
        !== 1
    ) {
        return false;
    }

    if (
        !storefleet_staff_has_role(
            $staff_id,
            $role_key
        )
    ) {
        return false;
    }

    $branch_ids =
        storefleet_get_staff_role_branch_ids(
            $staff_id,
            $role_key
        );

    return in_array(
        $branch_id,
        $branch_ids,
        true
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Branch IDs
|--------------------------------------------------------------------------
|
| Returns the UNION of branches from every assigned role.
|
| This keeps existing code working while role-specific authorization can
| use storefleet_staff_role_can_access_branch().
|
*/

function storefleet_get_staff_branch_ids(
    $staff_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    $roles =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    /*
    |--------------------------------------------------------------------------
    | Legacy Safety
    |--------------------------------------------------------------------------
    */

    if (empty($roles)) {
        return
            storefleet_get_legacy_staff_branch_ids(
                $staff_id
            );
    }

    $branch_ids = [];

    foreach (
        $roles as $role_key
    ) {
        $branch_ids =
            array_merge(
                $branch_ids,
                storefleet_get_staff_role_branch_ids(
                    $staff_id,
                    $role_key
                )
            );
    }

    $branch_ids =
        array_values(
            array_unique(
                array_map(
                    'absint',
                    $branch_ids
                )
            )
        );

    sort(
        $branch_ids,
        SORT_NUMERIC
    );

    return $branch_ids;
}


/*
|--------------------------------------------------------------------------
| Current Staff Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_branch_ids()
{
    $staff_id =
        storefleet_get_current_staff_id();

    if (!$staff_id) {
        return [];
    }

    return storefleet_get_staff_branch_ids(
        $staff_id
    );
}


/*
|--------------------------------------------------------------------------
| Get Staff Branches
|--------------------------------------------------------------------------
|
| Returns all unique branches available through any of the employee's roles.
|
*/

function storefleet_get_staff_branches(
    $staff_id
) {
    global $wpdb;

    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        return [];
    }

    $branch_ids =
        storefleet_get_staff_branch_ids(
            $staff_id
        );

    if (empty($branch_ids)) {
        return [];
    }

    $branches_table =
        storefleet_branches_table();

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($branch_ids),
                '%d'
            )
        );

    $query =
        "
        SELECT *
        FROM {$branches_table}
        WHERE merchant_id = %d
        AND id IN ({$placeholders})
        ORDER BY name ASC
        ";

    $args =
        array_merge(
            [
                absint(
                    $staff->merchant_id
                ),
            ],
            $branch_ids
        );

    return $wpdb->get_results(
        $wpdb->prepare(
            $query,
            ...$args
        )
    );
}


/*
|--------------------------------------------------------------------------
| Current Staff Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_branches()
{
    $staff_id =
        storefleet_get_current_staff_id();

    if (!$staff_id) {
        return [];
    }

    return storefleet_get_staff_branches(
        $staff_id
    );
}


/*
|--------------------------------------------------------------------------
| Staff Can Access Branch
|--------------------------------------------------------------------------
|
| General branch access.
|
| Returns TRUE if ANY role assigned to the staff member grants access to
| the requested branch.
|
| For permission-specific checks, roles.php will later determine which
| specific role grants the requested permission and branch combination.
|
*/

function storefleet_staff_can_access_branch(
    $staff_id,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$staff_id
        ||
        !$branch_id
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (
        !$staff
        ||
        (int) $staff->is_active
        !== 1
    ) {
        return false;
    }

    $roles =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    foreach (
        $roles as $role_key
    ) {
        if (
            storefleet_staff_role_can_access_branch(
                $staff_id,
                $role_key,
                $branch_id
            )
        ) {
            return true;
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Current User Can Access Branch
|--------------------------------------------------------------------------
*/

function storefleet_current_user_can_access_branch(
    $branch_id
) {
    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owner
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner()
    ) {
        return storefleet_merchant_owns_branch(
            storefleet_get_current_merchant_id(),
            $branch_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return false;
    }

    return storefleet_staff_can_access_branch(
        $staff->id,
        $branch_id
    );
}


/*
|--------------------------------------------------------------------------
| Require Branch Access
|--------------------------------------------------------------------------
*/

function storefleet_require_branch_access(
    $branch_id
) {
    if (
        storefleet_current_user_can_access_branch(
            $branch_id
        )
    ) {
        return true;
    }

    wp_die(
        esc_html__(
            'You do not have access to this StoreFleet branch.',
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
| Get Staff Phone
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_phone(
    $user_id
) {
    $user_id =
        absint(
            $user_id
        );

    if (!$user_id) {
        return '';
    }

    return sanitize_text_field(
        (string) get_user_meta(
            $user_id,
            'storefleet_staff_phone',
            true
        )
    );
}


/*
|--------------------------------------------------------------------------
| Staff Role Label
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_label(
    $role
) {
    $role =
        sanitize_key(
            $role
        );

    $roles =
        storefleet_get_staff_roles();

    return
        $roles[$role]
        ?? $role;
}


/*
|--------------------------------------------------------------------------
| Staff Role Labels
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_labels(
    $staff_id
) {
    $role_keys =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    if (empty($role_keys)) {
        return [];
    }

    $labels = [];

    foreach (
        $role_keys as $role_key
    ) {
        $labels[] =
            storefleet_get_staff_role_label(
                $role_key
            );
    }

    return $labels;
}


/*
|--------------------------------------------------------------------------
| Staff Roles Display
|--------------------------------------------------------------------------
|
| Example:
|
| Cashier, Inventory Staff, Order Staff
|
*/

function storefleet_get_staff_roles_display(
    $staff_id
) {
    $labels =
        storefleet_get_staff_role_labels(
            $staff_id
        );

    if (empty($labels)) {
        return '';
    }

    return implode(
        ', ',
        $labels
    );
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
            strtolower(
                $name_base
            ),
            true
        );

    if ($base === '') {

        $email_parts =
            explode(
                '@',
                $email
            );

        $base =
            sanitize_user(
                strtolower(
                    $email_parts[0]
                    ?? ''
                ),
                true
            );
    }

    if ($base === '') {
        $base =
            'storefleetstaff';
    }

    $username =
        $base;

    $counter =
        2;

    while (
        username_exists(
            $username
        )
    ) {
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

            return
                $branch->name;
        },
        $branches
    );
}


/*
|--------------------------------------------------------------------------
| Staff Role Branch Names
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_branch_names(
    $staff_id,
    $role_key
) {
    $branches =
        storefleet_get_staff_role_branches(
            $staff_id,
            $role_key
        );

    if (empty($branches)) {
        return [];
    }

    return array_map(
        function ($branch) {

            return
                $branch->name;
        },
        $branches
    );
}

/*
|--------------------------------------------------------------------------
| Staff Is Assigned To Branch
|--------------------------------------------------------------------------
|
| Used for branch-specific Staff directory screens.
|
| This intentionally does not require the staff member to be active so a
| suspended employee can still appear in the Staff directory.
|
| Authorization for the logged-in viewer is handled separately.
|
*/

function storefleet_staff_is_assigned_to_branch(
    $staff_id,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$staff_id
        ||
        !$branch_id
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (!$staff) {
        return false;
    }

    if (
        !function_exists(
            'storefleet_merchant_owns_branch'
        )
        ||
        !storefleet_merchant_owns_branch(
            absint(
                $staff->merchant_id
            ),
            $branch_id
        )
    ) {
        return false;
    }

    $roles =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    if (empty($roles)) {
        return false;
    }

    foreach (
        $roles as $role_key
    ) {
        $role_branch_ids =
            storefleet_get_staff_role_branch_ids(
                $staff_id,
                $role_key
            );

        if (
            in_array(
                $branch_id,
                $role_branch_ids,
                true
            )
        ) {
            return true;
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Get Merchant Staff For Branch
|--------------------------------------------------------------------------
|
| Returns all merchant staff whose role assignments include the requested
| branch.
|
| Both active and suspended employees are returned so the Staff directory can
| accurately display account status.
|
*/

function storefleet_get_merchant_staff_for_branch(
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
    ) {
        return [];
    }

    if (
        !function_exists(
            'storefleet_merchant_owns_branch'
        )
        ||
        !storefleet_merchant_owns_branch(
            $merchant_id,
            $branch_id
        )
    ) {
        return [];
    }

    $staff =
        storefleet_get_merchant_staff(
            $merchant_id
        );

    if (empty($staff)) {
        return [];
    }

    $branch_staff = [];

    foreach (
        $staff as $member
    ) {
        $staff_id =
            absint(
                $member->id
                ?? 0
            );

        if (!$staff_id) {
            continue;
        }

        if (
            !storefleet_staff_is_assigned_to_branch(
                $staff_id,
                $branch_id
            )
        ) {
            continue;
        }

        $branch_staff[] =
            $member;
    }

    return $branch_staff;
}