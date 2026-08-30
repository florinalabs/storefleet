<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Public Store REST API
|--------------------------------------------------------------------------
|
| Customer-facing StoreFleet endpoint for the Next.js storefront.
|
| GET /wp-json/storefleet/v1/stores/{merchant_id}
| GET /wp-json/storefleet/v1/stores/by-slug/{store_slug}
|
| Response:
|
| store
|   └── branches
|       └── opening hours
|
| IMPORTANT:
| This endpoint returns public storefront data, but it follows StoreFleet's
| existing API architecture:
|
| Browser -> Next.js -> WordPress StoreFleet API
|
| Next.js attaches X-StoreFleet-Key server-side. The key is never exposed
| to browser JavaScript.
|
*/

add_action(
    'rest_api_init',
    function () {

        register_rest_route(
            'storefleet/v1',
            '/stores',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_stores',

                'permission_callback' =>
                    'storefleet_api_public_store_permission',
            ]
        );


        register_rest_route(
            'storefleet/v1',
            '/stores/(?P<merchant_id>\d+)',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_store',

                'permission_callback' =>
                    'storefleet_api_public_store_permission',

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
                ],
            ]
        );


        register_rest_route(
            'storefleet/v1',
            '/stores/by-slug/(?P<store_slug>[a-zA-Z0-9-]+)',
            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_api_get_public_store_by_slug',

                'permission_callback' =>
                    'storefleet_api_public_store_permission',

                'args' => [
                    'store_slug' => [
                        'required' =>
                            true,

                        'sanitize_callback' =>
                            'sanitize_title',
                    ],
                ],
            ]
        );
    }
);


/*
|--------------------------------------------------------------------------
| Store API Authentication
|--------------------------------------------------------------------------
|
| Keep merchant registration authentication untouched.
|
| This Store API accepts the same X-StoreFleet-Key and resolves the expected
| key from the PHP constant when available, otherwise directly from the
| container environment.
|
*/

function storefleet_api_public_store_permission(
    WP_REST_Request $request
) {
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
        ) ||
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
| Get Public Stores
|--------------------------------------------------------------------------
|
| Returns active StoreFleet merchants for the Next.js /stores directory.
| Only data that is actually available from StoreFleet/Dokan is exposed.
|
*/

