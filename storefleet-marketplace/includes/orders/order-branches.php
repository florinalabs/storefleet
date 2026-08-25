<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Order Fulfillment Branch
|--------------------------------------------------------------------------
|
| An order/sub-order may be assigned to one StoreFleet fulfillment branch.
|
| The assignment is stored on the WooCommerce order using WC_Order meta so
| the implementation remains compatible with WooCommerce HPOS.
|
| Meta:
|
| _storefleet_fulfillment_branch_id
|
| IMPORTANT:
|
| This file establishes the data model only.
|
| It does NOT yet:
|
| - automatically choose a branch at checkout
| - filter Dokan REST orders
| - authorize order status mutations
|
| Those layers are implemented separately.
|
*/


/*
|--------------------------------------------------------------------------
| Fulfillment Branch Meta Key
|--------------------------------------------------------------------------
*/

function storefleet_order_fulfillment_branch_meta_key()
{
    return
        '_storefleet_fulfillment_branch_id';
}


/*
|--------------------------------------------------------------------------
| Resolve WooCommerce Order
|--------------------------------------------------------------------------
|
| Accept:
|
| - WC_Order object
| - order ID
|
*/

function storefleet_resolve_order(
    $order
) {
    if (
        $order instanceof WC_Order
    ) {
        return $order;
    }

    $order_id =
        absint(
            $order
        );

    if (!$order_id) {
        return null;
    }

    $order =
        wc_get_order(
            $order_id
        );

    if (
        !($order instanceof WC_Order)
    ) {
        return null;
    }

    return $order;
}


/*
|--------------------------------------------------------------------------
| Get Order Merchant ID
|--------------------------------------------------------------------------
|
| Dokan remains the source of truth for order/vendor ownership.
|
| StoreFleet must never infer the merchant from the currently logged-in user
| because staff operate under delegated merchant context.
|
*/

function storefleet_get_order_merchant_id(
    $order
) {
    $order =
        storefleet_resolve_order(
            $order
        );

    if (!$order) {
        return 0;
    }

    $order_id =
        absint(
            $order->get_id()
        );

    if (!$order_id) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan Order → Seller Relationship
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'dokan_get_seller_id_by_order'
        )
    ) {
        return
            absint(
                dokan_get_seller_id_by_order(
                    $order_id
                )
            );
    }

    return 0;
}


/*
|--------------------------------------------------------------------------
| Get Fulfillment Branch ID
|--------------------------------------------------------------------------
*/

function storefleet_get_order_fulfillment_branch_id(
    $order
) {
    $order =
        storefleet_resolve_order(
            $order
        );

    if (!$order) {
        return 0;
    }

    return
        absint(
            $order->get_meta(
                storefleet_order_fulfillment_branch_meta_key(),
                true
            )
        );
}


/*
|--------------------------------------------------------------------------
| Get Fulfillment Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_order_fulfillment_branch(
    $order
) {
    $branch_id =
        storefleet_get_order_fulfillment_branch_id(
            $order
        );

    if (!$branch_id) {
        return null;
    }

    if (
        !function_exists(
            'storefleet_get_branch'
        )
    ) {
        return null;
    }

    return
        storefleet_get_branch(
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Order Belongs To Branch
|--------------------------------------------------------------------------
*/

function storefleet_order_belongs_to_branch(
    $order,
    $branch_id
) {
    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return false;
    }

    $order_branch_id =
        storefleet_get_order_fulfillment_branch_id(
            $order
        );

    if (!$order_branch_id) {
        return false;
    }

    return
        $order_branch_id ===
        $branch_id;
}


/*
|--------------------------------------------------------------------------
| Validate Order Fulfillment Branch
|--------------------------------------------------------------------------
|
| Requirements:
|
| 1. valid WooCommerce order
| 2. valid Dokan merchant owner
| 3. valid StoreFleet branch
| 4. branch belongs to the SAME merchant as the order
| 5. branch must currently be active when assigning
|
*/

function storefleet_validate_order_fulfillment_branch(
    $order,
    $branch_id
) {
    $order =
        storefleet_resolve_order(
            $order
        );

    if (!$order) {
        return new WP_Error(
            'storefleet_invalid_order',
            __(
                'The StoreFleet order could not be found.',
                'storefleet-marketplace'
            )
        );
    }

    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return new WP_Error(
            'storefleet_invalid_order_branch',
            __(
                'A valid StoreFleet fulfillment branch is required.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Order Merchant
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        storefleet_get_order_merchant_id(
            $order
        );

    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_order_merchant_not_found',
            __(
                'The merchant for this order could not be determined.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Branch
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_branch'
        )
    ) {
        return new WP_Error(
            'storefleet_branch_service_unavailable',
            __(
                'StoreFleet branch information is unavailable.',
                'storefleet-marketplace'
            )
        );
    }

    $branch =
        storefleet_get_branch(
            $branch_id
        );

    if (!$branch) {
        return new WP_Error(
            'storefleet_order_branch_not_found',
            __(
                'The selected StoreFleet fulfillment branch could not be found.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Active Branch
    |--------------------------------------------------------------------------
    */

    if (
        isset($branch->is_active)
        &&
        (int) $branch->is_active !== 1
    ) {
        return new WP_Error(
            'storefleet_order_branch_inactive',
            __(
                'The selected StoreFleet fulfillment branch is not active.',
                'storefleet-marketplace'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owns Branch
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
        return new WP_Error(
            'storefleet_order_branch_merchant_mismatch',
            __(
                'The selected fulfillment branch does not belong to this order merchant.',
                'storefleet-marketplace'
            )
        );
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Set Order Fulfillment Branch
|--------------------------------------------------------------------------
|
| Uses WC_Order::update_meta_data() and save() for HPOS compatibility.
|
*/

function storefleet_set_order_fulfillment_branch(
    $order,
    $branch_id
) {
    $order =
        storefleet_resolve_order(
            $order
        );

    if (!$order) {
        return new WP_Error(
            'storefleet_invalid_order',
            __(
                'The StoreFleet order could not be found.',
                'storefleet-marketplace'
            )
        );
    }

    $branch_id =
        absint(
            $branch_id
        );


    /*
    |--------------------------------------------------------------------------
    | Validate Assignment
    |--------------------------------------------------------------------------
    */

    $validation =
        storefleet_validate_order_fulfillment_branch(
            $order,
            $branch_id
        );

    if (
        is_wp_error(
            $validation
        )
    ) {
        return $validation;
    }


    /*
    |--------------------------------------------------------------------------
    | Save Branch
    |--------------------------------------------------------------------------
    */

    $order->update_meta_data(
        storefleet_order_fulfillment_branch_meta_key(),
        $branch_id
    );

    $order->save();

    return true;
}


/*
|--------------------------------------------------------------------------
| Clear Order Fulfillment Branch
|--------------------------------------------------------------------------
|
| Kept separate from the setter so passing 0 accidentally cannot silently
| remove an existing fulfillment assignment.
|
*/

function storefleet_clear_order_fulfillment_branch(
    $order
) {
    $order =
        storefleet_resolve_order(
            $order
        );

    if (!$order) {
        return new WP_Error(
            'storefleet_invalid_order',
            __(
                'The StoreFleet order could not be found.',
                'storefleet-marketplace'
            )
        );
    }

    $order->delete_meta_data(
        storefleet_order_fulfillment_branch_meta_key()
    );

    $order->save();

    return true;
}