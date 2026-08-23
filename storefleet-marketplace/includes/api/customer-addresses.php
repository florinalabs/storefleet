<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Address API
|--------------------------------------------------------------------------
*/

add_action(
    'rest_api_init',
    function () {

        /*
        |--------------------------------------------------------------------------
        | List Addresses
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/addresses',
            array(
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_customer_addresses_list',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Create Address
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/addresses',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_address_create',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Update Address
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/addresses/(?P<address_id>[a-zA-Z0-9\-]+)',
            array(
                'methods' =>
                    WP_REST_Server::EDITABLE,

                'callback' =>
                    'storefleet_customer_address_update',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Delete Address
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/addresses/(?P<address_id>[a-zA-Z0-9\-]+)',
            array(
                'methods' =>
                    WP_REST_Server::DELETABLE,

                'callback' =>
                    'storefleet_customer_address_delete',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Set Default Address
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/addresses/(?P<address_id>[a-zA-Z0-9\-]+)/default',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_address_set_default',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );
    }
);


/*
|--------------------------------------------------------------------------
| List Customer Addresses
|--------------------------------------------------------------------------
*/

function storefleet_customer_addresses_list(
    WP_REST_Request $request
) {
    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $addresses =
        storefleet_customer_get_addresses(
            $session['user_id']
        );


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'addresses' =>
                array_values(
                    $addresses
                ),
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Create Customer Address
|--------------------------------------------------------------------------
*/

function storefleet_customer_address_create(
    WP_REST_Request $request
) {
    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $user_id =
        absint(
            $session['user_id']
        );


    $addresses =
        storefleet_customer_get_addresses(
            $user_id
        );


    /*
    |--------------------------------------------------------------------------
    | Address Limit
    |--------------------------------------------------------------------------
    */

    if (
        count($addresses) >= 10
    ) {
        return new WP_Error(
            'storefleet_address_limit',
            'You can save up to 10 delivery addresses.',
            array(
                'status' => 422,
            )
        );
    }


    $validated =
        storefleet_customer_validate_address_request(
            $request
        );


    if (is_wp_error($validated)) {
        return $validated;
    }


    $address_id =
        wp_generate_uuid4();


    $is_first_address =
        count($addresses) === 0;


    $is_default =
        $is_first_address ||
        storefleet_customer_parse_boolean(
            $request->get_param(
                'is_default'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Remove Existing Default
    |--------------------------------------------------------------------------
    */

    if ($is_default) {
        $addresses =
            storefleet_customer_clear_default_addresses(
                $addresses
            );
    }


    $now =
        current_time(
            'mysql',
            true
        );


    $address =
        array_merge(
            $validated,
            array(
                'id' =>
                    $address_id,

                'is_default' =>
                    $is_default,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            )
        );


    $addresses[
        $address_id
    ] =
        $address;


    storefleet_customer_save_addresses(
        $user_id,
        $addresses
    );


    if ($is_default) {
        storefleet_customer_sync_default_address_to_woocommerce(
            $user_id,
            $address
        );
    }


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Delivery address added successfully.',

            'address' =>
                $address,
        ),
        201
    );
}


/*
|--------------------------------------------------------------------------
| Update Customer Address
|--------------------------------------------------------------------------
*/

function storefleet_customer_address_update(
    WP_REST_Request $request
) {
    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $user_id =
        absint(
            $session['user_id']
        );


    $address_id =
        sanitize_text_field(
            (string)
            $request->get_param(
                'address_id'
            )
        );


    $addresses =
        storefleet_customer_get_addresses(
            $user_id
        );


    if (
        !isset(
            $addresses[
                $address_id
            ]
        )
    ) {
        return new WP_Error(
            'storefleet_address_not_found',
            'Delivery address could not be found.',
            array(
                'status' => 404,
            )
        );
    }


    $validated =
        storefleet_customer_validate_address_request(
            $request
        );


    if (is_wp_error($validated)) {
        return $validated;
    }


    $existing =
        $addresses[
            $address_id
        ];


    $make_default =
        storefleet_customer_parse_boolean(
            $request->get_param(
                'is_default'
            )
        );


    $is_default =
        !empty(
            $existing[
                'is_default'
            ]
        );


    if ($make_default) {
        $addresses =
            storefleet_customer_clear_default_addresses(
                $addresses
            );

        $is_default =
            true;
    }


    $address =
        array_merge(
            $validated,
            array(
                'id' =>
                    $address_id,

                'is_default' =>
                    $is_default,

                'created_at' =>
                    isset(
                        $existing[
                            'created_at'
                        ]
                    )
                        ? $existing[
                            'created_at'
                        ]
                        : current_time(
                            'mysql',
                            true
                        ),

                'updated_at' =>
                    current_time(
                        'mysql',
                        true
                    ),
            )
        );


    $addresses[
        $address_id
    ] =
        $address;


    storefleet_customer_save_addresses(
        $user_id,
        $addresses
    );


    if ($is_default) {
        storefleet_customer_sync_default_address_to_woocommerce(
            $user_id,
            $address
        );
    }


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Delivery address updated successfully.',

            'address' =>
                $address,
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Delete Customer Address
|--------------------------------------------------------------------------
*/

function storefleet_customer_address_delete(
    WP_REST_Request $request
) {
    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $user_id =
        absint(
            $session['user_id']
        );


    $address_id =
        sanitize_text_field(
            (string)
            $request->get_param(
                'address_id'
            )
        );


    $addresses =
        storefleet_customer_get_addresses(
            $user_id
        );


    if (
        !isset(
            $addresses[
                $address_id
            ]
        )
    ) {
        return new WP_Error(
            'storefleet_address_not_found',
            'Delivery address could not be found.',
            array(
                'status' => 404,
            )
        );
    }


    $was_default =
        !empty(
            $addresses[
                $address_id
            ]['is_default']
        );


    unset(
        $addresses[
            $address_id
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Promote Another Address
    |--------------------------------------------------------------------------
    */

    if (
        $was_default &&
        count($addresses) > 0
    ) {
        $first_id =
            array_key_first(
                $addresses
            );


        if ($first_id !== null) {
            $addresses[
                $first_id
            ]['is_default'] =
                true;


            $addresses[
                $first_id
            ]['updated_at'] =
                current_time(
                    'mysql',
                    true
                );


            storefleet_customer_sync_default_address_to_woocommerce(
                $user_id,
                $addresses[
                    $first_id
                ]
            );
        }
    }


    storefleet_customer_save_addresses(
        $user_id,
        $addresses
    );


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Delivery address deleted successfully.',
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Set Default Address
|--------------------------------------------------------------------------
*/

function storefleet_customer_address_set_default(
    WP_REST_Request $request
) {
    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $user_id =
        absint(
            $session['user_id']
        );


    $address_id =
        sanitize_text_field(
            (string)
            $request->get_param(
                'address_id'
            )
        );


    $addresses =
        storefleet_customer_get_addresses(
            $user_id
        );


    if (
        !isset(
            $addresses[
                $address_id
            ]
        )
    ) {
        return new WP_Error(
            'storefleet_address_not_found',
            'Delivery address could not be found.',
            array(
                'status' => 404,
            )
        );
    }


    $addresses =
        storefleet_customer_clear_default_addresses(
            $addresses
        );


    $addresses[
        $address_id
    ]['is_default'] =
        true;


    $addresses[
        $address_id
    ]['updated_at'] =
        current_time(
            'mysql',
            true
        );


    storefleet_customer_save_addresses(
        $user_id,
        $addresses
    );


    storefleet_customer_sync_default_address_to_woocommerce(
        $user_id,
        $addresses[
            $address_id
        ]
    );


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Default delivery address updated.',

            'address' =>
                $addresses[
                    $address_id
                ],
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Validate Address Input
|--------------------------------------------------------------------------
*/

function storefleet_customer_validate_address_request(
    WP_REST_Request $request
) {
    $label =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'label'
                )
            )
        );


    $first_name =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'first_name'
                )
            )
        );


    $last_name =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'last_name'
                )
            )
        );


    $phone =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'phone'
                )
            )
        );


    $address_1 =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'address_1'
                )
            )
        );


    $address_2 =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'address_2'
                )
            )
        );


    $barangay =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'barangay'
                )
            )
        );


    $city =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'city'
                )
            )
        );


    $province =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'province'
                )
            )
        );


    $postcode =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'postcode'
                )
            )
        );


    $country =
        strtoupper(
            sanitize_text_field(
                wp_unslash(
                    (string)
                    $request->get_param(
                        'country'
                    )
                )
            )
        );


    if ($country === '') {
        $country =
            'PH';
    }


    /*
    |--------------------------------------------------------------------------
    | Required Fields
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {
        return new WP_Error(
            'storefleet_address_first_name_required',
            'First name is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($last_name === '') {
        return new WP_Error(
            'storefleet_address_last_name_required',
            'Last name is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($phone === '') {
        return new WP_Error(
            'storefleet_address_phone_required',
            'Mobile number is required.',
            array(
                'status' => 422,
            )
        );
    }


    if (
        !preg_match(
            '/^[0-9+\-\s().]{7,25}$/',
            $phone
        )
    ) {
        return new WP_Error(
            'storefleet_address_invalid_phone',
            'Please enter a valid mobile number.',
            array(
                'status' => 422,
            )
        );
    }


    if ($address_1 === '') {
        return new WP_Error(
            'storefleet_address_line_required',
            'Street address is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($city === '') {
        return new WP_Error(
            'storefleet_address_city_required',
            'City or municipality is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($province === '') {
        return new WP_Error(
            'storefleet_address_province_required',
            'Province is required.',
            array(
                'status' => 422,
            )
        );
    }


    if (
        !preg_match(
            '/^[A-Z]{2}$/',
            $country
        )
    ) {
        return new WP_Error(
            'storefleet_address_invalid_country',
            'Please enter a valid country code.',
            array(
                'status' => 422,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Coordinates
    |--------------------------------------------------------------------------
    */

    $latitude =
        storefleet_customer_parse_coordinate(
            $request->get_param(
                'latitude'
            ),
            -90,
            90
        );


    if (is_wp_error($latitude)) {
        return $latitude;
    }


    $longitude =
        storefleet_customer_parse_coordinate(
            $request->get_param(
                'longitude'
            ),
            -180,
            180
        );


    if (is_wp_error($longitude)) {
        return $longitude;
    }


    return array(
        'label' =>
            $label !== ''
                ? $label
                : 'Home',

        'first_name' =>
            $first_name,

        'last_name' =>
            $last_name,

        'phone' =>
            $phone,

        'address_1' =>
            $address_1,

        'address_2' =>
            $address_2,

        'barangay' =>
            $barangay,

        'city' =>
            $city,

        'province' =>
            $province,

        'postcode' =>
            $postcode,

        'country' =>
            $country,

        'latitude' =>
            $latitude,

        'longitude' =>
            $longitude,
    );
}