function storefleet_api_get_public_stores(
    WP_REST_Request $request
) {
    $seller_ids =
        get_users(
            [
                'role' =>
                    'seller',

                'fields' =>
                    'ids',
            ]
        );


    $stores =
        array();


    foreach (
        $seller_ids as
        $merchant_id
    ) {
        $merchant_id =
            absint(
                $merchant_id
            );


        if (!$merchant_id) {
            continue;
        }


        $merchant =
            get_userdata(
                $merchant_id
            );


        if (
            !$merchant ||
            !storefleet_api_public_store_is_active(
                $merchant_id,
                $merchant
            )
        ) {
            continue;
        }


        $response =
            storefleet_api_build_public_store_response(
                $merchant_id
            );


        if (
            !($response instanceof WP_REST_Response) ||
            $response->get_status() !== 200
        ) {
            continue;
        }


        $data =
            $response->get_data();


        if (
            empty(
                $data['store']
            ) ||
            !is_array(
                $data['store']
            )
        ) {
            continue;
        }


        $store =
            $data['store'];


        $branches =
            isset(
                $data['branches']
            ) &&
            is_array(
                $data['branches']
            )
                ? $data['branches']
                : array();


        $primary_branch =
            null;


        foreach (
            $branches as
            $branch
        ) {
            if (
                !empty(
                    $branch['is_primary']
                )
            ) {
                $primary_branch =
                    $branch;

                break;
            }
        }


        if (
            $primary_branch === null &&
            !empty(
                $branches
            )
        ) {
            $primary_branch =
                $branches[0];
        }


        $open_branch_count =
            0;


        foreach (
            $branches as
            $branch
        ) {
            if (
                !empty(
                    $branch['is_open_now']
                )
            ) {
                $open_branch_count++;
            }
        }


        $verification_status =
            sanitize_key(
                get_user_meta(
                    $merchant_id,
                    'storefleet_verification_status',
                    true
                )
            );


        $stores[] =
            [
                'merchant_id' =>
                    $merchant_id,

                'name' =>
                    $store['name']
                        ?? '',

                'slug' =>
                    $store['slug']
                        ?? '',

                'description' =>
                    $store['description']
                        ?? '',

                'logo_url' =>
                    $store['logo_url']
                        ?? '',

                'banner_url' =>
                    $store['banner_url']
                        ?? '',

                'phone' =>
                    $store['phone']
                        ?? '',

                'address' =>
                    $store['address']
                        ?? array(),

                'verified' =>
                    in_array(
                        $verification_status,
                        [
                            'verified',
                            'approved',
                        ],
                        true
                    ),

                'branch_count' =>
                    count(
                        $branches
                    ),

                'open_branch_count' =>
                    $open_branch_count,

                'primary_branch_id' =>
                    $store['primary_branch_id']
                        ?? null,

                'primary_branch' =>
                    $primary_branch,
            ];
    }


    usort(
        $stores,
        function (
            $a,
            $b
        ) {
            return strcasecmp(
                $a['name']
                    ?? '',
                $b['name']
                    ?? ''
            );
        }
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

                'count' =>
                    count(
                        $stores
                    ),

                'stores' =>
                    $stores,
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
| Get Public Store By Merchant ID
|--------------------------------------------------------------------------
*/

function storefleet_api_get_public_store(
    WP_REST_Request $request
) {
    $merchant_id =
        absint(
            $request->get_param(
                'merchant_id'
            )
        );

    return storefleet_api_build_public_store_response(
        $merchant_id
    );
}


/*
|--------------------------------------------------------------------------
| Get Public Store By Slug
|--------------------------------------------------------------------------
*/

function storefleet_api_get_public_store_by_slug(
    WP_REST_Request $request
) {
    $store_slug =
        sanitize_title(
            $request->get_param(
                'store_slug'
            )
        );

    if ($store_slug === '') {
        return new WP_REST_Response(
            [
                'success' =>
                    false,

                'message' =>
                    'Invalid store slug.',
            ],
            422
        );
    }


    $merchant =
        storefleet_api_find_merchant_by_store_slug(
            $store_slug
        );


    if (!$merchant) {
        return new WP_REST_Response(
            [
                'success' =>
                    false,

                'message' =>
                    'Store not found.',
            ],
            404
        );
    }


    return storefleet_api_build_public_store_response(
        $merchant->ID
    );
}


/*
|--------------------------------------------------------------------------
| Build Public Store Response
|--------------------------------------------------------------------------
*/

function storefleet_api_build_public_store_response(
    $merchant_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    if (!$merchant_id) {
        return new WP_REST_Response(
            [
                'success' =>
                    false,

                'message' =>
                    'Invalid merchant ID.',
            ],
            422
        );
    }


    $merchant =
        get_userdata(
            $merchant_id
        );


    if (!$merchant) {
        return new WP_REST_Response(
            [
                'success' =>
                    false,

                'message' =>
                    'Store not found.',
            ],
            404
        );
    }


    if (
        !storefleet_api_public_store_is_active(
            $merchant_id,
            $merchant
        )
    ) {
        return new WP_REST_Response(
            [
                'success' =>
                    false,

                'message' =>
                    'Store not found.',
            ],
            404
        );
    }


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


    $store_name =
        !empty(
            $profile['store_name']
        )
            ? sanitize_text_field(
                $profile['store_name']
            )
            : sanitize_text_field(
                $merchant->display_name
            );


    $store_phone =
        !empty(
            $profile['phone']
        )
            ? sanitize_text_field(
                $profile['phone']
            )
            : '';


    $store_address =
        isset(
            $profile['address']
        ) &&
        is_array(
            $profile['address']
        )
            ? $profile['address']
            : array();


    $logo_url =
        storefleet_api_public_attachment_url(
            $profile['gravatar']
                ?? 0
        );


    if ($logo_url === '') {
        $logo_url =
            get_avatar_url(
                $merchant_id,
                [
                    'size' =>
                        512,
                ]
            );
    }


    $banner_url =
        storefleet_api_public_attachment_url(
            $profile['banner']
                ?? 0
        );


    $description =
        !empty(
            $profile['store_description']
        )
            ? wp_kses_post(
                $profile['store_description']
            )
            : '';


    $branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );


    $public_branches =
        array();


    $primary_branch_id =
        null;


    foreach (
        $branches as
        $branch
    ) {
        $public_branch =
            storefleet_api_build_public_branch(
                $branch
            );


        if (!$public_branch) {
            continue;
        }


        if (
            $public_branch[
                'is_primary'
            ] &&
            $primary_branch_id === null
        ) {
            $primary_branch_id =
                $public_branch[
                    'id'
                ];
        }


        $public_branches[] =
            $public_branch;
    }


    if (
        $primary_branch_id === null &&
        !empty(
            $public_branches
        )
    ) {
        $primary_branch_id =
            $public_branches[0][
                'id'
            ];
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

                'store' => [
                    'merchant_id' =>
                        $merchant_id,

                    'name' =>
                        $store_name,

                    'slug' =>
                        storefleet_api_get_store_slug(
                            $merchant_id,
                            $profile,
                            $merchant
                        ),

                    'description' =>
                        $description,

                    'logo_url' =>
                        $logo_url,

                    'banner_url' =>
                        $banner_url,

                    'phone' =>
                        $store_phone,

                    'address' =>
                        storefleet_api_normalize_store_address(
                            $store_address
                        ),

                    'primary_branch_id' =>
                        $primary_branch_id,

                    'branch_count' =>
                        count(
                            $public_branches
                        ),
                ],

                'branches' =>
                    $public_branches,
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
| Build One Public Branch
|--------------------------------------------------------------------------
*/

function storefleet_api_build_public_branch(
    $branch
) {
    if (
        !$branch ||
        empty(
            $branch->id
        )
    ) {
        return null;
    }


    $branch_id =
        absint(
            $branch->id
        );


    $hours =
        storefleet_get_branch_hours(
            $branch_id
        );


    $schedule =
        array();


    for (
        $day_number = 1;
        $day_number <= 7;
        $day_number++
    ) {
        $row =
            isset(
                $hours[
                    $day_number
                ]
            )
                ? $hours[
                    $day_number
                ]
                : null;


        $is_open =
            $row &&
            isset(
                $row->is_open
            ) &&
            (int)
            $row->is_open === 1;


        $schedule[] =
            [
                'day' =>
                    $day_number,

                'day_name' =>
                    storefleet_api_public_day_name(
                        $day_number
                    ),

                'is_open' =>
                    $is_open,

                'opens_at' =>
                    $is_open &&
                    !empty(
                        $row->opens_at
                    )
                        ? substr(
                            $row->opens_at,
                            0,
                            5
                        )
                        : null,

                'closes_at' =>
                    $is_open &&
                    !empty(
                        $row->closes_at
                    )
                        ? substr(
                            $row->closes_at,
                            0,
                            5
                        )
                        : null,
            ];
    }


    $today_number =
        (int)
        current_datetime()
            ->format(
                'N'
            );


    $today =
        null;


    foreach (
        $schedule as
        $day
    ) {
        if (
            (int)
            $day['day'] ===
            $today_number
        ) {
            $today =
                $day;

            break;
        }
    }


    $is_open_now =
        storefleet_branch_is_open_now(
            $branch_id
        );


    return [
        'id' =>
            $branch_id,

        'name' =>
            sanitize_text_field(
                $branch->name
                    ?? ''
            ),

        'slug' =>
            sanitize_title(
                $branch->slug
                    ?? ''
            ),

        'is_primary' =>
            (int)
            (
                $branch->is_primary
                    ?? 0
            ) === 1,

        'is_open_now' =>
            (bool)
            $is_open_now,

        'status' =>
            $is_open_now
                ? 'open'
                : 'closed',

        'address' => [
            'line_1' =>
                sanitize_text_field(
                    $branch->address_line_1
                        ?? ''
                ),

            'line_2' =>
                sanitize_text_field(
                    $branch->address_line_2
                        ?? ''
                ),

            'city' =>
                sanitize_text_field(
                    $branch->city
                        ?? ''
                ),

            'state' =>
                sanitize_text_field(
                    $branch->state
                        ?? ''
                ),

            'postcode' =>
                sanitize_text_field(
                    $branch->postcode
                        ?? ''
                ),

            'country' =>
                sanitize_text_field(
                    $branch->country
                        ?? 'PH'
                ),

            'formatted' =>
                function_exists(
                    'storefleet_get_branch_address'
                )
                    ? storefleet_get_branch_address(
                        $branch
                    )
                    : '',
        ],

        'location' => [
            'latitude' =>
                isset(
                    $branch->latitude
                ) &&
                $branch->latitude !== null
                    ? (float)
                    $branch->latitude
                    : null,

            'longitude' =>
                isset(
                    $branch->longitude
                ) &&
                $branch->longitude !== null
                    ? (float)
                    $branch->longitude
                    : null,
        ],

        'today' =>
            $today,

        'hours' =>
            $schedule,
    ];
}


/*
|--------------------------------------------------------------------------
| Store SEO Slug
|--------------------------------------------------------------------------
|
| Store URLs must represent the customer-facing store name, not the
| WordPress account username.
|
| Example:
|
| Brew & Bean Coffee House
| -> brew-bean-coffee-house
|
| A dedicated storefleet_store_slug user meta value takes priority when
| available. This allows the slug to remain stable even if the display name
| changes later.
|
*/

function storefleet_api_get_store_slug(
    $merchant_id,
    array $profile = array(),
    $merchant = null
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    $saved_slug =
        sanitize_title(
            get_user_meta(
                $merchant_id,
                'storefleet_store_slug',
                true
            )
        );


    if ($saved_slug !== '') {
        return $saved_slug;
    }


    $store_name =
        !empty(
            $profile['store_name']
        )
            ? sanitize_text_field(
                $profile['store_name']
            )
            : '';


    if (
        $store_name === '' &&
        $merchant
    ) {
        $store_name =
            sanitize_text_field(
                $merchant->display_name
                    ?? ''
            );
    }


    $slug =
        sanitize_title(
            $store_name
        );


    if ($slug !== '') {
        return $slug;
    }


    if ($merchant) {
        return sanitize_title(
            $merchant->user_nicename
                ?? ''
        );
    }


    return '';
}


/*
|--------------------------------------------------------------------------
| Find Merchant By Store Slug
|--------------------------------------------------------------------------
|
| First look for an explicitly saved StoreFleet slug. Existing merchants
| that predate storefleet_store_slug are supported by deriving the slug from
| their Dokan store name.
|
*/

function storefleet_api_find_merchant_by_store_slug(
    $store_slug
) {
    $store_slug =
        sanitize_title(
            $store_slug
        );


    if ($store_slug === '') {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Dedicated StoreFleet Slug
    |--------------------------------------------------------------------------
    */

    $users =
        get_users(
            [
                'number' =>
                    1,

                'role' =>
                    'seller',

                'meta_key' =>
                    'storefleet_store_slug',

                'meta_value' =>
                    $store_slug,

                'fields' =>
                    'all',
            ]
        );


    if (!empty($users)) {
        return $users[0];
    }


    /*
    |--------------------------------------------------------------------------
    | Existing / Legacy Merchants
    |--------------------------------------------------------------------------
    |
    | Existing stores may not have storefleet_store_slug yet. Compare the
    | requested slug with their current Dokan store name.
    |
    */

    $seller_ids =
        get_users(
            [
                'role' =>
                    'seller',

                'fields' =>
                    'ids',
            ]
        );


    foreach (
        $seller_ids as
        $merchant_id
    ) {
        $merchant_id =
            absint(
                $merchant_id
            );


        if (!$merchant_id) {
            continue;
        }


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


        $merchant =
            get_userdata(
                $merchant_id
            );


        if (!$merchant) {
            continue;
        }


        $candidate_slug =
            storefleet_api_get_store_slug(
                $merchant_id,
                $profile,
                $merchant
            );


        if (
            $candidate_slug ===
            $store_slug
        ) {
            return $merchant;
        }
    }


    return false;
}


/*
|--------------------------------------------------------------------------
| Public Store Eligibility
|--------------------------------------------------------------------------
*/

function storefleet_api_public_store_is_active(
    $merchant_id,
    $merchant
) {
    $merchant_id =
        absint(
            $merchant_id
        );


    if (
        !$merchant_id ||
        !$merchant
    ) {
        return false;
    }


    $roles =
        is_array(
            $merchant->roles
        )
            ? $merchant->roles
            : array();


    if (
        !in_array(
            'seller',
            $roles,
            true
        )
    ) {
        return false;
    }


    $status =
        sanitize_key(
            get_user_meta(
                $merchant_id,
                'storefleet_merchant_status',
                true
            )
        );


    if ($status !== '') {
        return in_array(
            $status,
            [
                'active',
                'approved',
            ],
            true
        );
    }


    $selling_enabled =
        (string)
        get_user_meta(
            $merchant_id,
            'dokan_enable_selling',
            true
        );


    if (
        $selling_enabled !== '' &&
        $selling_enabled !== 'yes'
    ) {
        return false;
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| Normalize Dokan Store Address
|--------------------------------------------------------------------------
*/

function storefleet_api_normalize_store_address(
    array $address
) {
    $line_1 =
        sanitize_text_field(
            $address['street_1']
                ?? ''
        );


    $line_2 =
        sanitize_text_field(
            $address['street_2']
                ?? ''
        );


    $city =
        sanitize_text_field(
            $address['city']
                ?? ''
        );


    $state =
        sanitize_text_field(
            $address['state']
                ?? ''
        );


    $postcode =
        sanitize_text_field(
            $address['zip']
                ?? ''
        );


    $country =
        sanitize_text_field(
            $address['country']
                ?? 'PH'
        );


    $formatted =
        implode(
            ', ',
            array_filter(
                [
                    $line_1,
                    $line_2,
                    $city,
                    $state,
                    $postcode,
                ],
                function ($value) {
                    return $value !== '';
                }
            )
        );


    return [
        'line_1' =>
            $line_1,

        'line_2' =>
            $line_2,

        'city' =>
            $city,

        'state' =>
            $state,

        'postcode' =>
            $postcode,

        'country' =>
            $country,

        'formatted' =>
            $formatted,
    ];
}


/*
|--------------------------------------------------------------------------
| Attachment URL
|--------------------------------------------------------------------------
*/

function storefleet_api_public_attachment_url(
    $attachment_id
) {
    $attachment_id =
        absint(
            $attachment_id
        );


    if (!$attachment_id) {
        return '';
    }


    $url =
        wp_get_attachment_image_url(
            $attachment_id,
            'full'
        );


    if (!$url) {
        $url =
            wp_get_attachment_url(
                $attachment_id
            );
    }


    return $url
        ? esc_url_raw(
            $url
        )
        : '';
}


/*
|--------------------------------------------------------------------------
| Day Name
|--------------------------------------------------------------------------
*/

function storefleet_api_public_day_name(
    $day_number
) {
    $days =
        [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];


    return $days[
        absint(
            $day_number
        )
    ] ?? '';
}
