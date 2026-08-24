<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Merchant Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_merchant_branches(
    $merchant_id,
    $active_only = false
) {
    global $wpdb;

    $merchant_id =
        absint($merchant_id);

    if (!$merchant_id) {
        return array();
    }

    $table =
        storefleet_branches_table();

    if ($active_only) {
        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$table}
                WHERE merchant_id = %d
                AND is_active = 1
                ORDER BY name ASC
                ",
                $merchant_id
            )
        );
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$table}
            WHERE merchant_id = %d
            ORDER BY
                is_active DESC,
                name ASC
            ",
            $merchant_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get One Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_branch(
    $branch_id
) {
    global $wpdb;

    $branch_id =
        absint($branch_id);

    if (!$branch_id) {
        return null;
    }

    $table =
        storefleet_branches_table();

    return $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$table}
            WHERE id = %d
            LIMIT 1
            ",
            $branch_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Merchant Owns Branch
|--------------------------------------------------------------------------
*/

function storefleet_merchant_owns_branch(
    $merchant_id,
    $branch_id
) {
    global $wpdb;

    $merchant_id =
        absint($merchant_id);

    $branch_id =
        absint($branch_id);

    if (
        !$merchant_id ||
        !$branch_id
    ) {
        return false;
    }

    $table =
        storefleet_branches_table();

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
                $branch_id,
                $merchant_id
            )
        );

    return !empty($found);
}


/*
|--------------------------------------------------------------------------
| Generate Unique Branch Slug
|--------------------------------------------------------------------------
*/

function storefleet_generate_branch_slug(
    $merchant_id,
    $branch_name
) {
    global $wpdb;

    $merchant_id =
        absint($merchant_id);

    $table =
        storefleet_branches_table();

    $base_slug =
        sanitize_title(
            $branch_name
        );

    if ($base_slug === '') {
        $base_slug =
            'branch';
    }

    $slug =
        $base_slug;

    $counter =
        2;

    while (true) {
        $existing =
            $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$table}
                    WHERE merchant_id = %d
                    AND slug = %s
                    LIMIT 1
                    ",
                    $merchant_id,
                    $slug
                )
            );

        if (!$existing) {
            return $slug;
        }

        $slug =
            $base_slug .
            '-' .
            $counter;

        $counter++;
    }
}


/*
|--------------------------------------------------------------------------
| Get Branch Address
|--------------------------------------------------------------------------
*/

function storefleet_get_branch_address(
    $branch
) {
    if (!$branch) {
        return '';
    }

    $parts =
        array_filter(
            array(
                $branch->address_line_1 ?? '',
                $branch->address_line_2 ?? '',
                $branch->city ?? '',
                $branch->state ?? '',
                $branch->postcode ?? '',
            )
        );

    return implode(
        ', ',
        $parts
    );
}


/*
|--------------------------------------------------------------------------
| Build Branch Geocoding Address
|--------------------------------------------------------------------------
*/

function storefleet_build_branch_geocoding_address(
    array $address
) {
    $country =
        isset(
            $address['country']
        ) &&
        $address['country'] !== ''
            ? $address['country']
            : 'Philippines';


    $parts =
        array_filter(
            array(
                $address['address_line_1']
                    ?? '',

                $address['address_line_2']
                    ?? '',

                $address['city']
                    ?? '',

                $address['state']
                    ?? '',

                $address['postcode']
                    ?? '',

                $country,
            ),
            function ($value) {
                return
                    trim(
                        (string)
                        $value
                    ) !== '';
            }
        );


    return implode(
        ', ',
        $parts
    );
}


/*
|--------------------------------------------------------------------------
| Geocode Branch Address
|--------------------------------------------------------------------------
|
| Development geocoder:
| OpenStreetMap Nominatim
|
| This runs server-side from WordPress.
|
| The merchant never provides latitude or longitude.
|
| The function returns:
|
| [
|     'latitude'     => 14.123,
|     'longitude'    => 121.123,
|     'display_name' => '...',
| ]
|
| or WP_Error.
|
*/