/*
|--------------------------------------------------------------------------
| Parse Coordinate
|--------------------------------------------------------------------------
*/

function storefleet_customer_parse_coordinate(
    $value,
    $minimum,
    $maximum
) {
    if (
        $value === null ||
        $value === ''
    ) {
        return null;
    }


    if (!is_numeric($value)) {
        return new WP_Error(
            'storefleet_address_invalid_coordinate',
            'Address coordinates are invalid.',
            array(
                'status' => 422,
            )
        );
    }


    $coordinate =
        (float)
        $value;


    if (
        $coordinate < $minimum ||
        $coordinate > $maximum
    ) {
        return new WP_Error(
            'storefleet_address_invalid_coordinate',
            'Address coordinates are invalid.',
            array(
                'status' => 422,
            )
        );
    }


    return $coordinate;
}


/*
|--------------------------------------------------------------------------
| Get Addresses
|--------------------------------------------------------------------------
*/

function storefleet_customer_get_addresses(
    $user_id
) {
    $addresses =
        get_user_meta(
            absint($user_id),
            'storefleet_customer_addresses',
            true
        );


    if (!is_array($addresses)) {
        return array();
    }


    return $addresses;
}


/*
|--------------------------------------------------------------------------
| Save Addresses
|--------------------------------------------------------------------------
*/

