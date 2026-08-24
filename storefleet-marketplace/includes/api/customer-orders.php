<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Order API
|--------------------------------------------------------------------------
*/

add_action(
    'rest_api_init',
    function () {

        register_rest_route(
            'storefleet/v1',
            '/orders',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_create_order',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );
    }
);


/*
|--------------------------------------------------------------------------
| Create Customer Order
|--------------------------------------------------------------------------
|
| Request:
|
| {
|   "idempotency_key": "checkout-unique-key",
|   "address_id": "saved-address-uuid",
|   "items": [
|     {
|       "product_id": 123,
|       "quantity": 2
|     }
|   ]
| }
|
| IMPORTANT:
|
| The browser does not provide prices.
|
| WooCommerce resolves product prices server-side.
|
*/

function storefleet_customer_create_order(
    WP_REST_Request $request
) {
    /*
    |--------------------------------------------------------------------------
    | WooCommerce Required
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'wc_create_order'
        ) ||
        !function_exists(
            'wc_get_product'
        )
    ) {
        return new WP_Error(
            'storefleet_woocommerce_unavailable',
            'WooCommerce ordering is currently unavailable.',
            array(
                'status' => 503,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Session
    |--------------------------------------------------------------------------
    */

    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $customer_id =
        absint(
            $session['user_id']
        );


    if ($customer_id <= 0) {
        return new WP_Error(
            'storefleet_invalid_customer',
            'Customer account could not be determined.',
            array(
                'status' => 401,
            )
        );
    }


    $customer =
        get_user_by(
            'id',
            $customer_id
        );


    if (!$customer) {
        return new WP_Error(
            'storefleet_customer_not_found',
            'Customer account could not be found.',
            array(
                'status' => 404,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Request Body
    |--------------------------------------------------------------------------
    */

    $params =
        $request->get_json_params();


    if (!is_array($params)) {
        $params =
            array();
    }


    /*
    |--------------------------------------------------------------------------
    | Idempotency Key
    |--------------------------------------------------------------------------
    |
    | The same checkout submission must always use the same key.
    |
    | If Next.js retries the request using the same key, StoreFleet returns
    | the existing order instead of creating another WooCommerce order.
    |
    */

    $idempotency_key =
        isset(
            $params[
                'idempotency_key'
            ]
        )
            ? sanitize_text_field(
                wp_unslash(
                    (string)
                    $params[
                        'idempotency_key'
                    ]
                )
            )
            : '';


    if ($idempotency_key === '') {
        return new WP_Error(
            'storefleet_idempotency_key_required',
            'An idempotency key is required.',
            array(
                'status' => 422,
            )
        );
    }


    if (
        strlen(
            $idempotency_key
        ) < 16 ||
        strlen(
            $idempotency_key
        ) > 128 ||
        !preg_match(
            '/^[A-Za-z0-9._:-]+$/',
            $idempotency_key
        )
    ) {
        return new WP_Error(
            'storefleet_invalid_idempotency_key',
            'The idempotency key is invalid.',
            array(
                'status' => 422,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Existing Order
    |--------------------------------------------------------------------------
    */

    $existing_order =
        storefleet_customer_find_idempotent_order(
            $customer_id,
            $idempotency_key
        );


    if (
        $existing_order instanceof
        WC_Order
    ) {
        return new WP_REST_Response(
            storefleet_customer_order_response(
                $existing_order,
                true
            ),
            200
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Acquire Order Creation Lock
    |--------------------------------------------------------------------------
    */

    $lock_key =
        storefleet_customer_order_lock_key(
            $customer_id,
            $idempotency_key
        );


    $lock_acquired =
        storefleet_customer_acquire_order_lock(
            $lock_key
        );


    if (!$lock_acquired) {

        /*
        |--------------------------------------------------------------------------
        | Check Again For Completed Retry
        |--------------------------------------------------------------------------
        */

        $existing_order =
            storefleet_customer_find_idempotent_order(
                $customer_id,
                $idempotency_key
            );


        if (
            $existing_order instanceof
            WC_Order
        ) {
            return new WP_REST_Response(
                storefleet_customer_order_response(
                    $existing_order,
                    true
                ),
                200
            );
        }


        return new WP_Error(
            'storefleet_order_processing',
            'This order request is already being processed. Please retry.',
            array(
                'status' => 409,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Order
    |--------------------------------------------------------------------------
    */

    $order =
        null;


    try {

        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

        $address_id =
            isset(
                $params[
                    'address_id'
                ]
            )
                ? sanitize_text_field(
                    wp_unslash(
                        (string)
                        $params[
                            'address_id'
                        ]
                    )
                )
                : '';


        if ($address_id === '') {
            throw new StoreFleet_Order_Exception(
                'storefleet_address_required',
                'A delivery address is required.',
                422
            );
        }


        if (
            !function_exists(
                'storefleet_customer_get_addresses'
            )
        ) {
            throw new StoreFleet_Order_Exception(
                'storefleet_addresses_unavailable',
                'Customer addresses are currently unavailable.',
                503
            );
        }


        $addresses =
            storefleet_customer_get_addresses(
                $customer_id
            );


        if (
            !isset(
                $addresses[
                    $address_id
                ]
            ) ||
            !is_array(
                $addresses[
                    $address_id
                ]
            )
        ) {
            throw new StoreFleet_Order_Exception(
                'storefleet_address_not_found',
                'The selected delivery address could not be found.',
                404
            );
        }


        $address =
            $addresses[
                $address_id
            ];


        /*
        |--------------------------------------------------------------------------
        | Validate Address
        |--------------------------------------------------------------------------
        */

        $required_address_fields =
            array(
                'first_name',
                'last_name',
                'phone',
                'address_1',
                'city',
                'province',
                'country',
            );


        foreach (
            $required_address_fields as
            $field
        ) {
            if (
                !isset(
                    $address[
                        $field
                    ]
                ) ||
                trim(
                    (string)
                    $address[
                        $field
                    ]
                ) === ''
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_incomplete_address',
                    'The selected delivery address is incomplete.',
                    422
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Cart Items
        |--------------------------------------------------------------------------
        */

        $raw_items =
            isset(
                $params[
                    'items'
                ]
            )
                ? $params[
                    'items'
                ]
                : array();


        $items =
            storefleet_customer_normalize_order_items(
                $raw_items
            );


        if (is_wp_error($items)) {
            throw new StoreFleet_Order_Exception(
                $items->get_error_code(),
                $items->get_error_message(),
                isset(
                    $items->get_error_data()['status']
                )
                    ? absint(
                        $items->get_error_data()['status']
                    )
                    : 422
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Resolve WooCommerce Products
        |--------------------------------------------------------------------------
        */

        $resolved_items =
            array();


        foreach (
            $items as
            $item
        ) {
            $product_id =
                absint(
                    $item[
                        'product_id'
                    ]
                );


            $quantity =
                absint(
                    $item[
                        'quantity'
                    ]
                );


            $product =
                wc_get_product(
                    $product_id
                );


            if (!$product) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_product_not_found',
                    sprintf(
                        'Product #%d could not be found.',
                        $product_id
                    ),
                    404
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Published / Purchasable
            |--------------------------------------------------------------------------
            */

            if (
                $product->get_status() !==
                'publish'
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_product_unavailable',
                    sprintf(
                        '%s is currently unavailable.',
                        $product->get_name()
                    ),
                    409
                );
            }


            if (
                !$product->is_purchasable()
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_product_not_purchasable',
                    sprintf(
                        '%s cannot currently be purchased.',
                        $product->get_name()
                    ),
                    409
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Price
            |--------------------------------------------------------------------------
            |
            | This is resolved by WooCommerce on the server.
            |
            | We never accept a customer price from Next.js/browser input.
            |
            */

            $price =
                $product->get_price();


            if (
                $price === ''
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_product_price_missing',
                    sprintf(
                        '%s does not currently have a valid price.',
                        $product->get_name()
                    ),
                    409
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Stock
            |--------------------------------------------------------------------------
            */

            if (
                !$product->is_in_stock()
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_product_out_of_stock',
                    sprintf(
                        '%s is out of stock.',
                        $product->get_name()
                    ),
                    409
                );
            }


            if (
                $product->managing_stock() &&
                !$product->backorders_allowed() &&
                !$product->has_enough_stock(
                    $quantity
                )
            ) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_insufficient_stock',
                    sprintf(
                        'There is not enough stock available for %s.',
                        $product->get_name()
                    ),
                    409
                );
            }


            $resolved_items[] =
                array(
                    'product' =>
                        $product,

                    'quantity' =>
                        $quantity,
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Create WooCommerce Order
        |--------------------------------------------------------------------------
        */

        $order =
            wc_create_order(
                array(
                    'customer_id' =>
                        $customer_id,

                    'status' =>
                        'pending',

                    'created_via' =>
                        'storefleet_checkout',
                )
            );


        if (is_wp_error($order)) {
            throw new StoreFleet_Order_Exception(
                'storefleet_order_create_failed',
                $order->get_error_message(),
                500
            );
        }


        if (
            !(
                $order instanceof
                WC_Order
            )
        ) {
            throw new StoreFleet_Order_Exception(
                'storefleet_order_create_failed',
                'The WooCommerce order could not be created.',
                500
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $order->set_customer_id(
            $customer_id
        );


        /*
        |--------------------------------------------------------------------------
        | Shipping Address
        |--------------------------------------------------------------------------
        */

        $shipping_address =
            array(
                'first_name' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'first_name'
                        ]
                    ),

                'last_name' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'last_name'
                        ]
                    ),

                'company' =>
                    '',

                'address_1' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'address_1'
                        ]
                    ),

                'address_2' =>
                    isset(
                        $address[
                            'address_2'
                        ]
                    )
                        ? sanitize_text_field(
                            (string)
                            $address[
                                'address_2'
                            ]
                        )
                        : '',

                'city' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'city'
                        ]
                    ),

                'state' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'province'
                        ]
                    ),

                'postcode' =>
                    isset(
                        $address[
                            'postcode'
                        ]
                    )
                        ? sanitize_text_field(
                            (string)
                            $address[
                                'postcode'
                            ]
                        )
                        : '',

                'country' =>
                    strtoupper(
                        sanitize_text_field(
                            (string)
                            $address[
                                'country'
                            ]
                        )
                    ),

                'phone' =>
                    sanitize_text_field(
                        (string)
                        $address[
                            'phone'
                        ]
                    ),
            );


        $order->set_address(
            $shipping_address,
            'shipping'
        );


        /*
        |--------------------------------------------------------------------------
        | Billing Address
        |--------------------------------------------------------------------------
        |
        | Until Xendit payment details are implemented, checkout uses the
        | selected delivery address as the customer's billing address.
        |
        */

        $billing_address =
            $shipping_address;


        $billing_address[
            'email'
        ] =
            sanitize_email(
                (string)
                $customer->user_email
            );


        $order->set_address(
            $billing_address,
            'billing'
        );


        /*
        |--------------------------------------------------------------------------
        | Add Products
        |--------------------------------------------------------------------------
        */

        foreach (
            $resolved_items as
            $resolved_item
        ) {
            $added_item_id =
                $order->add_product(
                    $resolved_item[
                        'product'
                    ],
                    $resolved_item[
                        'quantity'
                    ]
                );


            if (!$added_item_id) {
                throw new StoreFleet_Order_Exception(
                    'storefleet_order_item_failed',
                    'A product could not be added to the order.',
                    500
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | StoreFleet Metadata
        |--------------------------------------------------------------------------
        */

        $order->update_meta_data(
            '_storefleet_order_source',
            'marketplace'
        );


        $order->update_meta_data(
            '_storefleet_checkout_version',
            '1'
        );


        $order->update_meta_data(
            '_storefleet_customer_id',
            $customer_id
        );


        $order->update_meta_data(
            '_storefleet_address_id',
            $address_id
        );


        $order->update_meta_data(
            '_storefleet_idempotency_key',
            $idempotency_key
        );


        $order->update_meta_data(
            '_storefleet_payment_status',
            'awaiting_payment'
        );


        /*
        |--------------------------------------------------------------------------
        | Store StoreFleet Address Metadata
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $address[
                    'label'
                ]
            )
        ) {
            $order->update_meta_data(
                '_storefleet_address_label',
                sanitize_text_field(
                    (string)
                    $address[
                        'label'
                    ]
                )
            );
        }


        if (
            isset(
                $address[
                    'barangay'
                ]
            )
        ) {
            $order->update_meta_data(
                '_storefleet_shipping_barangay',
                sanitize_text_field(
                    (string)
                    $address[
                        'barangay'
                    ]
                )
            );
        }


        if (
            isset(
                $address[
                    'latitude'
                ]
            ) &&
            $address[
                'latitude'
            ] !== null &&
            is_numeric(
                $address[
                    'latitude'
                ]
            )
        ) {
            $order->update_meta_data(
                '_storefleet_shipping_latitude',
                (float)
                $address[
                    'latitude'
                ]
            );
        }


        if (
            isset(
                $address[
                    'longitude'
                ]
            ) &&
            $address[
                'longitude'
            ] !== null &&
            is_numeric(
                $address[
                    'longitude'
                ]
            )
        ) {
            $order->update_meta_data(
                '_storefleet_shipping_longitude',
                (float)
                $address[
                    'longitude'
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Totals
        |--------------------------------------------------------------------------
        |
        | No delivery fee is added yet.
        |
        | Lalamove will be introduced in the delivery issue.
        |
        */

        $order->calculate_totals();


        /*
        |--------------------------------------------------------------------------
        | Pending Payment
        |--------------------------------------------------------------------------
        */

        $order->set_status(
            'pending'
        );


        $order->save();


        /*
        |--------------------------------------------------------------------------
        | Internal Order Note
        |--------------------------------------------------------------------------
        */

        $order->add_order_note(
            'StoreFleet marketplace order created. Awaiting online payment.'
        );


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        $response =
            new WP_REST_Response(
                storefleet_customer_order_response(
                    $order,
                    false
                ),
                201
            );


        storefleet_customer_release_order_lock(
            $lock_key
        );


        return $response;

    } catch (
        StoreFleet_Order_Exception
        $exception
    ) {

        /*
        |--------------------------------------------------------------------------
        | Remove Partial Order
        |--------------------------------------------------------------------------
        */

        if (
            $order instanceof
            WC_Order
        ) {
            try {
                $order->delete(
                    true
                );
            } catch (Throwable $delete_error) {
                error_log(
                    sprintf(
                        'StoreFleet partial order cleanup failed for order #%d: %s',
                        $order->get_id(),
                        $delete_error->getMessage()
                    )
                );
            }
        }


        storefleet_customer_release_order_lock(
            $lock_key
        );


        return new WP_Error(
            $exception->get_storefleet_code(),
            $exception->getMessage(),
            array(
                'status' =>
                    $exception->get_status(),
            )
        );

    } catch (
        Throwable
        $exception
    ) {

        /*
        |--------------------------------------------------------------------------
        | Remove Partial Order
        |--------------------------------------------------------------------------
        */

        if (
            $order instanceof
            WC_Order
        ) {
            try {
                $order->delete(
                    true
                );
            } catch (Throwable $delete_error) {
                error_log(
                    sprintf(
                        'StoreFleet partial order cleanup failed for order #%d: %s',
                        $order->get_id(),
                        $delete_error->getMessage()
                    )
                );
            }
        }


        storefleet_customer_release_order_lock(
            $lock_key
        );


        error_log(
            sprintf(
                'StoreFleet order creation failed for customer #%d: %s',
                $customer_id,
                $exception->getMessage()
            )
        );


        return new WP_Error(
            'storefleet_order_create_failed',
            'The order could not be created. Please try again.',
            array(
                'status' => 500,
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| Normalize Order Items
|--------------------------------------------------------------------------
*/

function storefleet_customer_normalize_order_items(
    $raw_items
) {
    if (
        !is_array(
            $raw_items
        ) ||
        count(
            $raw_items
        ) === 0
    ) {
        return new WP_Error(
            'storefleet_order_items_required',
            'Your cart is empty.',
            array(
                'status' => 422,
            )
        );
    }


    if (
        count(
            $raw_items
        ) > 100
    ) {
        return new WP_Error(
            'storefleet_too_many_order_items',
            'The cart contains too many items.',
            array(
                'status' => 422,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Combine Duplicate Products
    |--------------------------------------------------------------------------
    */

    $normalized =
        array();


    foreach (
        $raw_items as
        $raw_item
    ) {
        if (
            !is_array(
                $raw_item
            )
        ) {
            return new WP_Error(
                'storefleet_invalid_order_item',
                'One or more cart items are invalid.',
                array(
                    'status' => 422,
                )
            );
        }


        $product_id =
            isset(
                $raw_item[
                    'product_id'
                ]
            )
                ? absint(
                    $raw_item[
                        'product_id'
                    ]
                )
                : 0;


        $quantity =
            isset(
                $raw_item[
                    'quantity'
                ]
            )
                ? absint(
                    $raw_item[
                        'quantity'
                    ]
                )
                : 0;


        if (
            $product_id <= 0 ||
            $quantity <= 0
        ) {
            return new WP_Error(
                'storefleet_invalid_order_item',
                'One or more cart items are invalid.',
                array(
                    'status' => 422,
                )
            );
        }


        if ($quantity > 99) {
            return new WP_Error(
                'storefleet_invalid_order_quantity',
                'A product quantity cannot exceed 99.',
                array(
                    'status' => 422,
                )
            );
        }


        if (
            !isset(
                $normalized[
                    $product_id
                ]
            )
        ) {
            $normalized[
                $product_id
            ] =
                array(
                    'product_id' =>
                        $product_id,

                    'quantity' =>
                        0,
                );
        }


        $normalized[
            $product_id
        ]['quantity'] +=
            $quantity;


        if (
            $normalized[
                $product_id
            ]['quantity'] >
            99
        ) {
            return new WP_Error(
                'storefleet_invalid_order_quantity',
                'A product quantity cannot exceed 99.',
                array(
                    'status' => 422,
                )
            );
        }
    }


    return array_values(
        $normalized
    );
}


/*
|--------------------------------------------------------------------------
| Find Existing Idempotent Order
|--------------------------------------------------------------------------
*/

function storefleet_customer_find_idempotent_order(
    $customer_id,
    $idempotency_key
) {
    $orders =
        wc_get_orders(
            array(
                'limit' =>
                    1,

                'customer_id' =>
                    absint(
                        $customer_id
                    ),

                'return' =>
                    'objects',

                'orderby' =>
                    'date',

                'order' =>
                    'DESC',

                'meta_query' =>
                    array(
                        array(
                            'key' =>
                                '_storefleet_idempotency_key',

                            'value' =>
                                (string)
                                $idempotency_key,

                            'compare' =>
                                '=',
                        ),
                    ),
            )
        );


    if (
        !empty(
            $orders
        ) &&
        $orders[0] instanceof
        WC_Order
    ) {
        return $orders[0];
    }


    return null;
}


/*
|--------------------------------------------------------------------------
| Order Response
|--------------------------------------------------------------------------
*/

function storefleet_customer_order_response(
    WC_Order $order,
    $idempotent_replay = false
) {
    $items =
        array();


    foreach (
        $order->get_items() as
        $item
    ) {
        $product =
            $item->get_product();


        $items[] =
            array(
                'product_id' =>
                    $product
                        ? (int)
                        $product->get_id()
                        : 0,

                'name' =>
                    (string)
                    $item->get_name(),

                'quantity' =>
                    (int)
                    $item->get_quantity(),

                'subtotal' =>
                    (string)
                    $item->get_subtotal(),

                'total' =>
                    (string)
                    $item->get_total(),
            );
    }


    return array(
        'success' =>
            true,

        'message' =>
            $idempotent_replay
                ? 'Existing order returned.'
                : 'Order created successfully.',

        'idempotent_replay' =>
            (bool)
            $idempotent_replay,

        'order' =>
            array(
                'id' =>
                    (int)
                    $order->get_id(),

                'number' =>
                    (string)
                    $order->get_order_number(),

                'status' =>
                    (string)
                    $order->get_status(),

                'currency' =>
                    (string)
                    $order->get_currency(),

                'subtotal' =>
                    (string)
                    $order->get_subtotal(),

                'total' =>
                    (string)
                    $order->get_total(),

                'address_id' =>
                    (string)
                    $order->get_meta(
                        '_storefleet_address_id'
                    ),

                'items' =>
                    $items,
            ),
    );
}


/*
|--------------------------------------------------------------------------
| Order Lock Key
|--------------------------------------------------------------------------
*/

function storefleet_customer_order_lock_key(
    $customer_id,
    $idempotency_key
) {
    return
        'storefleet_order_lock_' .
        hash(
            'sha256',
            absint(
                $customer_id
            ) .
            '|' .
            (string)
            $idempotency_key
        );
}


/*
|--------------------------------------------------------------------------
| Acquire Order Creation Lock
|--------------------------------------------------------------------------
|
| add_option() is used because WordPress option names are unique.
|
| This prevents two concurrent requests with the same idempotency key from
| both creating an order.
|
*/

function storefleet_customer_acquire_order_lock(
    $lock_key
) {
    $lock_key =
        sanitize_key(
            (string)
            $lock_key
        );


    if ($lock_key === '') {
        return false;
    }


    $now =
        time();


    $expires_at =
        $now + 60;


    $created =
        add_option(
            $lock_key,
            $expires_at,
            '',
            false
        );


    if ($created) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Recover Expired Lock
    |--------------------------------------------------------------------------
    */

    $existing_expiry =
        absint(
            get_option(
                $lock_key,
                0
            )
        );


    if (
        $existing_expiry > 0 &&
        $existing_expiry <= $now
    ) {
        delete_option(
            $lock_key
        );


        return add_option(
            $lock_key,
            $expires_at,
            '',
            false
        );
    }


    return false;
}


/*
|--------------------------------------------------------------------------
| Release Order Creation Lock
|--------------------------------------------------------------------------
*/

function storefleet_customer_release_order_lock(
    $lock_key
) {
    $lock_key =
        sanitize_key(
            (string)
            $lock_key
        );


    if ($lock_key !== '') {
        delete_option(
            $lock_key
        );
    }
}


/*
|--------------------------------------------------------------------------
| StoreFleet Order Exception
|--------------------------------------------------------------------------
*/

class StoreFleet_Order_Exception
    extends Exception
{
    protected $storefleet_code;

    protected $status;


    public function __construct(
        $storefleet_code,
        $message,
        $status = 500
    ) {
        $this->storefleet_code =
            sanitize_key(
                (string)
                $storefleet_code
            );


        $this->status =
            absint(
                $status
            );


        parent::__construct(
            (string)
            $message
        );
    }


    public function get_storefleet_code()
    {
        return
            $this->storefleet_code;
    }


    public function get_status()
    {
        return
            $this->status > 0
                ? $this->status
                : 500;
    }
}