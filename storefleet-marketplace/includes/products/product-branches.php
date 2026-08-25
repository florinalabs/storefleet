<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Product Branch Availability
|--------------------------------------------------------------------------
|
| Product branch availability answers:
|
|     "Which branches sell this product?"
|
| It is separate from branch inventory:
|
|     "How much stock does this branch have?"
|
|--------------------------------------------------------------------------
| Modes
|--------------------------------------------------------------------------
|
| all
|
|     Product is available at every active branch belonging to the merchant.
|
| selected
|
|     Product is available only at explicitly selected branches stored in:
|
|     wp_storefleet_product_branches
|
|--------------------------------------------------------------------------
| Existing Products
|--------------------------------------------------------------------------
|
| Products without _storefleet_branch_mode are treated as:
|
|     all
|
| This preserves backwards compatibility.
|
*/


/*
|--------------------------------------------------------------------------
| Product Branch Table
|--------------------------------------------------------------------------
*/

function storefleet_product_branches_table()
{
    global $wpdb;

    return
        $wpdb->prefix .
        'storefleet_product_branches';
}


/*
|--------------------------------------------------------------------------
| Product Branch Mode Meta Key
|--------------------------------------------------------------------------
*/

function storefleet_product_branch_mode_meta_key()
{
    return
        '_storefleet_branch_mode';
}


/*
|--------------------------------------------------------------------------
| Normalize Product Branch Mode
|--------------------------------------------------------------------------
*/

function storefleet_normalize_product_branch_mode(
    $mode
) {
    $mode =
        sanitize_key(
            $mode
        );

    if (
        !in_array(
            $mode,
            [
                'all',
                'selected',
            ],
            true
        )
    ) {
        return
            'all';
    }

    return
        $mode;
}


/*
|--------------------------------------------------------------------------
| Valid WooCommerce Product
|--------------------------------------------------------------------------
*/

function storefleet_is_valid_product(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (!$product_id) {
        return false;
    }

    return
        get_post_type(
            $product_id
        ) === 'product';
}


/*
|--------------------------------------------------------------------------
| Get Product Merchant ID
|--------------------------------------------------------------------------
|
| Dokan products are owned by the vendor through post_author.
|
| StoreFleet merchant IDs currently match the merchant/vendor WordPress
| user ID.
|
*/

function storefleet_get_product_merchant_id(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_is_valid_product(
            $product_id
        )
    ) {
        return 0;
    }

    return
        absint(
            get_post_field(
                'post_author',
                $product_id
            )
        );
}


/*
|--------------------------------------------------------------------------
| Product Belongs To Merchant
|--------------------------------------------------------------------------
*/

function storefleet_merchant_owns_product(
    $merchant_id,
    $product_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );

    $product_id =
        absint(
            $product_id
        );

    if (
        !$merchant_id
        ||
        !$product_id
    ) {
        return false;
    }

    return
        storefleet_get_product_merchant_id(
            $product_id
        ) ===
        $merchant_id;
}


/*
|--------------------------------------------------------------------------
| Get Product Branch Mode
|--------------------------------------------------------------------------
|
| Missing meta defaults to "all".
|
*/

function storefleet_get_product_branch_mode(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_is_valid_product(
            $product_id
        )
    ) {
        return
            'all';
    }

    $mode =
        get_post_meta(
            $product_id,
            storefleet_product_branch_mode_meta_key(),
            true
        );

    if ($mode === '') {
        return
            'all';
    }

    return
        storefleet_normalize_product_branch_mode(
            $mode
        );
}


/*
|--------------------------------------------------------------------------
| Get Explicit Product Branch IDs
|--------------------------------------------------------------------------
|
| These rows matter only when mode = selected.
|
*/

function storefleet_get_product_branch_ids(
    $product_id
) {
    global $wpdb;

    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_is_valid_product(
            $product_id
        )
    ) {
        return [];
    }

    $table =
        storefleet_product_branches_table();

    $branch_ids =
        $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT branch_id
                FROM {$table}
                WHERE product_id = %d
                ORDER BY branch_id ASC
                ",
                $product_id
            )
        );

    return
        array_values(
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
| Get Merchant Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_product_merchant_branches(
    $merchant_id,
    $active_only = true
) {
    global $wpdb;

    $merchant_id =
        absint(
            $merchant_id
        );

    if (
        !$merchant_id
        ||
        !function_exists(
            'storefleet_branches_table'
        )
    ) {
        return [];
    }

    $branches_table =
        storefleet_branches_table();

    if ($active_only) {
        return
            $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$branches_table}
                    WHERE merchant_id = %d
                    AND is_active = 1
                    ORDER BY name ASC
                    ",
                    $merchant_id
                )
            );
    }

    return
        $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$branches_table}
                WHERE merchant_id = %d
                ORDER BY name ASC
                ",
                $merchant_id
            )
        );
}