function storefleet_customer_save_addresses(
    $user_id,
    array $addresses
) {
    update_user_meta(
        absint($user_id),
        'storefleet_customer_addresses',
        $addresses
    );
}


/*
|--------------------------------------------------------------------------
| Clear Default Flags
|--------------------------------------------------------------------------
*/

function storefleet_customer_clear_default_addresses(
    array $addresses
) {
    foreach (
        $addresses as
        $address_id =>
        $address
    ) {
        $addresses[
            $address_id
        ]['is_default'] =
            false;
    }


    return $addresses;
}


/*
|--------------------------------------------------------------------------
| Sync Default Address To WooCommerce
|--------------------------------------------------------------------------
|
| StoreFleet keeps multiple delivery addresses in its own metadata.
|
| The currently selected default address is also synchronized with standard
| WooCommerce shipping metadata so future order creation remains compatible
| with WooCommerce.
|
*/

function storefleet_customer_sync_default_address_to_woocommerce(
    $user_id,
    array $address
) {
    $user_id =
        absint($user_id);


    update_user_meta(
        $user_id,
        'shipping_first_name',
        $address['first_name']
    );


    update_user_meta(
        $user_id,
        'shipping_last_name',
        $address['last_name']
    );


    update_user_meta(
        $user_id,
        'shipping_address_1',
        $address['address_1']
    );


    update_user_meta(
        $user_id,
        'shipping_address_2',
        $address['address_2']
    );


    update_user_meta(
        $user_id,
        'shipping_city',
        $address['city']
    );


    update_user_meta(
        $user_id,
        'shipping_state',
        $address['province']
    );


    update_user_meta(
        $user_id,
        'shipping_postcode',
        $address['postcode']
    );


    update_user_meta(
        $user_id,
        'shipping_country',
        $address['country']
    );


    update_user_meta(
        $user_id,
        'shipping_phone',
        $address['phone']
    );


    update_user_meta(
        $user_id,
        'storefleet_shipping_barangay',
        $address['barangay']
    );


    if (
        $address['latitude'] !== null
    ) {
        update_user_meta(
            $user_id,
            'storefleet_shipping_latitude',
            $address['latitude']
        );
    } else {
        delete_user_meta(
            $user_id,
            'storefleet_shipping_latitude'
        );
    }


    if (
        $address['longitude'] !== null
    ) {
        update_user_meta(
            $user_id,
            'storefleet_shipping_longitude',
            $address['longitude']
        );
    } else {
        delete_user_meta(
            $user_id,
            'storefleet_shipping_longitude'
        );
    }
}