<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Product Publishing
|--------------------------------------------------------------------------
|
| StoreFleet does not use product-by-product admin approval.
|
| Merchant verification is a trust/certification signal only.
| It must not determine whether a merchant can publish products.
|
| Rules:
|
| - New merchant products may publish immediately.
| - Editing a published product keeps it published.
| - Pending-review product status is converted to published.
| - Draft products may remain drafts.
| - Merchant ownership remains unchanged.
|
*/


/*
|--------------------------------------------------------------------------
| Allow Publish In Dokan Product Status Options
|--------------------------------------------------------------------------
|
| Dokan removes "publish" for sellers it does not consider trusted.
|
| StoreFleet restores Publish and removes Pending Review from the vendor
| product status selector.
|
*/

add_filter(
    'dokan_post_status',
    function (
        $statuses,
        $product_id
    ) {
        if (!is_array($statuses)) {
            $statuses =
                array();
        }


        $statuses[
            'publish'
        ] =
            function_exists(
                'dokan_get_post_status'
            )
                ? dokan_get_post_status(
                    'publish'
                )
                : 'Published';


        unset(
            $statuses[
                'pending'
            ]
        );


        return $statuses;
    },
    999,
    2
);


/*
|--------------------------------------------------------------------------
| Publish Newly Created Merchant Products
|--------------------------------------------------------------------------
|
| Dokan determines a default product status before inserting the product.
|
| If Dokan chooses "pending", StoreFleet converts that status to "publish".
|
| We deliberately do not convert "draft" to "publish", so merchants can
| still intentionally save a product as a draft.
|
*/

add_filter(
    'dokan_insert_product_post_data',
    function ($post_data) {
        if (!is_array($post_data)) {
            return $post_data;
        }


        if (
            isset(
                $post_data[
                    'post_status'
                ]
            ) &&
            $post_data[
                'post_status'
            ] === 'pending'
        ) {
            $post_data[
                'post_status'
            ] =
                'publish';
        }


        return $post_data;
    },
    999
);


/*
|--------------------------------------------------------------------------
| Keep Edited Merchant Products Published
|--------------------------------------------------------------------------
|
| Dokan may submit a product update with Pending Review status.
|
| StoreFleet converts only "pending" to "publish".
|
| Draft products are left alone.
|
*/

add_filter(
    'dokan_update_product_post_data',
    function ($post_data) {
        if (!is_array($post_data)) {
            return $post_data;
        }


        if (
            isset(
                $post_data[
                    'post_status'
                ]
            ) &&
            $post_data[
                'post_status'
            ] === 'pending'
        ) {
            $post_data[
                'post_status'
            ] =
                'publish';
        }


        return $post_data;
    },
    999
);


/*
|--------------------------------------------------------------------------
| Product Edit Default Status
|--------------------------------------------------------------------------
|
| A product that is already Pending Review should appear as Published when
| the merchant opens it for editing.
|
*/

add_filter(
    'dokan_post_edit_default_status',
    function (
        $status,
        $product
    ) {
        if ($status === 'pending') {
            return 'publish';
        }


        return $status;
    },
    999,
    2
);


/*
|--------------------------------------------------------------------------
| Safety Net For Dokan Product Saves
|--------------------------------------------------------------------------
|
| Some Dokan flows may save a product through a different internal path.
|
| If a merchant-owned product ends up Pending Review, StoreFleet promotes
| it to Published after Dokan finishes saving it.
|
| wp_update_post() is guarded to avoid an infinite save loop.
|
*/

add_action(
    'dokan_new_product_added',
    function ($product_id) {
        storefleet_publish_pending_merchant_product(
            $product_id
        );
    },
    999
);


add_action(
    'dokan_product_updated',
    function ($product_id) {
        storefleet_publish_pending_merchant_product(
            $product_id
        );
    },
    999
);


/*
|--------------------------------------------------------------------------
| Publish Pending Merchant Product
|--------------------------------------------------------------------------
*/

function storefleet_publish_pending_merchant_product(
    $product_id
) {
    $product_id =
        absint(
            is_object($product_id) &&
            method_exists(
                $product_id,
                'get_id'
            )
                ? $product_id->get_id()
                : $product_id
        );


    if ($product_id <= 0) {
        return;
    }


    if (
        get_post_type(
            $product_id
        ) !== 'product'
    ) {
        return;
    }


    if (
        get_post_status(
            $product_id
        ) !== 'pending'
    ) {
        return;
    }


    $author_id =
        absint(
            get_post_field(
                'post_author',
                $product_id
            )
        );


    if ($author_id <= 0) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Merchant Products
    |--------------------------------------------------------------------------
    */

    $user =
        get_user_by(
            'id',
            $author_id
        );


    if (!$user) {
        return;
    }


    if (
        !in_array(
            'seller',
            (array)
            $user->roles,
            true
        )
    ) {
        return;
    }


    wp_update_post(
        array(
            'ID' =>
                $product_id,

            'post_status' =>
                'publish',
        )
    );
}