/*
|--------------------------------------------------------------------------
| Get Merchant Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_get_product_merchant_branch_ids(
    $merchant_id,
    $active_only = true
) {
    $branches =
        storefleet_get_product_merchant_branches(
            $merchant_id,
            $active_only
        );

    if (
        empty(
            $branches
        )
    ) {
        return [];
    }

    return
        array_values(
            array_filter(
                array_map(
                    function (
                        $branch
                    ) {
                        return
                            absint(
                                $branch->id
                                ?? 0
                            );
                    },
                    $branches
                )
            )
        );
}


/*
|--------------------------------------------------------------------------
| Product Available At Branch
|--------------------------------------------------------------------------
*/

function storefleet_product_is_available_at_branch(
    $product_id,
    $branch_id
) {
    global $wpdb;

    $product_id =
        absint(
            $product_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$product_id
        ||
        !$branch_id
        ||
        !storefleet_is_valid_product(
            $product_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Merchant
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        storefleet_get_product_merchant_id(
            $product_id
        );

    if (!$merchant_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Must Belong To Same Merchant
    |--------------------------------------------------------------------------
    */

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
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | All Branches
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_get_product_branch_mode(
            $product_id
        ) === 'all'
    ) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branch
    |--------------------------------------------------------------------------
    */

    $table =
        storefleet_product_branches_table();

    $found =
        $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$table}
                WHERE product_id = %d
                AND branch_id = %d
                LIMIT 1
                ",
                $product_id,
                $branch_id
            )
        );

    return
        !empty(
            $found
        );
}


/*
|--------------------------------------------------------------------------
| Current User Owns Product Merchant Context
|--------------------------------------------------------------------------
*/

function storefleet_current_user_can_access_product_merchant(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !function_exists(
            'storefleet_get_current_operational_merchant_id'
        )
    ) {
        return false;
    }

    $current_merchant_id =
        absint(
            storefleet_get_current_operational_merchant_id()
        );

    if (!$current_merchant_id) {
        return false;
    }

    return
        storefleet_merchant_owns_product(
            $current_merchant_id,
            $product_id
        );
}


/*
|--------------------------------------------------------------------------
| Current User Can Manage Product Branch
|--------------------------------------------------------------------------
|
| Merchant owner:
|
|     Can manage every branch belonging to their merchant.
|
| Staff:
|
|     Must have products.branches.manage on the SAME role that grants access
|     to the requested branch.
|
*/

