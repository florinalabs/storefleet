<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Public Product API
|--------------------------------------------------------------------------
|
| Customer-facing product catalog for Next.js.
|
| Important boundaries:
|
| - WooCommerce remains the product source of truth.
| - Dokan/WP post_author remains the merchant owner.
| - Product branch availability comes from StoreFleet product branches.
| - Branch stock comes from wp_storefleet_branch_inventory when present.
| - Existing products without a branch inventory row fall back to Woo stock.
| - Public responses never expose the merchant/vendor base price directly.
|
*/


/*
|--------------------------------------------------------------------------
| Register Routes
|--------------------------------------------------------------------------
*/

add_action(
    'rest_api_init',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Merchant Products
        |--------------------------------------------------------------------------
        |
        | GET /wp-json/storefleet/v1/stores/2/products
        | GET /wp-json/storefleet/v1/stores/2/products?branch_id=2
        |
        */

        register_rest_route(
            'storefleet/v1',
            '/stores/(?P<merchant_id>\d+)/products',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_products_by_merchant',

                'permission_callback' =>
                    'storefleet_api_public_product_permission',

                'args' => [
                    'merchant_id' => [
                        'required' =>
                            true,

                        'sanitize_callback' =>
                            'absint',

                        'validate_callback' =>
                            function ($value) {
                                return absint($value) > 0;
                            },
                    ],

                    'branch_id' => [
                        'required' =>
                            false,

                        'sanitize_callback' =>
                            'absint',
                    ],

                    'page' => [
                        'required' =>
                            false,

                        'default' =>
                            1,

                        'sanitize_callback' =>
                            'absint',
                    ],

                    'per_page' => [
                        'required' =>
                            false,

                        'default' =>
                            24,

                        'sanitize_callback' =>
                            'absint',
                    ],
                ],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Store Products By SEO Slug
        |--------------------------------------------------------------------------
        |
        | GET /wp-json/storefleet/v1/stores/by-slug/brew-bean-coffee-house/products
        | GET /wp-json/storefleet/v1/stores/by-slug/brew-bean-coffee-house/products?branch_id=2
        |
        */

        register_rest_route(
            'storefleet/v1',
            '/stores/by-slug/(?P<store_slug>[a-zA-Z0-9-]+)/products',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_products_by_store_slug',

                'permission_callback' =>
                    'storefleet_api_public_product_permission',

                'args' => [
                    'store_slug' => [
                        'required' =>
                            true,

                        'sanitize_callback' =>
                            'sanitize_title',
                    ],

                    'branch_id' => [
                        'required' =>
                            false,

                        'sanitize_callback' =>
                            'absint',
                    ],

                    'page' => [
                        'required' =>
                            false,

                        'default' =>
                            1,

                        'sanitize_callback' =>
                            'absint',
                    ],

                    'per_page' => [
                        'required' =>
                            false,

                        'default' =>
                            24,

                        'sanitize_callback' =>
                            'absint',
                    ],
                ],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | One Product
        |--------------------------------------------------------------------------
        |
        | GET /wp-json/storefleet/v1/products/123
        | GET /wp-json/storefleet/v1/products/123?branch_id=2
        |
        */

        /*
        |--------------------------------------------------------------------------
        | Marketplace Products
        |--------------------------------------------------------------------------
        |
        | GET /wp-json/storefleet/v1/products
        |
        | Each product is returned once with every active fulfillment branch
        | where that product is available, including branch coordinates and
        | branch-specific stock for nearest-location filtering.
        |
        */

        register_rest_route(
            'storefleet/v1',
            '/products',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_marketplace_products',

                'permission_callback' =>
                    'storefleet_api_public_product_permission',

                'args' => [
                    'page' => [
                        'required' =>
                            false,

                        'default' =>
                            1,

                        'sanitize_callback' =>
                            'absint',
                    ],

                    'per_page' => [
                        'required' =>
                            false,

                        'default' =>
                            48,

                        'sanitize_callback' =>
                            'absint',
                    ],
                ],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | One Product By Slug + Branch
        |--------------------------------------------------------------------------
        |
        | GET /wp-json/storefleet/v1/products/by-slug/barako-coffee-beans-250g
        | GET /wp-json/storefleet/v1/products/by-slug/barako-coffee-beans-250g?branch_id=2
        |
        | If branch_id is omitted, StoreFleet resolves the merchant's primary
        | active branch, then falls back to the first active branch.
        |
        */

        register_rest_route(
            'storefleet/v1',
            '/products/by-slug/(?P<product_slug>[a-zA-Z0-9-]+)',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_product_by_slug',

                'permission_callback' =>
                    'storefleet_api_public_product_permission',

                'args' => [
                    'product_slug' => [
                        'required' =>
                            true,

                        'sanitize_callback' =>
                            'sanitize_title',
                    ],

                    'branch_id' => [
                        'required' =>
                            false,

                        'sanitize_callback' =>
                            'absint',
                    ],
                ],
            ]
        );


        register_rest_route(
            'storefleet/v1',
            '/products/(?P<product_id>\d+)',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_product',

                'permission_callback' =>
                    'storefleet_api_public_product_permission',

                'args' => [
                    'product_id' => [
                        'required' =>
                            true,

                        'sanitize_callback' =>
                            'absint',

                        'validate_callback' =>
                            function ($value) {
                                return absint($value) > 0;
                            },
                    ],

                    'branch_id' => [
                        'required' =>
                            false,

                        'sanitize_callback' =>
                            'absint',
                    ],
                ],
            ]
        );
    }
);


