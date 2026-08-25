<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Install WordPress Staff Role
|--------------------------------------------------------------------------
|
| All StoreFleet employees use one WordPress authentication role.
|
| Operational authorization is handled separately through:
|
| - StoreFleet staff record
| - one or more StoreFleet operational roles
| - role permission matrix
| - role-specific branch assignments
| - active/suspended status
|
*/

function storefleet_install_roles()
{
    $role =
        get_role(
            'storefleet_staff'
        );

    if (!$role) {
        add_role(
            'storefleet_staff',
            'StoreFleet Staff',
            [
                'read' => true,
            ]
        );

        return;
    }

    $role->add_cap(
        'read'
    );
}


/*
|--------------------------------------------------------------------------
| StoreFleet Staff Roles
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_roles()
{
    return [

        'manager' =>
            'Manager',

        'branch_manager' =>
            'Branch Manager',

        'cashier' =>
            'Cashier',

        'inventory_staff' =>
            'Inventory Staff',

        'order_staff' =>
            'Order Staff',

        'delivery_staff' =>
            'Delivery Staff',
    ];
}


/*
|--------------------------------------------------------------------------
| StoreFleet Permission Matrix
|--------------------------------------------------------------------------
|
| Staff may have multiple StoreFleet roles.
|
| Effective permissions are the union of all assigned roles.
|
| IMPORTANT:
|
| Branch assignment is NOT represented by:
|
|   branches.view
|
| anymore.
|
| Staff choose their current operational branch using the global branch
| selector.
|
| Permission + branch authorization is checked using the SAME StoreFleet
| role.
|
|--------------------------------------------------------------------------
| Product Permissions
|--------------------------------------------------------------------------
|
| products.view
|
|     View merchant products.
|
| products.create
|
|     Create new merchant products.
|
| products.edit
|
|     Edit existing merchant products.
|
| products.delete
|
|     Delete merchant products.
|
| inventory.view
|
|     View product inventory information.
|
| inventory.manage
|
|     Modify inventory information.
|
*/

