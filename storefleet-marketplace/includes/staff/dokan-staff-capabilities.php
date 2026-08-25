<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Staff ↔ Dokan Capability Bridge
|--------------------------------------------------------------------------
|
| Feature #30
|
| StoreFleet remains the source of truth for staff authorization.
|
| Staff remain:
|
| WordPress role:
|
|   storefleet_staff
|
| Staff do NOT receive:
|
|   dokandar
|   seller role
|   manage_woocommerce
|   withdrawal permissions
|   payout permissions
|   merchant settings permissions
|
|--------------------------------------------------------------------------
| Product Permission Model
|--------------------------------------------------------------------------
|
| StoreFleet                Dokan
|
| products.view      →      dokan_view_product_menu
| products.create    →      dokan_add_product
| products.edit      →      dokan_edit_product
| products.delete    →      dokan_delete_product
|
| Every product permission is evaluated against the CURRENT selected
| StoreFleet branch.
|
| Branch selection itself does NOT grant permission.
|
*/


/*
|--------------------------------------------------------------------------
| StoreFleet Permission → Dokan Capability Map
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_staff_capability_map()
{
    return [

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        'dokan_view_product_menu' =>
            'products.view',

        'dokan_add_product' =>
            'products.create',

        'dokan_edit_product' =>
            'products.edit',

        'dokan_delete_product' =>
            'products.delete',


        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        'dokan_view_order_menu' =>
            'orders.view',
    ];
}


/*
|--------------------------------------------------------------------------
| Branch-Scoped StoreFleet Permissions
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_permission_is_branch_scoped(
    $permission
) {
    return in_array(
        $permission,
        [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            'orders.view',
        ],
        true
    );
}


/*
|--------------------------------------------------------------------------
| Get Current Staff Product Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_current_dokan_staff_product_branch_id()
{
    if (
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return 0;
    }

    return
        absint(
            storefleet_get_current_staff_branch_id()
        );
}


/*
|--------------------------------------------------------------------------
| Current Dokan Merchant Can Sell
|--------------------------------------------------------------------------
|
| StoreFleet staff operate under the merchant owner context.
|
| Product creation should not bypass a disabled/suspended Dokan merchant.
|
*/

function storefleet_dokan_staff_merchant_can_sell()
{
    if (
        !function_exists(
            'dokan_get_current_user_id'
        )
    ) {
        return true;
    }

    $vendor_id =
        absint(
            dokan_get_current_user_id()
        );

    if (!$vendor_id) {
        return false;
    }

    if (
        !function_exists(
            'dokan_is_seller_enabled'
        )
    ) {
        return true;
    }

    return
        (bool) dokan_is_seller_enabled(
            $vendor_id
        );
}


/*
|--------------------------------------------------------------------------
| Get Current Staff Product Permission
|--------------------------------------------------------------------------
|
| Authorization:
|
| 1. logged-in StoreFleet staff
| 2. active staff record
| 3. active selected branch
| 4. StoreFleet permission
| 5. SAME role must grant permission + selected branch
|
*/

