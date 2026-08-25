<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Staff ↔ wePOS Integration
|--------------------------------------------------------------------------
|
| Feature #30
|
| StoreFleet employees remain:
|
| WordPress role:
|   storefleet_staff
|
| They do NOT become:
|
|   vendor
|   seller
|   vendor_staff role
|   dokandar
|
| wePOS normally detects Dokan staff by checking the WordPress role
| "vendor_staff".
|
| StoreFleet instead bridges its own staff authorization into wePOS using:
|
| - wepos_is_vendor_staff
| - access_wepos
| - _vendor_id
|
| StoreFleet remains the source of truth for:
|
| - staff status
| - merchant ownership
| - StoreFleet role
| - pos.use permission
| - active StoreFleet branch
|
*/


/*
|--------------------------------------------------------------------------
| Get Real wePOS Frontend URL
|--------------------------------------------------------------------------
*/

function storefleet_get_wepos_frontend_url()
{
    return
        untrailingslashit(
            get_site_url()
        ) .
        '/wepos/#';
}


/*
|--------------------------------------------------------------------------
| Prepare StoreFleet Staff wePOS Context
|--------------------------------------------------------------------------
|
| The Dokan staff dashboard already synchronizes StoreFleet staff to their
| merchant owner through _vendor_id.
|
| wePOS runs from its own frontend and REST requests, so those requests may
| happen without first rendering the Dokan staff dashboard shell.
|
| Synchronize the existing StoreFleet → Dokan merchant context early for the
| current request. This does NOT grant dokandar or change the WordPress role.
|
*/

function storefleet_prepare_staff_wepos_context()
{
    static $prepared =
        false;

    if ($prepared) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return;
    }

    $merchant_id =
        absint(
            $staff->merchant_id
            ?? 0
        );

    if (!$merchant_id) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Reuse Existing StoreFleet Dokan Context Synchronizer
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_sync_staff_dokan_context'
        )
    ) {
        return;
    }

    storefleet_sync_staff_dokan_context(
        get_current_user_id()
    );

    $prepared =
        true;
}


/*
|--------------------------------------------------------------------------
| Synchronize Context For wePOS Frontend + REST
|--------------------------------------------------------------------------
|
| wp_loaded covers normal frontend requests.
|
| rest_api_init provides an explicit REST safety hook because wePOS loads its
| products and operational data through WordPress REST endpoints.
|
*/

add_action(
    'wp_loaded',
    'storefleet_prepare_staff_wepos_context',
    5
);

add_action(
    'rest_api_init',
    'storefleet_prepare_staff_wepos_context',
    5
);


/*
|--------------------------------------------------------------------------
| Is StoreFleet Staff Eligible For POS
|--------------------------------------------------------------------------
|
| POS requires:
|
| 1. StoreFleet staff account
| 2. active staff
| 3. active StoreFleet branch
| 4. pos.use granted for THAT branch
|
*/