function storefleet_geocode_branch_address(
    array $address
) {
    /*
    |--------------------------------------------------------------------------
    | Build Address
    |--------------------------------------------------------------------------
    */

    $full_address =
        storefleet_build_branch_geocoding_address(
            $address
        );


    if ($full_address === '') {
        return new WP_Error(
            'storefleet_geocode_empty_address',
            'A complete branch address is required.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Avoid repeatedly requesting coordinates for exactly the same address.
    |
    */

    $cache_key =
        'storefleet_geocode_' .
        md5(
            strtolower(
                $full_address
            )
        );


    $cached =
        get_transient(
            $cache_key
        );


    if (
        is_array($cached) &&
        isset(
            $cached['latitude'],
            $cached['longitude']
        )
    ) {
        return $cached;
    }


    /*
    |--------------------------------------------------------------------------
    | Geocoder Endpoint
    |--------------------------------------------------------------------------
    |
    | Filter allows StoreFleet to replace Nominatim later without changing
    | branch business logic.
    |
    */

    $endpoint =
        apply_filters(
            'storefleet_geocoding_endpoint',
            'https://nominatim.openstreetmap.org/search'
        );


    /*
    |--------------------------------------------------------------------------
    | Request URL
    |--------------------------------------------------------------------------
    */

    $url =
        add_query_arg(
            array(
                'format' =>
                    'jsonv2',

                'limit' =>
                    1,

                'countrycodes' =>
                    'ph',

                'addressdetails' =>
                    1,

                'q' =>
                    $full_address,
            ),
            $endpoint
        );


    /*
    |--------------------------------------------------------------------------
    | Request
    |--------------------------------------------------------------------------
    */

    $response =
        wp_remote_get(
            $url,
            array(
                'timeout' =>
                    12,

                'redirection' =>
                    3,

                'headers' =>
                    array(
                        'Accept' =>
                            'application/json',

                        'Accept-Language' =>
                            'en',

                        'User-Agent' =>
                            'StoreFleet/' .
                            (
                                defined(
                                    'STOREFLEET_VERSION'
                                )
                                    ? STOREFLEET_VERSION
                                    : '1.0'
                            ) .
                            ' (' .
                            home_url('/') .
                            ')',
                    ),
            )
        );


    if (
        is_wp_error(
            $response
        )
    ) {
        return new WP_Error(
            'storefleet_geocode_request_failed',
            'The address geocoding service could not be reached.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HTTP Status
    |--------------------------------------------------------------------------
    */

    $status_code =
        wp_remote_retrieve_response_code(
            $response
        );


    if ($status_code !== 200) {
        return new WP_Error(
            'storefleet_geocode_http_error',
            'The address geocoding service returned an error.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode Response
    |--------------------------------------------------------------------------
    */

    $body =
        wp_remote_retrieve_body(
            $response
        );


    $results =
        json_decode(
            $body,
            true
        );


    if (
        !is_array($results) ||
        empty($results) ||
        !isset(
            $results[0]['lat'],
            $results[0]['lon']
        )
    ) {
        return new WP_Error(
            'storefleet_geocode_not_found',
            'The branch address could not be located.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Coordinates
    |--------------------------------------------------------------------------
    */

    $latitude =
        (float)
        $results[0]['lat'];


    $longitude =
        (float)
        $results[0]['lon'];


    if (
        $latitude < -90 ||
        $latitude > 90 ||
        $longitude < -180 ||
        $longitude > 180
    ) {
        return new WP_Error(
            'storefleet_geocode_invalid_coordinates',
            'The geocoding service returned invalid coordinates.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Result
    |--------------------------------------------------------------------------
    */

    $coordinates =
        array(
            'latitude' =>
                $latitude,

            'longitude' =>
                $longitude,

            'display_name' =>
                isset(
                    $results[0]['display_name']
                )
                    ? sanitize_text_field(
                        $results[0]['display_name']
                    )
                    : '',
        );


    /*
    |--------------------------------------------------------------------------
    | Cache Result
    |--------------------------------------------------------------------------
    */

    set_transient(
        $cache_key,
        $coordinates,
        MONTH_IN_SECONDS
    );


    return $coordinates;
}