function storefleet_current_user_can_manage_product_branch(
    $product_id,
    $branch_id
) {
    $product_id =
        absint(
            $product_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$product_id
        ||
        !$branch_id
        ||
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Merchant
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        storefleet_get_product_merchant_id(
            $product_id
        );

    if (!$merchant_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Must Belong To Product Merchant
    |--------------------------------------------------------------------------
    */

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
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_current_staff_can'
        )
    ) {
        return false;
    }

    return
        storefleet_current_staff_can(
            'products.branches.manage',
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Get Current User Manageable Product Branches
|--------------------------------------------------------------------------
|
| Used by the future Dokan Product Editor branch selector.
|
*/

function storefleet_get_current_user_manageable_product_branches(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
        return [];
    }

    $merchant_id =
        storefleet_get_product_merchant_id(
            $product_id
        );

    if (!$merchant_id) {
        return [];
    }

    $branches =
        storefleet_get_product_merchant_branches(
            $merchant_id,
            true
        );

    if (
        empty(
            $branches
        )
    ) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owner Gets All Merchant Branches
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner()
    ) {
        return
            $branches;
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Gets Only Authorized Branches
    |--------------------------------------------------------------------------
    */

    $allowed =
        [];

    foreach (
        $branches as $branch
    ) {
        $branch_id =
            absint(
                $branch->id
                ?? 0
            );

        if (!$branch_id) {
            continue;
        }

        if (
            storefleet_current_user_can_manage_product_branch(
                $product_id,
                $branch_id
            )
        ) {
            $allowed[] =
                $branch;
        }
    }

    return
        $allowed;
}


/*
|--------------------------------------------------------------------------
| Current Staff Can Set Product To All Branches
|--------------------------------------------------------------------------
|
| Merchant Owner:
|
|     Always allowed for their own product.
|
| Manager:
|
|     Allowed only when:
|
|     - manager role exists
|     - products.branches.manage is granted
|     - manager role covers every currently active merchant branch
|
| Other staff roles should use explicit selected branches.
|
*/

function storefleet_current_user_can_set_product_all_branches(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
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
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
        ||
        !function_exists(
            'storefleet_staff_has_role'
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
    | Manager Role Required
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_has_role(
            $staff->id,
            'manager'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Manager Must Cover Every Active Merchant Branch
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        storefleet_get_product_merchant_id(
            $product_id
        );

    $branch_ids =
        storefleet_get_product_merchant_branch_ids(
            $merchant_id,
            true
        );

    if (
        empty(
            $branch_ids
        )
    ) {
        return false;
    }

    foreach (
        $branch_ids as $branch_id
    ) {
        if (
            !storefleet_current_user_can_manage_product_branch(
                $product_id,
                $branch_id
            )
        ) {
            return false;
        }
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Set Product Branches
|--------------------------------------------------------------------------
|
| Secure write helper.
|
| mode = all
|
|     Clears explicit product-branch rows.
|
| mode = selected
|
|     Requires at least one selected branch.
|
| Every branch is validated server-side.
|
| Returns:
|
| true
|
| or WP_Error.
|
*/

function storefleet_set_product_branches(
    $product_id,
    $mode,
    $branch_ids = []
) {
    global $wpdb;

    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !storefleet_is_valid_product(
            $product_id
        )
    ) {
        return
            new WP_Error(
                'storefleet_invalid_product',
                __(
                    'A valid product is required.',
                    'storefleet-marketplace'
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Product Merchant Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
        return
            new WP_Error(
                'storefleet_product_access_denied',
                __(
                    'You are not authorized to manage branches for this product.',
                    'storefleet-marketplace'
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Mode
    |--------------------------------------------------------------------------
    */

    $mode =
        storefleet_normalize_product_branch_mode(
            $mode
        );

    $table =
        storefleet_product_branches_table();


    /*
    |--------------------------------------------------------------------------
    | All Merchant Branches
    |--------------------------------------------------------------------------
    */

    if ($mode === 'all') {
        if (
            !storefleet_current_user_can_set_product_all_branches(
                $product_id
            )
        ) {
            return
                new WP_Error(
                    'storefleet_all_branches_denied',
                    __(
                        'You are not authorized to make this product available at all branches.',
                        'storefleet-marketplace'
                    )
                );
        }

        $wpdb->query(
            'START TRANSACTION'
        );

        $deleted =
            $wpdb->delete(
                $table,
                [
                    'product_id' =>
                        $product_id,
                ],
                [
                    '%d',
                ]
            );

        if ($deleted === false) {
            $wpdb->query(
                'ROLLBACK'
            );

            return
                new WP_Error(
                    'storefleet_product_branch_delete_failed',
                    __(
                        'StoreFleet could not update the product branch assignments.',
                        'storefleet-marketplace'
                    )
                );
        }

        update_post_meta(
            $product_id,
            storefleet_product_branch_mode_meta_key(),
            'all'
        );

        $wpdb->query(
            'COMMIT'
        );

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branches
    |--------------------------------------------------------------------------
    */

    $branch_ids =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        (array) $branch_ids
                    )
                )
            )
        );

    if (
        empty(
            $branch_ids
        )
    ) {
        return
            new WP_Error(
                'storefleet_no_product_branches',
                __(
                    'Select at least one branch for this product.',
                    'storefleet-marketplace'
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Every Selected Branch
    |--------------------------------------------------------------------------
    */

    foreach (
        $branch_ids as $branch_id
    ) {
        if (
            !storefleet_current_user_can_manage_product_branch(
                $product_id,
                $branch_id
            )
        ) {
            return
                new WP_Error(
                    'storefleet_product_branch_access_denied',
                    sprintf(
                        __(
                            'You are not authorized to assign branch #%d to this product.',
                            'storefleet-marketplace'
                        ),
                        $branch_id
                    )
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Replace Selected Branch Assignments
    |--------------------------------------------------------------------------
    */

    $wpdb->query(
        'START TRANSACTION'
    );


    /*
    |--------------------------------------------------------------------------
    | Remove Existing Rows
    |--------------------------------------------------------------------------
    */

    $deleted =
        $wpdb->delete(
            $table,
            [
                'product_id' =>
                    $product_id,
            ],
            [
                '%d',
            ]
        );

    if ($deleted === false) {
        $wpdb->query(
            'ROLLBACK'
        );

        return
            new WP_Error(
                'storefleet_product_branch_delete_failed',
                __(
                    'StoreFleet could not clear the previous branch assignments.',
                    'storefleet-marketplace'
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Selected Rows
    |--------------------------------------------------------------------------
    */

    foreach (
        $branch_ids as $branch_id
    ) {
        $inserted =
            $wpdb->insert(
                $table,
                [
                    'product_id' =>
                        $product_id,

                    'branch_id' =>
                        $branch_id,

                    'created_at' =>
                        current_time(
                            'mysql'
                        ),
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                ]
            );

        if ($inserted === false) {
            $wpdb->query(
                'ROLLBACK'
            );

            return
                new WP_Error(
                    'storefleet_product_branch_insert_failed',
                    __(
                        'StoreFleet could not save the selected product branches.',
                        'storefleet-marketplace'
                    )
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Product Mode
    |--------------------------------------------------------------------------
    */

    update_post_meta(
        $product_id,
        storefleet_product_branch_mode_meta_key(),
        'selected'
    );


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