function storefleet_staff_can_access_wepos(
    $user_id = 0
) {
    $user_id =
        $user_id
            ? absint(
                $user_id
            )
            : get_current_user_id();

    if (!$user_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff WordPress Role
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
    | StoreFleet Staff Record
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
    | Current Logged-In User Only
    |--------------------------------------------------------------------------
    |
    | Branch context belongs to the current staff dashboard session.
    |
    */

    if (
        $user_id !==
        get_current_user_id()
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Active StoreFleet Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return false;
    }

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet POS Permission + Branch
    |--------------------------------------------------------------------------
    |
    | SAME role must grant:
    |
    | pos.use
    |
    | and access to the current branch.
    |
    */

    if (
        !function_exists(
            'storefleet_staff_has_permission'
        )
    ) {
        return false;
    }

    return
        storefleet_staff_has_permission(
            $staff,
            'pos.use',
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Filter wePOS Products For StoreFleet Staff
|--------------------------------------------------------------------------
|
| wePOS fetches its POS catalogue through:
|
|     wepos/v1/products
|
| and exposes the final WP_Query arguments through:
|
|     wepos_rest_product_query_args
|
| Dokan's normal vendor filter is attached to WooCommerce's generic REST
| product hook, so StoreFleet explicitly applies the operational merchant
| and current branch context here.
|
| Rules:
|
| 1. StoreFleet staff only.
| 2. The current employee must have pos.use for the selected branch.
| 3. Products must belong to the employee's merchant.
| 4. Products must be available at the selected StoreFleet branch.
|
*/

add_filter(
    'wepos_rest_product_query_args',
    'storefleet_filter_staff_wepos_products',
    20,
    2
);


function storefleet_filter_staff_wepos_products(
    $args,
    $request
) {
    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
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
        return $args;
    }


    /*
    |--------------------------------------------------------------------------
    | POS Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_wepos()
    ) {
        $args['post__in'] = [0];

        return $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Staff + Merchant + Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
        ||
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        $args['post__in'] = [0];

        return $args;
    }

    $staff =
        storefleet_get_current_staff();

    $merchant_id =
        $staff
            ? absint(
                $staff->merchant_id
                ?? 0
            )
            : 0;

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (
        !$merchant_id
        ||
        !$branch_id
    ) {
        $args['post__in'] = [0];

        return $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Product Ownership
    |--------------------------------------------------------------------------
    */

    $args['author'] =
        $merchant_id;


    /*
    |--------------------------------------------------------------------------
    | Product Branch Helper Required
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_product_is_available_at_branch'
        )
    ) {
        $args['post__in'] = [0];

        return $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Product IDs
    |--------------------------------------------------------------------------
    |
    | Query IDs separately so we can apply StoreFleet's existing product
    | branch availability helper before the final wePOS REST query runs.
    |
    */

    $merchant_product_ids =
        get_posts(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'author' =>
                    $merchant_id,

                'posts_per_page' =>
                    -1,

                'fields' =>
                    'ids',

                'orderby' =>
                    'ID',

                'order' =>
                    'ASC',

                'no_found_rows' =>
                    true,

                'update_post_meta_cache' =>
                    false,

                'update_post_term_cache' =>
                    false,
            ]
        );

    $merchant_product_ids =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $merchant_product_ids
                    )
                )
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Current Branch Product IDs
    |--------------------------------------------------------------------------
    */

    $branch_product_ids =
        [];

    foreach (
        $merchant_product_ids as $product_id
    ) {
        if (
            storefleet_product_is_available_at_branch(
                $product_id,
                $branch_id
            )
        ) {
            $branch_product_ids[] =
                $product_id;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve Existing wePOS Filters
    |--------------------------------------------------------------------------
    |
    | Low-stock and other filters may already have populated post__in.
    | Intersect with them instead of overwriting them.
    |
    */

    if (
        isset(
            $args['post__in']
        )
        &&
        is_array(
            $args['post__in']
        )
        &&
        !empty(
            $args['post__in']
        )
    ) {
        $existing_ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'absint',
                            $args['post__in']
                        )
                    )
                )
            );

        $branch_product_ids =
            array_values(
                array_intersect(
                    $existing_ids,
                    $branch_product_ids
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Fail Closed When Branch Has No Products
    |--------------------------------------------------------------------------
    */

    $args['post__in'] =
        !empty(
            $branch_product_ids
        )
            ? $branch_product_ids
            : [0];

    return $args;
}



/*
|--------------------------------------------------------------------------
| Allow StoreFleet Staff To Read Authorized Products In WooCommerce REST
|--------------------------------------------------------------------------
|
| WooCommerce's REST product response preparation checks:
|
|     wc_rest_check_post_permissions( 'product', 'read', $product_id )
|
| For products, that resolves to the post type's read_private_posts
| capability. StoreFleet employees intentionally do not receive broad
| WooCommerce product-management capabilities, so the default check can fail
| even when StoreFleet has already authorized the product for the employee.
|
| This filter provides a narrow read-only exception.
|
| It does NOT grant:
|
| - manage_woocommerce
| - edit_products
| - publish_products
| - delete_products
| - dokandar
| - seller/vendor roles
|
| The product must still:
|
| 1. belong to the employee's merchant,
| 2. be available at the employee's current branch,
| 3. be visible to a staff role with products.view,
| 4. be accessed by an employee who can use POS for that branch.
|
*/

add_filter(
    'woocommerce_rest_check_permissions',
    'storefleet_allow_staff_wepos_product_rest_read',
    100,
    4
);


function storefleet_allow_staff_wepos_product_rest_read(
    $permission,
    $context,
    $object_id,
    $object_type
) {
    /*
    |--------------------------------------------------------------------------
    | Preserve Existing Allowed Requests
    |--------------------------------------------------------------------------
    */

    if ($permission) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Read-Only Product Objects
    |--------------------------------------------------------------------------
    */

    if (
        $context !== 'read'
        ||
        !in_array(
            $object_type,
            [
                'product',
                'product_variation',
            ],
            true
        )
    ) {
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
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
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | Ensure Existing Merchant Context Is Prepared
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_prepare_staff_wepos_context'
        )
    ) {
        storefleet_prepare_staff_wepos_context();
    }


    /*
    |--------------------------------------------------------------------------
    | Current Staff + Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
        ||
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return $permission;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return $permission;
    }

    $merchant_id =
        absint(
            $staff->merchant_id
            ?? 0
        );

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (
        !$merchant_id
        ||
        !$branch_id
    ) {
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | Require POS + Product View For This Branch
    |--------------------------------------------------------------------------
    |
    | POS access remains independently authorized by pos.use.
    |
    | products.view is also required so the REST exception cannot become a
    | generic product-reading bypass for staff without product visibility.
    |
    */

    if (
        !storefleet_staff_can_access_wepos()
        ||
        !function_exists(
            'storefleet_current_staff_can'
        )
        ||
        !storefleet_current_staff_can(
            'products.view',
            $branch_id
        )
    ) {
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Product
    |--------------------------------------------------------------------------
    */

    $object_id =
        absint(
            $object_id
        );

    if (!$object_id) {
        return $permission;
    }

    $product_post =
        get_post(
            $object_id
        );

    if (
        !($product_post instanceof WP_Post)
        ||
        !in_array(
            $product_post->post_type,
            [
                'product',
                'product_variation',
            ],
            true
        )
    ) {
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Parent Product For Variations
    |--------------------------------------------------------------------------
    */

    $product_id =
        $object_id;

    if (
        $product_post->post_type ===
        'product_variation'
    ) {
        $product_id =
            absint(
                $product_post->post_parent
            );

        if (!$product_id) {
            return $permission;
        }

        $product_post =
            get_post(
                $product_id
            );

        if (
            !($product_post instanceof WP_Post)
            ||
            $product_post->post_type !==
            'product'
        ) {
            return $permission;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Ownership
    |--------------------------------------------------------------------------
    */

    if (
        absint(
            $product_post->post_author
        )
        !==
        $merchant_id
    ) {
        return $permission;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Branch Availability
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_product_is_available_at_branch'
        )
        ||
        !storefleet_product_is_available_at_branch(
            $product_id,
            $branch_id
        )
    ) {
        return $permission;
    }

    return true;
}



/*
|--------------------------------------------------------------------------
| Tell wePOS StoreFleet Staff Is Vendor Staff
|--------------------------------------------------------------------------
|
| wePOS's own Dokan integration checks:
|
| apply_filters(
|     'wepos_is_vendor_staff',
|     false
| )
|
| Its default callback checks specifically for the "vendor_staff" role.
|
| StoreFleet deliberately uses "storefleet_staff", so we extend that check
| here without changing the actual WordPress role.
|
*/

add_filter(
    'wepos_is_vendor_staff',
    'storefleet_wepos_is_vendor_staff',
    100,
    2
);


function storefleet_wepos_is_vendor_staff(
    $is_staff,
    $user_id = null
) {
    $user_id =
        $user_id
            ? absint(
                $user_id
            )
            : get_current_user_id();

    if (!$user_id) {
        return $is_staff;
    }

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
        return $is_staff;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Controls Authorization
    |--------------------------------------------------------------------------
    */

    return
        storefleet_staff_can_access_wepos(
            $user_id
        );
}


/*
|--------------------------------------------------------------------------
| Dynamically Grant access_wepos
|--------------------------------------------------------------------------
|
| Do NOT permanently grant broad WooCommerce / Dokan capabilities.
|
| access_wepos is made available dynamically only when StoreFleet says:
|
| pos.use + current branch = allowed
|
| Priority 15 is intentional.
|
| wePOS itself applies its vendor → staff capability cascade later at
| priority 20. Therefore if the merchant/vendor has had POS access revoked,
| wePOS can still remove the capability afterward.
|
*/

add_filter(
    'user_has_cap',
    'storefleet_wepos_dynamic_access_capability',
    15,
    4
);


function storefleet_wepos_dynamic_access_capability(
    $allcaps,
    $caps,
    $args,
    $user
) {
    if (
        !($user instanceof WP_User)
    ) {
        return $allcaps;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Handle access_wepos Checks
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'access_wepos',
            (array) $caps,
            true
        )
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
    | Grant / Deny From StoreFleet Permission Engine
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_staff_can_access_wepos(
            $user->ID
        )
    ) {
        $allcaps[
            'access_wepos'
        ] = true;
    } else {
        $allcaps[
            'access_wepos'
        ] = false;
    }

    return $allcaps;
}


/*
|--------------------------------------------------------------------------
| Replace StoreFleet POS Menu With Real wePOS
|--------------------------------------------------------------------------
|
| Our StoreFleet navigation filter runs at priority 9999.
|
| This filter runs afterward and replaces:
|
| StoreFleet POS placeholder
|
| with:
|
| real wePOS frontend
|
*/

add_filter(
    'dokan_get_dashboard_nav',
    'storefleet_replace_staff_pos_with_wepos',
    10000
);


function storefleet_replace_staff_pos_with_wepos(
    $navigation
) {
    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return $navigation;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove StoreFleet Placeholder POS
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $navigation[
                'storefleet-pos'
            ]
        )
    ) {
        unset(
            $navigation[
                'storefleet-pos'
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | No POS Permission For Current Branch
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_wepos()
    ) {
        return $navigation;
    }


    /*
    |--------------------------------------------------------------------------
    | Add Real wePOS Menu
    |--------------------------------------------------------------------------
    */

    $navigation[
        'storefleet-wepos'
    ] = [
        'title' =>
            'wePOS',

        'icon' =>
            '<i class="fas fa-cash-register"></i>',

        'url' =>
            storefleet_get_wepos_frontend_url(),

        'pos' =>
            20,

        'submenu' => [
            'storefleet-view-pos' => [
                'title' =>
                    'View POS',

                'icon' =>
                    '<i class="fas fa-desktop"></i>',

                'url' =>
                    storefleet_get_wepos_frontend_url(),

                'pos' =>
                    10,

                'target' =>
                    '_blank',
            ],
        ],
    ];


    /*
    |--------------------------------------------------------------------------
    | Preserve Navigation Ordering
    |--------------------------------------------------------------------------
    */

    uasort(
        $navigation,
        function (
            $item_a,
            $item_b
        ) {
            return
                absint(
                    $item_a['pos']
                    ?? 999
                )
                <=>
                absint(
                    $item_b['pos']
                    ?? 999
                );
        }
    );

    return $navigation;
}


/*
|--------------------------------------------------------------------------
| Redirect Old StoreFleet POS Placeholder
|--------------------------------------------------------------------------
|
| Existing links may still point to:
|
| /dashboard/storefleet-pos/
|
| Redirect authorized employees to the real wePOS application.
|
*/

add_action(
    'template_redirect',
    'storefleet_redirect_staff_pos_to_wepos',
    12
);


function storefleet_redirect_staff_pos_to_wepos()
{
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

    if (
        !function_exists(
            'storefleet_get_current_dokan_staff_route'
        )
    ) {
        return;
    }

    $route =
        storefleet_get_current_dokan_staff_route();

    if (
        $route !==
        'storefleet-pos'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Server-Side StoreFleet Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_wepos()
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to use StoreFleet POS for the selected branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet POS Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Real POS
    |--------------------------------------------------------------------------
    */

    wp_safe_redirect(
        storefleet_get_wepos_frontend_url()
    );

    exit;
}