function storefleet_get_staff_permission_matrix()
{
    return [

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        |
        | Full merchant operational access within assigned branches.
        |
        */

        'manager' => [

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',
            'products.create',
            'products.edit',
            'products.delete',


            /*
            |--------------------------------------------------------------------------
            | Orders
            |--------------------------------------------------------------------------
            */

            'orders.view',
            'orders.manage',


            /*
            |--------------------------------------------------------------------------
            | POS
            |--------------------------------------------------------------------------
            */

            'pos.use',


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            'inventory.view',
            'inventory.manage',


            /*
            |--------------------------------------------------------------------------
            | Staff
            |--------------------------------------------------------------------------
            */

            'staff.view',


            /*
            |--------------------------------------------------------------------------
            | Delivery
            |--------------------------------------------------------------------------
            */

            'delivery.view',
            'delivery.manage',
        ],


        /*
        |--------------------------------------------------------------------------
        | Branch Manager
        |--------------------------------------------------------------------------
        |
        | Full operational access within assigned branches.
        |
        */

        'branch_manager' => [

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',
            'products.create',
            'products.edit',
            'products.delete',


            /*
            |--------------------------------------------------------------------------
            | Orders
            |--------------------------------------------------------------------------
            */

            'orders.view',
            'orders.manage',


            /*
            |--------------------------------------------------------------------------
            | POS
            |--------------------------------------------------------------------------
            */

            'pos.use',


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            'inventory.view',
            'inventory.manage',


            /*
            |--------------------------------------------------------------------------
            | Staff
            |--------------------------------------------------------------------------
            */

            'staff.view',


            /*
            |--------------------------------------------------------------------------
            | Delivery
            |--------------------------------------------------------------------------
            */

            'delivery.view',
            'delivery.manage',
        ],


        /*
        |--------------------------------------------------------------------------
        | Cashier
        |--------------------------------------------------------------------------
        |
        | Cashier can:
        |
        | - view products
        | - use wePOS
        | - view inventory
        |
        | Cashier cannot:
        |
        | - create products
        | - edit products
        | - delete products
        |
        */

        'cashier' => [

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',


            /*
            |--------------------------------------------------------------------------
            | POS
            |--------------------------------------------------------------------------
            */

            'pos.use',


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            'inventory.view',
        ],


        /*
        |--------------------------------------------------------------------------
        | Inventory Staff
        |--------------------------------------------------------------------------
        |
        | Inventory Staff can:
        |
        | - view products
        | - create products
        | - edit products
        | - view inventory
        | - manage inventory
        |
        | Inventory Staff cannot:
        |
        | - delete products
        |
        */

        'inventory_staff' => [

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',
            'products.create',
            'products.edit',


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            'inventory.view',
            'inventory.manage',
        ],


        /*
        |--------------------------------------------------------------------------
        | Order Staff
        |--------------------------------------------------------------------------
        |
        | Order Staff can reference products while processing orders.
        |
        | They cannot mutate the product catalog.
        |
        */

        'order_staff' => [

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',


            /*
            |--------------------------------------------------------------------------
            | Orders
            |--------------------------------------------------------------------------
            */

            'orders.view',
            'orders.manage',


            /*
            |--------------------------------------------------------------------------
            | Delivery
            |--------------------------------------------------------------------------
            */

            'delivery.view',
        ],


        /*
        |--------------------------------------------------------------------------
        | Delivery Staff
        |--------------------------------------------------------------------------
        |
        | Merchant-owned in-house rider.
        |
        | Lalamove remains an external delivery provider.
        |
        */

        'delivery_staff' => [

            /*
            |--------------------------------------------------------------------------
            | Delivery
            |--------------------------------------------------------------------------
            */

            'delivery.view',
            'delivery.rider',
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| Get Permissions For One Role
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_permissions(
    $staff_role
) {
    $staff_role =
        sanitize_key(
            $staff_role
        );

    if ($staff_role === '') {
        return [];
    }

    $matrix =
        storefleet_get_staff_permission_matrix();

    if (
        !isset(
            $matrix[$staff_role]
        )
    ) {
        return [];
    }

    return
        $matrix[$staff_role];
}


/*
|--------------------------------------------------------------------------
| One Role Has Permission
|--------------------------------------------------------------------------
*/

function storefleet_staff_role_has_permission(
    $staff_role,
    $permission
) {
    $staff_role =
        sanitize_key(
            $staff_role
        );

    $permission =
        sanitize_text_field(
            $permission
        );

    if (
        $staff_role === ''
        ||
        $permission === ''
    ) {
        return false;
    }

    return
        in_array(
            $permission,
            storefleet_get_staff_role_permissions(
                $staff_role
            ),
            true
        );
}


/*
|--------------------------------------------------------------------------
| Get All Effective Staff Permissions
|--------------------------------------------------------------------------
|
| Example:
|
| Cashier:
|
|   products.view
|   pos.use
|   inventory.view
|
| +
|
| Inventory Staff:
|
|   products.view
|   products.create
|   products.edit
|   inventory.view
|   inventory.manage
|
| =
|
| Effective:
|
|   products.view
|   products.create
|   products.edit
|   pos.use
|   inventory.view
|   inventory.manage
|
*/

function storefleet_get_staff_permissions(
    $staff_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return [];
    }

    if (
        !function_exists(
            'storefleet_get_staff_roles_for_staff'
        )
    ) {
        return [];
    }

    $role_keys =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    if (
        empty(
            $role_keys
        )
    ) {
        return [];
    }

    $permissions =
        [];

    foreach (
        $role_keys as $role_key
    ) {
        $permissions =
            array_merge(
                $permissions,
                storefleet_get_staff_role_permissions(
                    $role_key
                )
            );
    }

    $permissions =
        array_values(
            array_unique(
                $permissions
            )
        );

    sort(
        $permissions
    );

    return
        $permissions;
}


/*
|--------------------------------------------------------------------------
| Get Current Staff Effective Permissions
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_permissions()
{
    if (
        !function_exists(
            'storefleet_get_current_staff_id'
        )
    ) {
        return [];
    }

    $staff_id =
        storefleet_get_current_staff_id();

    if (!$staff_id) {
        return [];
    }

    return
        storefleet_get_staff_permissions(
            $staff_id
        );
}


/*
|--------------------------------------------------------------------------
| Find Roles That Grant A Permission
|--------------------------------------------------------------------------
|
| This preserves same-role branch authorization.
|
| Example:
|
| Employee:
|
| Cashier
|     Makati
|     BGC
|
| Inventory Staff
|     Makati
|
| Request:
|
| products.edit + BGC
|
| products.edit comes from Inventory Staff.
|
| Inventory Staff does not have BGC.
|
| Result:
|
| DENY
|
*/

function storefleet_get_staff_roles_for_permission(
    $staff_id,
    $permission
) {
    $staff_id =
        absint(
            $staff_id
        );

    $permission =
        sanitize_text_field(
            $permission
        );

    if (
        !$staff_id
        ||
        $permission === ''
    ) {
        return [];
    }

    if (
        !function_exists(
            'storefleet_get_staff_roles_for_staff'
        )
    ) {
        return [];
    }

    $role_keys =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    if (
        empty(
            $role_keys
        )
    ) {
        return [];
    }

    $matching_roles =
        [];

    foreach (
        $role_keys as $role_key
    ) {
        if (
            storefleet_staff_role_has_permission(
                $role_key,
                $permission
            )
        ) {
            $matching_roles[] =
                $role_key;
        }
    }

    return
        array_values(
            array_unique(
                $matching_roles
            )
        );
}


/*
|--------------------------------------------------------------------------
| Staff Record Has Permission
|--------------------------------------------------------------------------
|
| Without branch:
|
| At least one assigned StoreFleet role must grant the permission.
|
| With branch:
|
| At least one SAME StoreFleet role must:
|
| 1. grant the requested permission
| 2. grant access to the requested branch
|
*/

function storefleet_staff_has_permission(
    $staff,
    $permission,
    $branch_id = 0
) {
    /*
    |--------------------------------------------------------------------------
    | Valid Staff
    |--------------------------------------------------------------------------
    */

    if (!$staff) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        (int) $staff->is_active
        !== 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Staff ID
    |--------------------------------------------------------------------------
    */

    $staff_id =
        absint(
            $staff->id
            ?? 0
        );

    if (!$staff_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permission
    |--------------------------------------------------------------------------
    */

    $permission =
        sanitize_text_field(
            $permission
        );

    if ($permission === '') {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Roles Granting Requested Permission
    |--------------------------------------------------------------------------
    */

    $matching_roles =
        storefleet_get_staff_roles_for_permission(
            $staff_id,
            $permission
        );

    if (
        empty(
            $matching_roles
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | No Branch Scope
    |--------------------------------------------------------------------------
    */

    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Same Role Must Also Have Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_staff_role_can_access_branch'
        )
    ) {
        return false;
    }

    foreach (
        $matching_roles as $role_key
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
| Staff ID Has Permission
|--------------------------------------------------------------------------
*/

function storefleet_staff_id_has_permission(
    $staff_id,
    $permission,
    $branch_id = 0
) {
    $staff_id =
        absint(
            $staff_id
        );

    if (!$staff_id) {
        return false;
    }

    if (
        !function_exists(
            'storefleet_get_staff'
        )
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

    return
        storefleet_staff_has_permission(
            $staff,
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Current Staff Has Permission
|--------------------------------------------------------------------------
*/

function storefleet_current_staff_can(
    $permission,
    $branch_id = 0
) {
    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return false;
    }

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return false;
    }

    return
        storefleet_staff_has_permission(
            $staff,
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Current StoreFleet User Has Permission
|--------------------------------------------------------------------------
|
| Merchant owner:
|
| - operational access across their own merchant
| - branch-specific access only to branches they own
|
| Staff:
|
| - active account required
| - role must grant requested permission
| - same role must have access to requested branch
|
*/

function storefleet_current_user_can_storefleet(
    $permission,
    $branch_id = 0
) {
    if (!is_user_logged_in()) {
        return false;
    }

    $permission =
        sanitize_text_field(
            $permission
        );

    if ($permission === '') {
        return false;
    }

    $branch_id =
        absint(
            $branch_id
        );


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
        /*
        |--------------------------------------------------------------------------
        | Merchant Owner Without Branch Scope
        |--------------------------------------------------------------------------
        */

        if (!$branch_id) {
            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Requested Branch Must Belong To Merchant
        |--------------------------------------------------------------------------
        */

        if (
            !function_exists(
                'storefleet_merchant_owns_branch'
            )
            ||
            !function_exists(
                'storefleet_get_current_merchant_id'
            )
        ) {
            return false;
        }

        $merchant_id =
            absint(
                storefleet_get_current_merchant_id()
            );

        if (!$merchant_id) {
            return false;
        }

        return
            storefleet_merchant_owns_branch(
                $merchant_id,
                $branch_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    return
        storefleet_current_staff_can(
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Require StoreFleet Permission
|--------------------------------------------------------------------------
|
| Protected handlers should use this.
|
| UI visibility alone is NOT authorization.
|
*/

function storefleet_require_permission(
    $permission,
    $branch_id = 0
) {
    if (
        storefleet_current_user_can_storefleet(
            $permission,
            $branch_id
        )
    ) {
        return true;
    }

    wp_die(
        esc_html__(
            'You are not authorized to perform this StoreFleet action.',
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
| Can View StoreFleet Module
|--------------------------------------------------------------------------
*/

function storefleet_can_view_module(
    $permission,
    $branch_id = 0
) {
    return
        storefleet_current_user_can_storefleet(
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| In-House Rider Check
|--------------------------------------------------------------------------
|
| A staff account may have Delivery Staff plus other roles.
|
| The Delivery Staff role itself must:
|
| - grant delivery.rider
| - have access to requested branch
|
*/

function storefleet_staff_is_inhouse_rider(
    $staff,
    $branch_id = 0
) {
    if (!$staff) {
        return false;
    }

    if (
        (int) $staff->is_active
        !== 1
    ) {
        return false;
    }

    $staff_id =
        absint(
            $staff->id
            ?? 0
        );

    if (!$staff_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Must Have Delivery Staff Role
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_staff_has_role'
        )
        ||
        !storefleet_staff_has_role(
            $staff_id,
            'delivery_staff'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permission + Branch
    |--------------------------------------------------------------------------
    */

    return
        storefleet_staff_has_permission(
            $staff,
            'delivery.rider',
            $branch_id
        );
}