function storefleet_current_staff_can_product_permission(
    $permission
) {
    $permission =
        sanitize_text_field(
            $permission
        );

    if (
        $permission === ''
        ||
        !is_user_logged_in()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Record
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return false;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branch
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_get_current_dokan_staff_product_branch_id();

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Permission Engine
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_staff_has_permission'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permission + SAME Role Branch
    |--------------------------------------------------------------------------
    */

    return
        storefleet_staff_has_permission(
            $staff,
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Product Permission Helpers
|--------------------------------------------------------------------------
*/

function storefleet_staff_can_view_dokan_react_products()
{
    return
        storefleet_current_staff_can_product_permission(
            'products.view'
        );
}


function storefleet_staff_can_create_dokan_product()
{
    if (
        !storefleet_current_staff_can_product_permission(
            'products.create'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Must Still Be Allowed To Sell
    |--------------------------------------------------------------------------
    */

    return
        storefleet_dokan_staff_merchant_can_sell();
}


function storefleet_staff_can_edit_dokan_product()
{
    return
        storefleet_current_staff_can_product_permission(
            'products.edit'
        );
}


function storefleet_staff_can_delete_dokan_product()
{
    return
        storefleet_current_staff_can_product_permission(
            'products.delete'
        );
}


/*
|--------------------------------------------------------------------------
| Check StoreFleet Permission For Dokan Capability
|--------------------------------------------------------------------------
*/

function storefleet_staff_can_receive_dokan_capability(
    $user_id,
    $dokan_capability
) {
    $user_id =
        absint(
            $user_id
        );

    $dokan_capability =
        sanitize_key(
            $dokan_capability
        );

    if (
        !$user_id
        ||
        $dokan_capability === ''
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress User
    |--------------------------------------------------------------------------
    */

    $user =
        get_userdata(
            $user_id
        );

    if (
        !($user instanceof WP_User)
        ||
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Authenticated Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        $user_id !==
        get_current_user_id()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Capability Mapping
    |--------------------------------------------------------------------------
    */

    $map =
        storefleet_get_dokan_staff_capability_map();

    if (
        !isset(
            $map[$dokan_capability]
        )
    ) {
        return false;
    }

    $storefleet_permission =
        $map[$dokan_capability];


    /*
    |--------------------------------------------------------------------------
    | Staff Record
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_staff_by_user_id'
        )
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user_id
        );

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permission Engine
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_staff_has_permission'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Creation Also Requires Merchant Selling Status
    |--------------------------------------------------------------------------
    */

    if (
        $dokan_capability ===
        'dokan_add_product'
        &&
        !storefleet_dokan_staff_merchant_can_sell()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Account-Level Permission
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_dokan_staff_permission_is_branch_scoped(
            $storefleet_permission
        )
    ) {
        return
            storefleet_staff_has_permission(
                $staff,
                $storefleet_permission
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branch
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_get_current_dokan_staff_product_branch_id();

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permission + Branch
    |--------------------------------------------------------------------------
    |
    | StoreFleet guarantees that the SAME operational role must:
    |
    | - grant the permission
    | - grant access to the selected branch
    |
    */

    return
        storefleet_staff_has_permission(
            $staff,
            $storefleet_permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Dynamic Dokan Capabilities
|--------------------------------------------------------------------------
|
| These are calculated at runtime.
|
| Nothing is permanently written to the StoreFleet staff WordPress user.
|
*/

add_filter(
    'user_has_cap',
    'storefleet_dynamic_dokan_staff_capabilities',
    15,
    4
);


function storefleet_dynamic_dokan_staff_capabilities(
    $allcaps,
    $caps,
    $args,
    $user
) {
    /*
    |--------------------------------------------------------------------------
    | Valid WP User
    |--------------------------------------------------------------------------
    */

    if (
        !($user instanceof WP_User)
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | Supported Dokan Capabilities
    |--------------------------------------------------------------------------
    */

    $supported_caps =
        array_keys(
            storefleet_get_dokan_staff_capability_map()
        );


    /*
    |--------------------------------------------------------------------------
    | Resolve Requested Capability
    |--------------------------------------------------------------------------
    */

    foreach (
        $supported_caps as $dokan_capability
    ) {
        if (
            !in_array(
                $dokan_capability,
                (array) $caps,
                true
            )
        ) {
            continue;
        }

        $allcaps[
            $dokan_capability
        ] =
            storefleet_staff_can_receive_dokan_capability(
                $user->ID,
                $dokan_capability
            );
    }

    return
        $allcaps;
}


/*
|--------------------------------------------------------------------------
| Dokan React Product Permission Bridge
|--------------------------------------------------------------------------
|
| Dokan's React router uses:
|
| dokan-products
|     /products
|
| dokan-product-editor-create
|     /products/create
|
| dokan-product-editor-edit
|     /products/:productId/edit
|
| Dokan itself uses dokan_view_product_menu on all three routes.
|
| StoreFleet needs finer authorization:
|
| /products
|     products.view
|
| /products/create
|     products.create
|
| /products/:id/edit
|     products.edit
|
| Delete is controlled separately by products.delete.
|
*/

add_action(
    'wp_enqueue_scripts',
    'storefleet_bridge_dokan_react_staff_product_permissions',
    120
);


function storefleet_bridge_dokan_react_staff_product_permissions()
{
    /*
    |--------------------------------------------------------------------------
    | Logged-In StoreFleet Staff
    |--------------------------------------------------------------------------
    */

    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | React Dashboard Request
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_is_dokan_react_dashboard_request'
        )
        ||
        !storefleet_is_dokan_react_dashboard_request()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Must Be Able To View Products
    |--------------------------------------------------------------------------
    */

    $can_view =
        storefleet_staff_can_view_dokan_react_products();

    if (!$can_view) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Branch Permissions
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_get_current_dokan_staff_product_branch_id();

    $can_create =
        storefleet_staff_can_create_dokan_product();

    $can_edit =
        storefleet_staff_can_edit_dokan_product();

    $can_delete =
        storefleet_staff_can_delete_dokan_product();


    /*
    |--------------------------------------------------------------------------
    | Ensure Dokan React Bundle
    |--------------------------------------------------------------------------
    */

    if (
        !wp_script_is(
            'dokan-react-frontend',
            'enqueued'
        )
    ) {
        wp_enqueue_script(
            'dokan-react-frontend'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Permission Payload
    |--------------------------------------------------------------------------
    */

    $permissions =
        [
            'branchId' =>
                $branch_id,

            'view' =>
                (bool) $can_view,

            'create' =>
                (bool) $can_create,

            'edit' =>
                (bool) $can_edit,

            'delete' =>
                (bool) $can_delete,
        ];


    /*
    |--------------------------------------------------------------------------
    | Expose Permission Payload Before Dokan React
    |--------------------------------------------------------------------------
    */

    wp_add_inline_script(
        'dokan-react-frontend',
        'window.storefleetDokanProductPermissions = ' .
        wp_json_encode(
            $permissions
        ) .
        ';',
        'before'
    );


    /*
    |--------------------------------------------------------------------------
    | React Compatibility Bridge
    |--------------------------------------------------------------------------
    |
    | This modifies UI routing/visibility only.
    |
    | Server-side authorization remains the StoreFleet permission engine +
    | dynamic Dokan capabilities above.
    |
    */

    $script = <<<'JS'
(function () {
    'use strict';

    if (
        !window.storefleetDokanProductPermissions
    ) {
        return;
    }

    var permissions =
        window.storefleetDokanProductPermissions;


    /*
    |--------------------------------------------------------------------------
    | Synchronize Dokan Product Listing UI
    |--------------------------------------------------------------------------
    |
    | Dokan's modern product list only builds the Add New Product button when:
    |
    | window.dokanFrontend.product_listing.can_add_product
    |
    | is true.
    |
    | For StoreFleet staff that value can be false because the employee is not
    | a normal Dokan seller account.
    |
    | StoreFleet has already authorized products.create server-side, so expose
    | that result to the Dokan listing UI.
    |
    */

    window.dokanFrontend =
        window.dokanFrontend || {};

    window.dokanFrontend.product_listing =
        window.dokanFrontend.product_listing || {};

    window.dokanFrontend.product_listing.can_add_product =
        !!permissions.create;


    /*
    |--------------------------------------------------------------------------
    | WordPress Hooks Required
    |--------------------------------------------------------------------------
    */

    if (
        !window.wp ||
        !window.wp.hooks ||
        typeof window.wp.hooks.addFilter !== 'function'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | React Route Authorization
    |--------------------------------------------------------------------------
    */

    window.wp.hooks.addFilter(
        'dokan-dashboard-routes',
        'storefleet/staff-product-routes',
        function (routes) {
            if (!Array.isArray(routes)) {
                return routes;
            }

            return routes.map(
                function (route) {
                    if (!route) {
                        return route;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Products List
                    |--------------------------------------------------------------------------
                    */

                    if (
                        route.id === 'dokan-products'
                    ) {
                        return Object.assign(
                            {},
                            route,
                            {
                                capabilities:
                                    permissions.view
                                        ? []
                                        : [
                                            'storefleet_products_view_denied'
                                        ]
                            }
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Add New Product
                    |--------------------------------------------------------------------------
                    */

                    if (
                        route.id ===
                        'dokan-product-editor-create'
                    ) {
                        return Object.assign(
                            {},
                            route,
                            {
                                capabilities:
                                    permissions.create
                                        ? []
                                        : [
                                            'storefleet_products_create_denied'
                                        ]
                            }
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Edit Product
                    |--------------------------------------------------------------------------
                    */

                    if (
                        route.id ===
                        'dokan-product-editor-edit'
                    ) {
                        return Object.assign(
                            {},
                            route,
                            {
                                capabilities:
                                    permissions.edit
                                        ? []
                                        : [
                                            'storefleet_products_edit_denied'
                                        ]
                            }
                        );
                    }

                    return route;
                }
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Product Table Actions
    |--------------------------------------------------------------------------
    |
    | Keep non-mutating actions such as:
    |
    | - quick view
    | - view in site
    |
    | Hide mutation actions when StoreFleet permission is missing.
    |
    */

    window.wp.hooks.addFilter(
        'dokan_product_list_table_actions',
        'storefleet/staff-product-actions',
        function (actions) {
            if (!Array.isArray(actions)) {
                return actions;
            }

            return actions.filter(
                function (action) {
                    if (
                        !action ||
                        !action.id
                    ) {
                        return true;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Edit
                    |--------------------------------------------------------------------------
                    */

                    if (
                        action.id === 'edit-details'
                        &&
                        !permissions.edit
                    ) {
                        return false;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Delete
                    |--------------------------------------------------------------------------
                    */

                    if (
                        action.id === 'delete'
                        &&
                        !permissions.delete
                    ) {
                        return false;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Publish / Status Mutation
                    |--------------------------------------------------------------------------
                    |
                    | Treat publishing as product editing.
                    |
                    */

                    if (
                        action.id === 'bulk-publish'
                        &&
                        !permissions.edit
                    ) {
                        return false;
                    }

                    return true;
                }
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Add New Product Header Button
    |--------------------------------------------------------------------------
    |
    | Dokan builds the button first, then passes it through this filter.
    |
    | If StoreFleet does not grant products.create, remove the button.
    |
    */

    window.wp.hooks.addFilter(
        'dokan_product_list_header_buttons',
        'storefleet/staff-product-create-button',
        function (buttons) {
            if (!permissions.create) {
                return [];
            }

            return buttons;
        }
    );

})();
JS;


    /*
    |--------------------------------------------------------------------------
    | Execute Before Dokan React Bundle
    |--------------------------------------------------------------------------
    */

    wp_add_inline_script(
        'dokan-react-frontend',
        $script,
        'before'
    );
}