/*
|--------------------------------------------------------------------------
| Product API Authentication
|--------------------------------------------------------------------------
|
| Same private server-to-server key used by the Store API.
|
| Browser -> Next.js server -> WordPress
|
| The secret must never be exposed as NEXT_PUBLIC_*.
|
*/

function storefleet_api_public_product_permission(
    WP_REST_Request $request
) {
    if (
        function_exists(
            'storefleet_api_public_store_permission'
        )
    ) {
        return
            storefleet_api_public_store_permission(
                $request
            );
    }


    $expected_key =
        defined(
            'STOREFLEET_INTERNAL_API_KEY'
        )
            ? STOREFLEET_INTERNAL_API_KEY
            : getenv(
                'STOREFLEET_INTERNAL_API_KEY'
            );


    if (
        !is_string(
            $expected_key
        )
        ||
        $expected_key === ''
    ) {
        return false;
    }


    $provided =
        (string)
        $request->get_header(
            'x-storefleet-key'
        );


    if ($provided === '') {
        return false;
    }


    return hash_equals(
        $expected_key,
        $provided
    );
}


/*
|--------------------------------------------------------------------------
| Products By Merchant ID
|--------------------------------------------------------------------------
*/

function storefleet_api_get_public_products_by_merchant(
    WP_REST_Request $request
) {
    $merchant_id =
        absint(
            $request[
                'merchant_id'
            ]
        );


    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_invalid_merchant',
            'A valid merchant is required.',
            [
                'status' =>
                    400,
            ]
        );
    }


    return
        storefleet_api_build_public_products_response(
            $merchant_id,
            $request
        );
}


/*
|--------------------------------------------------------------------------
| Products By Store Slug
|--------------------------------------------------------------------------
*/

function storefleet_api_get_public_products_by_store_slug(
    WP_REST_Request $request
) {
    $store_slug =
        sanitize_title(
            (string)
            $request[
                'store_slug'
            ]
        );


    if ($store_slug === '') {
        return new WP_Error(
            'storefleet_invalid_store_slug',
            'A valid store slug is required.',
            [
                'status' =>
                    400,
            ]
        );
    }


    $merchant_id =
        0;


    if (
        function_exists(
            'storefleet_api_find_merchant_by_store_slug'
        )
    ) {
        $resolved_merchant =
            storefleet_api_find_merchant_by_store_slug(
                $store_slug
            );


        if (
            $resolved_merchant
            instanceof WP_User
        ) {
            $merchant_id =
                absint(
                    $resolved_merchant->ID
                );
        } elseif (
            is_object(
                $resolved_merchant
            )
            &&
            isset(
                $resolved_merchant->ID
            )
        ) {
            $merchant_id =
                absint(
                    $resolved_merchant->ID
                );
        } else {
            $merchant_id =
                absint(
                    $resolved_merchant
                );
        }
    }


    if (!$merchant_id) {
        $merchant_id =
            storefleet_api_product_find_merchant_by_slug(
                $store_slug
            );
    }


    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_store_not_found',
            'Store not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    return
        storefleet_api_build_public_products_response(
            $merchant_id,
            $request
        );
}


/*
|--------------------------------------------------------------------------
| Marketplace Products
|--------------------------------------------------------------------------
|
| Lists published WooCommerce products across active StoreFleet merchants.
| Each product uses that merchant's primary active branch for availability and
| stock. Stores with no active branch are skipped.
|
*/

function storefleet_api_get_public_marketplace_products(
    WP_REST_Request $request
) {
    if (
        !function_exists(
            'wc_get_product'
        )
    ) {
        return new WP_Error(
            'storefleet_woocommerce_unavailable',
            'WooCommerce is unavailable.',
            [
                'status' =>
                    503,
            ]
        );
    }


    $page =
        max(
            1,
            absint(
                $request->get_param(
                    'page'
                )
            )
        );


    $per_page =
        absint(
            $request->get_param(
                'per_page'
            )
        );


    if (!$per_page) {
        $per_page =
            48;
    }


    $per_page =
        min(
            100,
            max(
                1,
                $per_page
            )
        );


    $query =
        new WP_Query(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'posts_per_page' =>
                    $per_page,

                'paged' =>
                    $page,

                'orderby' =>
                    [
                        'menu_order' =>
                            'ASC',

                        'date' =>
                            'DESC',
                    ],

                'fields' =>
                    'ids',

                'no_found_rows' =>
                    false,
            ]
        );


    $products =
        array();


    foreach (
        $query->posts as
        $product_id
    ) {
        $product_id =
            absint(
                $product_id
            );


        if (!$product_id) {
            continue;
        }


        $merchant_id =
            absint(
                get_post_field(
                    'post_author',
                    $product_id
                )
            );


        if (!$merchant_id) {
            continue;
        }


        $merchant_error =
            storefleet_api_product_validate_public_merchant(
                $merchant_id
            );


        if (is_wp_error($merchant_error)) {
            continue;
        }


        $product =
            wc_get_product(
                $product_id
            );


        if (
            !$product
            ||
            $product->get_status() !==
                'publish'
        ) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Every Active Fulfillment Branch
        |--------------------------------------------------------------------------
        |
        | The marketplace returns the product once. "branches" contains every
        | active merchant branch where the product is assigned/available.
        |
        | This gives Next.js enough information to:
        |
        | - search by branch/address
        | - filter by branch
        | - calculate nearest branch from customer coordinates
        | - open /shop/{slug}?branch={branch_id}
        |
        */

        $branch_objects =
            storefleet_api_product_get_available_branch_objects(
                $product_id,
                $merchant_id
            );


        if (empty($branch_objects)) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Default Marketplace Branch
        |--------------------------------------------------------------------------
        |
        | Prefer:
        |
        | 1. primary branch that is in stock
        | 2. first in-stock branch
        | 3. primary branch
        | 4. first available branch
        |
        | The frontend may replace this with the customer's nearest in-stock
        | branch after geolocation/address resolution.
        |
        */

        $branch =
            storefleet_api_product_choose_default_branch(
                $product,
                $branch_objects
            );


        if (!$branch) {
            continue;
        }


        $prepared =
            storefleet_api_prepare_public_product(
                $product,
                $merchant_id,
                $branch
            );


        $prepared['branch'] =
            storefleet_api_product_prepare_branch_summary(
                $branch
            );


        $prepared['branches'] =
            storefleet_api_product_prepare_available_branches(
                $product,
                $branch_objects
            );


        $prepared['branch_count'] =
            count(
                $prepared['branches']
            );


        $products[] =
            $prepared;
    }


    $response =
        new WP_REST_Response(
            [
                'success' =>
                    true,

                'generated_at' =>
                    current_datetime()
                        ->format(
                            DATE_ATOM
                        ),

                'timezone' =>
                    wp_timezone_string(),

                'pagination' => [
                    'page' =>
                        $page,

                    'per_page' =>
                        $per_page,

                    'count' =>
                        count(
                            $products
                        ),

                    'published_total' =>
                        absint(
                            $query->found_posts
                        ),

                    'total_pages' =>
                        absint(
                            $query->max_num_pages
                        ),
                ],

                'products' =>
                    $products,
            ],
            200
        );


    $response->header(
        'Cache-Control',
        'public, max-age=60, s-maxage=60'
    );


    return $response;
}

