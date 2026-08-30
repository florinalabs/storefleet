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
                ORDER BY
                    is_primary DESC,
                    name ASC
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
                is_primary DESC,
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
| Get Merchant Primary Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_primary_branch(
    $merchant_id,
    $active_only = true
) {
    global $wpdb;


    $merchant_id =
        absint(
            $merchant_id
        );


    if (!$merchant_id) {
        return null;
    }


    $table =
        storefleet_branches_table();


    $active_sql =
        $active_only
            ? 'AND is_active = 1'
            : '';


    return $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$table}
            WHERE merchant_id = %d
            AND is_primary = 1
            {$active_sql}
            ORDER BY id ASC
            LIMIT 1
            ",
            $merchant_id
        )
    );
}


/*
|--------------------------------------------------------------------------
| Branch Is Primary
|--------------------------------------------------------------------------
*/

function storefleet_branch_is_primary(
    $branch
) {
    if (
        is_numeric(
            $branch
        )
    ) {
        $branch =
            storefleet_get_branch(
                absint(
                    $branch
                )
            );
    }


    return
        is_object(
            $branch
        )
        &&
        isset(
            $branch->is_primary
        )
        &&
        (int)
        $branch->is_primary === 1;
}


/*
|--------------------------------------------------------------------------
| Set Merchant Primary Branch
|--------------------------------------------------------------------------
|
| The selected branch must:
|
| - belong to the merchant
| - be active
|
| One UPDATE statement clears the previous primary flag and promotes the
| selected branch, preventing two StoreFleet primary branches from being
| intentionally persisted for the same merchant.
|
*/

function storefleet_set_primary_branch(
    $merchant_id,
    $branch_id
) {
    global $wpdb;


    $merchant_id =
        absint(
            $merchant_id
        );


    $branch_id =
        absint(
            $branch_id
        );


    if (
        !$merchant_id ||
        !$branch_id
    ) {
        return new WP_Error(
            'storefleet_invalid_primary_branch',
            'A valid merchant and branch are required.'
        );
    }


    $branch =
        storefleet_get_branch(
            $branch_id
        );


    if (
        !$branch ||
        (int)
        $branch->merchant_id !==
        $merchant_id
    ) {
        return new WP_Error(
            'storefleet_primary_branch_not_owned',
            'The selected branch does not belong to this merchant.'
        );
    }


    if (
        (int)
        $branch->is_active !== 1
    ) {
        return new WP_Error(
            'storefleet_primary_branch_inactive',
            'The primary branch must be active.'
        );
    }


    $table =
        storefleet_branches_table();


    $result =
        $wpdb->query(
            $wpdb->prepare(
                "
                UPDATE {$table}
                SET
                    is_primary =
                        CASE
                            WHEN id = %d
                                THEN 1
                            ELSE 0
                        END,
                    updated_at =
                        CASE
                            WHEN id = %d
                                THEN %s
                            ELSE updated_at
                        END
                WHERE merchant_id = %d
                ",
                $branch_id,
                $branch_id,
                current_time(
                    'mysql'
                ),
                $merchant_id
            )
        );


    if ($result === false) {
        return new WP_Error(
            'storefleet_primary_branch_update_failed',
            'The primary branch could not be updated.'
        );
    }


    do_action(
        'storefleet_primary_branch_changed',
        $merchant_id,
        $branch_id,
        $branch
    );


    return true;
}


/*
|--------------------------------------------------------------------------
| Ensure Merchant Has Primary Branch
|--------------------------------------------------------------------------
|
| Used by branch creation / repair flows.
|
| Existing active primary branch is kept. When none exists, StoreFleet
| promotes the merchant's oldest active branch.
|
*/

function storefleet_ensure_merchant_primary_branch(
    $merchant_id
) {
    global $wpdb;


    $merchant_id =
        absint(
            $merchant_id
        );


    if (!$merchant_id) {
        return null;
    }


    $primary =
        storefleet_get_primary_branch(
            $merchant_id,
            true
        );


    if ($primary) {
        return $primary;
    }


    $table =
        storefleet_branches_table();


    $branch_id =
        absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$table}
                    WHERE merchant_id = %d
                    AND is_active = 1
                    ORDER BY
                        created_at ASC,
                        id ASC
                    LIMIT 1
                    ",
                    $merchant_id
                )
            )
        );


    if (!$branch_id) {
        return null;
    }


    $result =
        storefleet_set_primary_branch(
            $merchant_id,
            $branch_id
        );


    if (is_wp_error($result)) {
        return $result;
    }


    return storefleet_get_branch(
        $branch_id
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