/*
|--------------------------------------------------------------------------
| One Public Product By Slug
|--------------------------------------------------------------------------
|
| Returns one published WooCommerce product in a StoreFleet branch context.
|
| Explicit branch_id:
| - branch must belong to the product merchant
| - branch must be active
| - product must be available at the branch
|
| Omitted branch_id:
| - primary active branch
| - otherwise first active branch
|
*/

function storefleet_api_get_public_product_by_slug(
    WP_REST_Request $request
) {
    $product_slug =
        sanitize_title(
            (string)
            $request[
                'product_slug'
            ]
        );


    if ($product_slug === '') {
        return new WP_Error(
            'storefleet_invalid_product_slug',
            'A valid product slug is required.',
            [
                'status' =>
                    400,
            ]
        );
    }


    if (
        !function_exists(
            'wc_get_product'
        )
    ) {
        return new WP_Error(
            'storefleet_woocommerce_unavailable',
            'WooCommerce is unavailable.',
            [
                'status' =>
                    503,
            ]
        );
    }


    $post =
        get_page_by_path(
            $product_slug,
            OBJECT,
            'product'
        );


    if (
        !$post
        ||
        $post->post_status !==
            'publish'
    ) {
        return new WP_Error(
            'storefleet_product_not_found',
            'Product not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $product_id =
        absint(
            $post->ID
        );


    $product =
        wc_get_product(
            $product_id
        );


    if (
        !$product
        ||
        $product->get_status() !==
            'publish'
    ) {
        return new WP_Error(
            'storefleet_product_not_found',
            'Product not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $merchant_id =
        absint(
            get_post_field(
                'post_author',
                $product_id
            )
        );


    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_product_merchant_not_found',
            'Product merchant was not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $merchant_error =
        storefleet_api_product_validate_public_merchant(
            $merchant_id
        );


    if (is_wp_error($merchant_error)) {
        return $merchant_error;
    }


    $branch_objects =
        storefleet_api_product_get_available_branch_objects(
            $product_id,
            $merchant_id
        );


    if (empty($branch_objects)) {
        return new WP_Error(
            'storefleet_product_branch_not_found',
            'No active fulfillment branch is available for this product.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $requested_branch_id =
        absint(
            $request->get_param(
                'branch_id'
            )
        );


    if ($requested_branch_id) {
        $branch_result =
            storefleet_api_product_resolve_branch(
                $merchant_id,
                $requested_branch_id
            );


        if (is_wp_error($branch_result)) {
            return $branch_result;
        }


        $branch =
            $branch_result;


        if (
            !$branch
            ||
            !storefleet_api_product_is_available_at_branch(
                $product_id,
                absint(
                    $branch->id
                )
            )
        ) {
            return new WP_Error(
                'storefleet_product_not_available_at_branch',
                'Product is not available at the selected branch.',
                [
                    'status' =>
                        404,
                ]
            );
        }
    } else {
        $branch =
            storefleet_api_product_choose_default_branch(
                $product,
                $branch_objects
            );
    }


    if (!$branch) {
        return new WP_Error(
            'storefleet_product_branch_not_found',
            'No active fulfillment branch is available for this product.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $data =
        storefleet_api_prepare_public_product(
            $product,
            $merchant_id,
            $branch
        );


    $data['branch'] =
        storefleet_api_product_prepare_branch_summary(
            $branch
        );


    $data['branches'] =
        storefleet_api_product_prepare_available_branches(
            $product,
            $branch_objects
        );


    $data['branch_count'] =
        count(
            $data['branches']
        );


    $response =
        new WP_REST_Response(
            [
                'success' =>
                    true,

                'generated_at' =>
                    current_datetime()
                        ->format(
                            DATE_ATOM
                        ),

                'timezone' =>
                    wp_timezone_string(),

                'branch' =>
                    storefleet_api_product_prepare_branch_summary(
                        $branch
                    ),

                'branches' =>
                    $data['branches'],

                'product' =>
                    $data,
            ],
            200
        );


    $response->header(
        'Cache-Control',
        'public, max-age=60, s-maxage=60'
    );


    return $response;
}

/*
|--------------------------------------------------------------------------
| One Public Product
|--------------------------------------------------------------------------
*/

function storefleet_api_get_public_product(
    WP_REST_Request $request
) {
    $product_id =
        absint(
            $request[
                'product_id'
            ]
        );


    if (!$product_id) {
        return new WP_Error(
            'storefleet_invalid_product',
            'A valid product is required.',
            [
                'status' =>
                    400,
            ]
        );
    }


    if (
        !function_exists(
            'wc_get_product'
        )
    ) {
        return new WP_Error(
            'storefleet_woocommerce_unavailable',
            'WooCommerce is unavailable.',
            [
                'status' =>
                    503,
            ]
        );
    }


    $product =
        wc_get_product(
            $product_id
        );


    if (
        !$product
        ||
        $product->get_status() !==
            'publish'
    ) {
        return new WP_Error(
            'storefleet_product_not_found',
            'Product not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $merchant_id =
        absint(
            get_post_field(
                'post_author',
                $product_id
            )
        );


    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_product_merchant_not_found',
            'Product merchant was not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $merchant_error =
        storefleet_api_product_validate_public_merchant(
            $merchant_id
        );


    if (is_wp_error($merchant_error)) {
        return $merchant_error;
    }


    $branch_objects =
        storefleet_api_product_get_available_branch_objects(
            $product_id,
            $merchant_id
        );


    if (empty($branch_objects)) {
        return new WP_Error(
            'storefleet_product_branch_not_found',
            'No active fulfillment branch is available for this product.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $requested_branch_id =
        absint(
            $request->get_param(
                'branch_id'
            )
        );


    if ($requested_branch_id) {
        $branch_result =
            storefleet_api_product_resolve_branch(
                $merchant_id,
                $requested_branch_id
            );


        if (is_wp_error($branch_result)) {
            return $branch_result;
        }


        $branch =
            $branch_result;


        if (
            !$branch
            ||
            !storefleet_api_product_is_available_at_branch(
                $product_id,
                absint(
                    $branch->id
                )
            )
        ) {
            return new WP_Error(
                'storefleet_product_not_available_at_branch',
                'Product is not available at the selected branch.',
                [
                    'status' =>
                        404,
                ]
            );
        }
    } else {
        $branch =
            storefleet_api_product_choose_default_branch(
                $product,
                $branch_objects
            );
    }


    if (!$branch) {
        return new WP_Error(
            'storefleet_product_branch_not_found',
            'No active fulfillment branch is available for this product.',
            [
                'status' =>
                    404,
            ]
        );
    }


    $data =
        storefleet_api_prepare_public_product(
            $product,
            $merchant_id,
            $branch
        );


    $data['branch'] =
        storefleet_api_product_prepare_branch_summary(
            $branch
        );


    $data['branches'] =
        storefleet_api_product_prepare_available_branches(
            $product,
            $branch_objects
        );


    $data['branch_count'] =
        count(
            $data['branches']
        );


    $response =
        new WP_REST_Response(
            [
                'success' =>
                    true,

                'generated_at' =>
                    current_datetime()
                        ->format(
                            DATE_ATOM
                        ),

                'timezone' =>
                    wp_timezone_string(),

                'branch' =>
                    storefleet_api_product_prepare_branch_summary(
                        $branch
                    ),

                'branches' =>
                    $data['branches'],

                'product' =>
                    $data,
            ],
            200
        );


    $response->header(
        'Cache-Control',
        'public, max-age=60, s-maxage=60'
    );


    return $response;
}

/*
|--------------------------------------------------------------------------
| Build Products Response
|--------------------------------------------------------------------------
*/

function storefleet_api_build_public_products_response(
    $merchant_id,
    WP_REST_Request $request
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    $merchant_error =
        storefleet_api_product_validate_public_merchant(
            $merchant_id
        );


    if (is_wp_error($merchant_error)) {
        return $merchant_error;
    }


    if (
        !function_exists(
            'wc_get_product'
        )
    ) {
        return new WP_Error(
            'storefleet_woocommerce_unavailable',
            'WooCommerce is unavailable.',
            [
                'status' =>
                    503,
            ]
        );
    }


    $requested_branch_id =
        absint(
            $request->get_param(
                'branch_id'
            )
        );


    $branch_result =
        storefleet_api_product_resolve_branch(
            $merchant_id,
            $requested_branch_id
        );


    if (is_wp_error($branch_result)) {
        return $branch_result;
    }


    $branch =
        $branch_result;


    $page =
        max(
            1,
            absint(
                $request->get_param(
                    'page'
                )
            )
        );


    $per_page =
        absint(
            $request->get_param(
                'per_page'
            )
        );


    if (!$per_page) {
        $per_page =
            24;
    }


    $per_page =
        min(
            100,
            max(
                1,
                $per_page
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Merchant Product Query
    |--------------------------------------------------------------------------
    |
    | Dokan products are WooCommerce product posts owned by the vendor's
    | WordPress user ID.
    |
    */

    $query =
        new WP_Query(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'author' =>
                    $merchant_id,

                'posts_per_page' =>
                    $per_page,

                'paged' =>
                    $page,

                'orderby' =>
                    [
                        'menu_order' =>
                            'ASC',

                        'date' =>
                            'DESC',
                    ],

                'fields' =>
                    'ids',

                'no_found_rows' =>
                    false,
            ]
        );


    $products =
        array();


    foreach (
        $query->posts as
        $product_id
    ) {
        $product_id =
            absint(
                $product_id
            );


        if (!$product_id) {
            continue;
        }


        if (
            $branch
            &&
            !storefleet_api_product_is_available_at_branch(
                $product_id,
                absint(
                    $branch->id
                )
            )
        ) {
            continue;
        }


        $product =
            wc_get_product(
                $product_id
            );


        if (!$product) {
            continue;
        }


        $products[] =
            storefleet_api_prepare_public_product(
                $product,
                $merchant_id,
                $branch
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Pagination Note
    |--------------------------------------------------------------------------
    |
    | total from WP_Query is the merchant's published-product total before
    | branch availability filtering. "count" is the number returned for this
    | selected branch/page.
    |
    */

    $response =
        new WP_REST_Response(
            [
                'success' =>
                    true,

                'generated_at' =>
                    current_datetime()
                        ->format(
                            DATE_ATOM
                        ),

                'timezone' =>
                    wp_timezone_string(),

                'merchant' =>
                    storefleet_api_product_prepare_merchant_summary(
                        $merchant_id
                    ),

                'branch' =>
                    storefleet_api_product_prepare_branch_summary(
                        $branch
                    ),

                'pagination' => [
                    'page' =>
                        $page,

                    'per_page' =>
                        $per_page,

                    'count' =>
                        count(
                            $products
                        ),

                    'merchant_published_total' =>
                        absint(
                            $query->found_posts
                        ),

                    'merchant_total_pages' =>
                        absint(
                            $query->max_num_pages
                        ),
                ],

                'products' =>
                    $products,
            ],
            200
        );


    $response->header(
        'Cache-Control',
        'public, max-age=60, s-maxage=60'
    );


    return $response;
}


/*
|--------------------------------------------------------------------------
| Validate Public Merchant
|--------------------------------------------------------------------------
*/

function storefleet_api_product_validate_public_merchant(
    $merchant_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    if (!$merchant_id) {
        return new WP_Error(
            'storefleet_invalid_merchant',
            'A valid merchant is required.',
            [
                'status' =>
                    400,
            ]
        );
    }


    $merchant =
        get_userdata(
            $merchant_id
        );


    if (!$merchant) {
        return new WP_Error(
            'storefleet_merchant_not_found',
            'Merchant not found.',
            [
                'status' =>
                    404,
            ]
        );
    }


    if (
        function_exists(
            'storefleet_api_public_store_is_active'
        )
        &&
        !storefleet_api_public_store_is_active(
            $merchant_id,
            $merchant
        )
    ) {
        return new WP_Error(
            'storefleet_store_unavailable',
            'Store is not available.',
            [
                'status' =>
                    404,
            ]
        );
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| Resolve Branch
|--------------------------------------------------------------------------
|
| When branch_id is omitted:
|
| 1. use StoreFleet primary active branch
| 2. otherwise use the first active merchant branch
|
*/

function storefleet_api_product_resolve_branch(
    $merchant_id,
    $branch_id = 0
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    $branch_id =
        absint(
            $branch_id
        );


    if ($branch_id) {
        $branch =
            function_exists(
                'storefleet_get_branch'
            )
                ? storefleet_get_branch(
                    $branch_id
                )
                : null;


        if (
            !$branch
            ||
            absint(
                $branch->merchant_id
            ) !==
                $merchant_id
            ||
            (int)
            $branch->is_active !==
                1
        ) {
            return new WP_Error(
                'storefleet_invalid_product_branch',
                'The selected branch is not an active branch of this merchant.',
                [
                    'status' =>
                        404,
                ]
            );
        }


        return $branch;
    }


    if (
        function_exists(
            'storefleet_get_primary_branch'
        )
    ) {
        $primary =
            storefleet_get_primary_branch(
                $merchant_id,
                true
            );


        if ($primary) {
            return $primary;
        }
    }


    if (
        function_exists(
            'storefleet_get_merchant_branches'
        )
    ) {
        $branches =
            storefleet_get_merchant_branches(
                $merchant_id,
                true
            );


        if (!empty($branches)) {
            return $branches[0];
        }
    }


    return null;
}


/*
|--------------------------------------------------------------------------
| Product Branch Availability
|--------------------------------------------------------------------------
*/

function storefleet_api_product_is_available_at_branch(
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
    ) {
        return false;
    }


    if (
        function_exists(
            'storefleet_product_is_available_at_branch'
        )
    ) {
        return (bool)
            storefleet_product_is_available_at_branch(
                $product_id,
                $branch_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Backwards-Compatible Fallback
    |--------------------------------------------------------------------------
    |
    | Missing mode = all branches.
    |
    */

    $mode =
        sanitize_key(
            get_post_meta(
                $product_id,
                '_storefleet_branch_mode',
                true
            )
        );


    if (
        $mode === ''
        ||
        $mode === 'all'
    ) {
        return true;
    }


    if ($mode !== 'selected') {
        return true;
    }


    global $wpdb;


    $table =
        $wpdb->prefix .
        'storefleet_product_branches';


    $exists =
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


    return !empty($exists);
}


/*
|--------------------------------------------------------------------------
| Available Branch Objects For Product
|--------------------------------------------------------------------------
|
| Returns every active merchant branch where the product is assigned.
|
*/

function storefleet_api_product_get_available_branch_objects(
    $product_id,
    $merchant_id
) {
    $product_id =
        absint(
            $product_id
        );


    $merchant_id =
        absint(
            $merchant_id
        );


    if (
        !$product_id
        ||
        !$merchant_id
        ||
        !function_exists(
            'storefleet_get_merchant_branches'
        )
    ) {
        return array();
    }


    $merchant_branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );


    if (empty($merchant_branches)) {
        return array();
    }


    $available_branches =
        array();


    foreach (
        $merchant_branches as
        $branch
    ) {
        if (
            !$branch
            ||
            empty(
                $branch->id
            )
        ) {
            continue;
        }


        if (
            !storefleet_api_product_is_available_at_branch(
                $product_id,
                absint(
                    $branch->id
                )
            )
        ) {
            continue;
        }


        $available_branches[] =
            $branch;
    }


    usort(
        $available_branches,
        function (
            $a,
            $b
        ) {
            $a_primary =
                !empty(
                    $a->is_primary
                )
                    ? 1
                    : 0;


            $b_primary =
                !empty(
                    $b->is_primary
                )
                    ? 1
                    : 0;


            if (
                $a_primary !==
                $b_primary
            ) {
                return
                    $b_primary <=>
                    $a_primary;
            }


            return
                strcasecmp(
                    (string)
                    $a->name,
                    (string)
                    $b->name
                );
        }
    );


    return $available_branches;
}


/*
|--------------------------------------------------------------------------
| Choose Default Product Branch
|--------------------------------------------------------------------------
|
| Marketplace/product pages need a branch even before customer location is
| known. Prefer an in-stock branch while keeping the primary branch priority.
|
*/

function storefleet_api_product_choose_default_branch(
    WC_Product $product,
    array $branches
) {
    if (empty($branches)) {
        return null;
    }


    $primary =
        null;


    $first_in_stock =
        null;


    foreach (
        $branches as
        $branch
    ) {
        if (!$branch) {
            continue;
        }


        if (
            !$primary
            &&
            !empty(
                $branch->is_primary
            )
        ) {
            $primary =
                $branch;
        }


        $stock =
            storefleet_api_product_public_stock(
                $product,
                $branch
            );


        if (
            !empty(
                $stock['available']
            )
            &&
            !empty(
                $stock['in_stock']
            )
        ) {
            if (
                !empty(
                    $branch->is_primary
                )
            ) {
                return $branch;
            }


            if (!$first_in_stock) {
                $first_in_stock =
                    $branch;
            }
        }
    }


    if ($first_in_stock) {
        return $first_in_stock;
    }


    if ($primary) {
        return $primary;
    }


    return $branches[0];
}


/*
|--------------------------------------------------------------------------
| Prepare All Product Branches
|--------------------------------------------------------------------------
|
| Each branch includes coordinates and its own stock state so Next.js can
| calculate nearest fulfillment without duplicating the product card.
|
*/

function storefleet_api_product_prepare_available_branches(
    WC_Product $product,
    array $branches
) {
    $prepared =
        array();


    foreach (
        $branches as
        $branch
    ) {
        if (!$branch) {
            continue;
        }


        $summary =
            storefleet_api_product_prepare_branch_summary(
                $branch
            );


        if (!$summary) {
            continue;
        }


        $stock =
            storefleet_api_product_public_stock(
                $product,
                $branch
            );


        $summary['available'] =
            !empty(
                $stock['available']
            );


        $summary['in_stock'] =
            !empty(
                $stock['in_stock']
            );


        $summary['stock_quantity'] =
            $stock[
                'stock_quantity'
            ];


        $summary['stock_source'] =
            $stock[
                'source'
            ];


        $prepared[] =
            $summary;
    }


    return $prepared;
}


/*
|--------------------------------------------------------------------------
| Prepare Public Product
|--------------------------------------------------------------------------
*/

function storefleet_api_prepare_public_product(
    WC_Product $product,
    $merchant_id,
    $branch = null
) {
    $product_id =
        $product->get_id();


    $pricing =
        storefleet_api_product_public_pricing(
            $product
        );


    $stock =
        storefleet_api_product_public_stock(
            $product,
            $branch
        );


    $categories =
        storefleet_api_product_categories(
            $product_id
        );


    $images =
        storefleet_api_product_images(
            $product
        );


    return [
        'id' =>
            $product_id,

        'slug' =>
            $product->get_slug(),

        'name' =>
            $product->get_name(),

        'type' =>
            $product->get_type(),

        'short_description' =>
            wp_kses_post(
                $product->get_short_description()
            ),

        'description' =>
            wp_kses_post(
                $product->get_description()
            ),

        'sku' =>
            $product->get_sku(),

        'currency' =>
            get_woocommerce_currency(),

        /*
        |--------------------------------------------------------------------------
        | Public Customer Pricing Only
        |--------------------------------------------------------------------------
        |
        | Never add vendor/base price, margin amount or markup percentage here.
        |
        */

        'price' =>
            $pricing[
                'final_customer_price'
            ],

        'regular_price' =>
            $pricing[
                'regular_customer_price'
            ],

        'on_sale' =>
            $pricing[
                'on_sale'
            ],

        'campaign' =>
            $pricing[
                'campaign'
            ],

        'image_url' =>
            $images[
                'primary'
            ],

        'images' =>
            $images[
                'gallery'
            ],

        'categories' =>
            $categories,

        'merchant' =>
            storefleet_api_product_prepare_merchant_summary(
                $merchant_id
            ),

        'branch_id' =>
            $branch
                ? absint(
                    $branch->id
                )
                : null,

        'available' =>
            $stock[
                'available'
            ],

        'in_stock' =>
            $stock[
                'in_stock'
            ],

        'stock_quantity' =>
            $stock[
                'stock_quantity'
            ],

        'stock_source' =>
            $stock[
                'source'
            ],
    ];
}


/*
|--------------------------------------------------------------------------
| Public Pricing
|--------------------------------------------------------------------------
|
| Current implementation:
|
| - merchant Woo regular price is treated as the protected pricing basis
| - resolve StoreFleet category markup
| - calculate customer regular price server-side
| - expose only the customer-facing result
|
| Filters are provided so the future full pricing/campaign engine can replace
| the calculation without changing the public API contract.
|
*/

function storefleet_api_product_public_pricing(
    WC_Product $product
) {
    $product_id =
        $product->get_id();


    /*
    |--------------------------------------------------------------------------
    | Pricing Basis
    |--------------------------------------------------------------------------
    |
    | Read raw edit-context Woo price internally only.
    | It is never returned in the public response.
    |
    */

    $base_price =
        $product->get_regular_price(
            'edit'
        );


    if (
        $base_price === ''
        ||
        $base_price === null
    ) {
        $base_price =
            $product->get_price(
                'edit'
            );
    }


    $base_price =
        (float)
        $base_price;


    $markup_pct =
        storefleet_api_product_resolve_category_markup(
            $product_id
        );


    $regular_customer_price =
        $base_price *
        (
            1 +
            (
                $markup_pct /
                100
            )
        );


    $regular_customer_price =
        (float)
        wc_format_decimal(
            $regular_customer_price,
            wc_get_price_decimals()
        );


    $regular_customer_price =
        (float)
        apply_filters(
            'storefleet_public_regular_customer_price',
            $regular_customer_price,
            $product,
            $markup_pct
        );


    /*
    |--------------------------------------------------------------------------
    | Future Campaign Hook
    |--------------------------------------------------------------------------
    |
    | Until the campaign engine exists, final = regular.
    |
    */

    $final_customer_price =
        (float)
        apply_filters(
            'storefleet_public_final_customer_price',
            $regular_customer_price,
            $product,
            $regular_customer_price
        );


    $final_customer_price =
        (float)
        wc_format_decimal(
            max(
                0,
                $final_customer_price
            ),
            wc_get_price_decimals()
        );


    return [
        'regular_customer_price' =>
            $regular_customer_price,

        'final_customer_price' =>
            $final_customer_price,

        'on_sale' =>
            $final_customer_price <
            $regular_customer_price,

        'campaign' =>
            null,
    ];
}


/*
|--------------------------------------------------------------------------
| Resolve Category Markup
|--------------------------------------------------------------------------
|
| Searches assigned product categories from deepest to shallowest.
| A configured child category wins. If absent, its parents are checked.
|
| Current category admin stores markup in term meta:
|
| storefleet_markup
|
*/

function storefleet_api_product_resolve_category_markup(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );


    $terms =
        wp_get_post_terms(
            $product_id,
            'product_cat'
        );


    if (
        is_wp_error($terms)
        ||
        empty($terms)
    ) {
        return
            (float)
            apply_filters(
                'storefleet_default_markup_pct',
                0,
                $product_id
            );
    }


    usort(
        $terms,
        function (
            $a,
            $b
        ) {
            return
                count(
                    get_ancestors(
                        $b->term_id,
                        'product_cat',
                        'taxonomy'
                    )
                )
                <=>
                count(
                    get_ancestors(
                        $a->term_id,
                        'product_cat',
                        'taxonomy'
                    )
                );
        }
    );


    $checked =
        array();


    foreach (
        $terms as
        $term
    ) {
        $candidate_ids =
            array_merge(
                [
                    absint(
                        $term->term_id
                    ),
                ],
                array_map(
                    'absint',
                    get_ancestors(
                        $term->term_id,
                        'product_cat',
                        'taxonomy'
                    )
                )
            );


        foreach (
            $candidate_ids as
            $term_id
        ) {
            if (
                !$term_id
                ||
                isset(
                    $checked[
                        $term_id
                    ]
                )
            ) {
                continue;
            }


            $checked[
                $term_id
            ] =
                true;


            $markup =
                get_term_meta(
                    $term_id,
                    'storefleet_markup',
                    true
                );


            if (
                $markup !== ''
                &&
                is_numeric(
                    $markup
                )
            ) {
                return max(
                    0,
                    (float)
                    $markup
                );
            }
        }
    }


    return
        max(
            0,
            (float)
            apply_filters(
                'storefleet_default_markup_pct',
                0,
                $product_id
            )
        );
}


/*
|--------------------------------------------------------------------------
| Public Branch Stock
|--------------------------------------------------------------------------
|
| When a branch inventory row exists:
|
| available qty = stock_qty - reserved_qty
|
| When no branch row exists yet:
|
| fall back to WooCommerce stock so existing products remain usable while
| StoreFleet branch inventory is being populated.
|
*/

function storefleet_api_product_public_stock(
    WC_Product $product,
    $branch = null
) {
    $product_id =
        $product->get_id();


    if ($branch) {
        global $wpdb;


        $table =
            function_exists(
                'storefleet_branch_inventory_table'
            )
                ? storefleet_branch_inventory_table()
                : $wpdb->prefix .
                    'storefleet_branch_inventory';


        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT
                        stock_qty,
                        reserved_qty
                    FROM {$table}
                    WHERE branch_id = %d
                    AND product_id = %d
                    LIMIT 1
                    ",
                    absint(
                        $branch->id
                    ),
                    $product_id
                )
            );


        if ($row) {
            $stock_qty =
                (float)
                $row->stock_qty;


            $reserved_qty =
                (float)
                $row->reserved_qty;


            $available_qty =
                max(
                    0,
                    $stock_qty -
                    $reserved_qty
                );


            return [
                'available' =>
                    $available_qty > 0,

                'in_stock' =>
                    $available_qty > 0,

                'stock_quantity' =>
                    $available_qty,

                'source' =>
                    'branch',
            ];
        }
    }


    $manages_stock =
        $product->managing_stock();


    $quantity =
        $manages_stock
            ? $product->get_stock_quantity()
            : null;


    return [
        'available' =>
            $product->is_purchasable()
            &&
            $product->is_in_stock(),

        'in_stock' =>
            $product->is_in_stock(),

        'stock_quantity' =>
            $quantity !== null
                ? (float)
                    $quantity
                : null,

        'source' =>
            'woocommerce',
    ];
}


/*
|--------------------------------------------------------------------------
| Product Categories
|--------------------------------------------------------------------------
*/

function storefleet_api_product_categories(
    $product_id
) {
    $terms =
        wp_get_post_terms(
            $product_id,
            'product_cat'
        );


    if (is_wp_error($terms)) {
        return array();
    }


    $categories =
        array();


    foreach (
        $terms as
        $term
    ) {
        $categories[] = [
            'id' =>
                absint(
                    $term->term_id
                ),

            'name' =>
                $term->name,

            'slug' =>
                $term->slug,
        ];
    }


    return $categories;
}


/*
|--------------------------------------------------------------------------
| Product Images
|--------------------------------------------------------------------------
*/

function storefleet_api_product_images(
    WC_Product $product
) {
    $ids =
        array();


    $primary_id =
        absint(
            $product->get_image_id()
        );


    if ($primary_id) {
        $ids[] =
            $primary_id;
    }


    foreach (
        $product->get_gallery_image_ids()
        as
        $image_id
    ) {
        $image_id =
            absint(
                $image_id
            );


        if (
            $image_id
            &&
            !in_array(
                $image_id,
                $ids,
                true
            )
        ) {
            $ids[] =
                $image_id;
        }
    }


    $gallery =
        array();


    foreach (
        $ids as
        $image_id
    ) {
        $url =
            wp_get_attachment_image_url(
                $image_id,
                'full'
            );


        if ($url) {
            $gallery[] =
                $url;
        }
    }


    return [
        'primary' =>
            !empty($gallery)
                ? $gallery[0]
                : '',

        'gallery' =>
            $gallery,
    ];
}


/*
|--------------------------------------------------------------------------
| Merchant Summary
|--------------------------------------------------------------------------
*/

function storefleet_api_product_prepare_merchant_summary(
    $merchant_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    $merchant =
        get_userdata(
            $merchant_id
        );


    $profile =
        get_user_meta(
            $merchant_id,
            'dokan_profile_settings',
            true
        );


    if (!is_array($profile)) {
        $profile =
            array();
    }


    $name =
        isset(
            $profile[
                'store_name'
            ]
        )
            ? sanitize_text_field(
                $profile[
                    'store_name'
                ]
            )
            : '';


    if (
        $name === ''
        &&
        $merchant
    ) {
        $name =
            $merchant->display_name;
    }


    $slug =
        '';


    if (
        function_exists(
            'storefleet_api_get_store_slug'
        )
        &&
        $merchant
    ) {
        $slug =
            storefleet_api_get_store_slug(
                $merchant_id,
                $profile,
                $merchant
            );
    }


    if ($slug === '') {
        $slug =
            sanitize_title(
                $name
            );
    }


    return [
        'id' =>
            $merchant_id,

        'name' =>
            $name,

        'slug' =>
            $slug,
    ];
}


/*
|--------------------------------------------------------------------------
| Branch Summary
|--------------------------------------------------------------------------
*/

function storefleet_api_product_prepare_branch_summary(
    $branch
) {
    if (!$branch) {
        return null;
    }


    $address =
        array_filter(
            [
                isset(
                    $branch->address_line_1
                )
                    ? $branch->address_line_1
                    : '',

                isset(
                    $branch->address_line_2
                )
                    ? $branch->address_line_2
                    : '',

                isset(
                    $branch->city
                )
                    ? $branch->city
                    : '',

                isset(
                    $branch->state
                )
                    ? $branch->state
                    : '',

                isset(
                    $branch->postcode
                )
                    ? $branch->postcode
                    : '',
            ],
            function ($value) {
                return
                    trim(
                        (string)
                        $value
                    ) !== '';
            }
        );


    return [
        'id' =>
            absint(
                $branch->id
            ),

        'name' =>
            sanitize_text_field(
                $branch->name
            ),

        'slug' =>
            sanitize_title(
                $branch->slug
            ),

        'is_primary' =>
            !empty(
                $branch->is_primary
            ),

        'is_active' =>
            !empty(
                $branch->is_active
            ),

        'address' =>
            implode(
                ', ',
                $address
            ),

        'location' => [
            'latitude' =>
                isset(
                    $branch->latitude
                )
                &&
                $branch->latitude !== null
                    ? (float)
                    $branch->latitude
                    : null,

            'longitude' =>
                isset(
                    $branch->longitude
                )
                &&
                $branch->longitude !== null
                    ? (float)
                    $branch->longitude
                    : null,
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| Fallback Find Merchant By Store Slug
|--------------------------------------------------------------------------
*/

function storefleet_api_product_find_merchant_by_slug(
    $store_slug
) {
    $store_slug =
        sanitize_title(
            $store_slug
        );


    if ($store_slug === '') {
        return 0;
    }


    $users =
        get_users(
            [
                'role' =>
                    'seller',

                'fields' =>
                    'all',
            ]
        );


    foreach (
        $users as
        $merchant
    ) {
        $merchant_id =
            absint(
                $merchant->ID
            );


        $profile =
            get_user_meta(
                $merchant_id,
                'dokan_profile_settings',
                true
            );


        if (!is_array($profile)) {
            $profile =
                array();
        }


        $name =
            isset(
                $profile[
                    'store_name'
                ]
            )
                ? sanitize_text_field(
                    $profile[
                        'store_name'
                    ]
                )
                : $merchant->display_name;


        $candidate =
            sanitize_title(
                $name
            );


        if (
            $candidate ===
            $store_slug
        ) {
            return $merchant_id;
        }
    }


    return 0;
}
