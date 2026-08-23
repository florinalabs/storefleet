<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Merchant Registration REST API
|--------------------------------------------------------------------------
*/

add_action(
    'rest_api_init',
    function () {

        register_rest_route(
            'storefleet/v1',
            '/merchants/register',
            [
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_api_register_merchant',

                'permission_callback' =>
                    'storefleet_api_internal_permission',
            ]
        );
    }
);


/*
|--------------------------------------------------------------------------
| Internal API Authentication
|--------------------------------------------------------------------------
*/

function storefleet_api_internal_permission(
    WP_REST_Request $request
) {
    if (
        !defined(
            'STOREFLEET_INTERNAL_API_KEY'
        )
        ||
        STOREFLEET_INTERNAL_API_KEY === ''
    ) {
        return false;
    }

    $provided =
        (string) $request->get_header(
            'x-storefleet-key'
        );

    if ($provided === '') {
        return false;
    }

    return hash_equals(
        STOREFLEET_INTERNAL_API_KEY,
        $provided
    );
}


/*
|--------------------------------------------------------------------------
| Register Merchant
|--------------------------------------------------------------------------
*/

function storefleet_api_register_merchant(
    WP_REST_Request $request
) {
    $first_name =
        sanitize_text_field(
            $request->get_param(
                'first_name'
            )
        );

    $last_name =
        sanitize_text_field(
            $request->get_param(
                'last_name'
            )
        );

    $email =
        sanitize_email(
            $request->get_param(
                'email'
            )
        );

    $phone =
        sanitize_text_field(
            $request->get_param(
                'phone'
            )
        );

    $password =
        (string)
        $request->get_param(
            'password'
        );


    $business_name =
        sanitize_text_field(
            $request->get_param(
                'business_name'
            )
        );

    $business_type =
        sanitize_key(
            $request->get_param(
                'business_type'
            )
        );

    $business_phone =
        sanitize_text_field(
            $request->get_param(
                'business_phone'
            )
        );

    $address_line_1 =
        sanitize_text_field(
            $request->get_param(
                'address_line_1'
            )
        );

    $city =
        sanitize_text_field(
            $request->get_param(
                'city'
            )
        );

    $state =
        sanitize_text_field(
            $request->get_param(
                'state'
            )
        );

    $postcode =
        sanitize_text_field(
            $request->get_param(
                'postcode'
            )
        );


    $has_business_permit =
        $request->get_param(
            'has_business_permit'
        ) === '1';


    $permit_number =
        sanitize_text_field(
            $request->get_param(
                'business_permit_number'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $first_name === ''
        ||
        $last_name === ''
        ||
        $business_name === ''
    ) {
        return new WP_REST_Response(
            [
                'message' =>
                    'Required merchant information is missing.',
            ],
            422
        );
    }


    if (
        $email === ''
        ||
        !is_email($email)
    ) {
        return new WP_REST_Response(
            [
                'message' =>
                    'Enter a valid email address.',
            ],
            422
        );
    }


    if (email_exists($email)) {
        return new WP_REST_Response(
            [
                'message' =>
                    'An account already exists with this email address.',
            ],
            409
        );
    }


    if (strlen($password) < 10) {
        return new WP_REST_Response(
            [
                'message' =>
                    'Password must contain at least 10 characters.',
            ],
            422
        );
    }


    $allowed_business_types = [
        'sole_proprietor',
        'partnership',
        'corporation',
        'informal_seller',
        'other',
    ];


    if (
        !in_array(
            $business_type,
            $allowed_business_types,
            true
        )
    ) {
        return new WP_REST_Response(
            [
                'message' =>
                    'Select a valid business type.',
            ],
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Permit Validation
    |--------------------------------------------------------------------------
    */

    $files =
        $request->get_file_params();


    $permit_file =
        isset(
            $files[
                'business_permit_file'
            ]
        )
            ? $files[
                'business_permit_file'
            ]
            : null;


    if ($has_business_permit) {

        if ($permit_number === '') {
            return new WP_REST_Response(
                [
                    'message' =>
                        'Business permit number is required.',
                ],
                422
            );
        }

        if (!$permit_file) {
            return new WP_REST_Response(
                [
                    'message' =>
                        'Please upload your business permit.',
                ],
                422
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Username
    |--------------------------------------------------------------------------
    */

    $username_base =
        sanitize_user(
            sanitize_title(
                $business_name
            ),
            true
        );


    if ($username_base === '') {

        $email_parts =
            explode(
                '@',
                $email
            );

        $username_base =
            sanitize_user(
                $email_parts[0],
                true
            );
    }


    $username =
        $username_base;

    $counter = 2;


    while (
        username_exists(
            $username
        )
    ) {

        $username =
            $username_base .
            $counter;

        $counter++;
    }


    /*
    |--------------------------------------------------------------------------
    | Create Dokan Seller
    |--------------------------------------------------------------------------
    */

    $user_id =
        wp_insert_user(
            [
                'user_login' =>
                    $username,

                'user_pass' =>
                    $password,

                'user_email' =>
                    $email,

                'first_name' =>
                    $first_name,

                'last_name' =>
                    $last_name,

                'display_name' =>
                    $business_name,

                'role' =>
                    'seller',
            ]
        );


    if (is_wp_error($user_id)) {

        return new WP_REST_Response(
            [
                'message' =>
                    $user_id
                        ->get_error_message(),
            ],
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Account Status
    |--------------------------------------------------------------------------
    |
    | Registration does NOT automatically authorize selling.
    |
    */

    update_user_meta(
        $user_id,
        'dokan_enable_selling',
        'no'
    );


    update_user_meta(
        $user_id,
        'storefleet_merchant_status',
        'pending_approval'
    );


    /*
    |--------------------------------------------------------------------------
    | Verification Status
    |--------------------------------------------------------------------------
    */

    $verification_status =
        $has_business_permit
            ? 'pending_review'
            : 'unverified';


    update_user_meta(
        $user_id,
        'storefleet_verification_status',
        $verification_status
    );


    update_user_meta(
        $user_id,
        'storefleet_business_type',
        $business_type
    );


    update_user_meta(
        $user_id,
        'storefleet_owner_phone',
        $phone
    );


    update_user_meta(
        $user_id,
        'storefleet_business_permit_number',
        $permit_number
    );


    /*
    |--------------------------------------------------------------------------
    | Dokan Profile
    |--------------------------------------------------------------------------
    */

    $profile = [
        'store_name' =>
            $business_name,

        'phone' =>
            $business_phone !== ''
                ? $business_phone
                : $phone,

        'address' => [
            'street_1' =>
                $address_line_1,

            'street_2' =>
                '',

            'city' =>
                $city,

            'zip' =>
                $postcode,

            'country' =>
                'PH',

            'state' =>
                $state,
        ],
    ];


    update_user_meta(
        $user_id,
        'dokan_profile_settings',
        $profile
    );


    /*
    |--------------------------------------------------------------------------
    | Store Business Permit Privately
    |--------------------------------------------------------------------------
    */

    if (
        $has_business_permit
        &&
        $permit_file
    ) {

        $permit_result =
            storefleet_store_private_permit(
                $user_id,
                $permit_file
            );


        if (
            is_wp_error(
                $permit_result
            )
        ) {

            require_once ABSPATH .
                'wp-admin/includes/user.php';

            wp_delete_user(
                $user_id
            );


            return new WP_REST_Response(
                [
                    'message' =>
                        $permit_result
                            ->get_error_message(),
                ],
                422
            );
        }


        update_user_meta(
            $user_id,
            'storefleet_business_permit_file',
            $permit_result
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Registration Metadata
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        'storefleet_registration_source',
        'nextjs'
    );


    update_user_meta(
        $user_id,
        'storefleet_registered_at',
        current_time('mysql')
    );


    return new WP_REST_Response(
        [
            'success' =>
                true,

            'message' =>
                'Merchant registration received.',

            'merchant_id' =>
                $user_id,

            'merchant_status' =>
                'pending_approval',

            'verification_status' =>
                $verification_status,
        ],
        201
    );
}


/*
|--------------------------------------------------------------------------
| Private Business Permit Storage
|--------------------------------------------------------------------------
*/

function storefleet_store_private_permit(
    $user_id,
    array $file
) {
    $user_id =
        absint($user_id);


    if (
        empty($file['tmp_name'])
        ||
        !is_uploaded_file(
            $file['tmp_name']
        )
    ) {
        return new WP_Error(
            'invalid_permit',
            'Invalid business permit upload.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 10 MB Limit
    |--------------------------------------------------------------------------
    */

    $max_size =
        10 * 1024 * 1024;


    if (
        !empty($file['size'])
        &&
        (int) $file['size']
            > $max_size
    ) {
        return new WP_Error(
            'permit_too_large',
            'Business permit must be 10 MB or smaller.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIME Type
    |--------------------------------------------------------------------------
    */

    $allowed_mimes = [
        'pdf' =>
            'application/pdf',

        'jpg|jpeg' =>
            'image/jpeg',

        'png' =>
            'image/png',
    ];


    $check =
        wp_check_filetype_and_ext(
            $file['tmp_name'],
            $file['name'],
            $allowed_mimes
        );


    if (
        empty($check['ext'])
        ||
        empty($check['type'])
    ) {
        return new WP_Error(
            'invalid_permit_type',
            'Business permit must be a PDF, JPG or PNG file.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Private Directory
    |--------------------------------------------------------------------------
    */

    $uploads =
        wp_upload_dir();


    if (!empty($uploads['error'])) {
        return new WP_Error(
            'upload_directory_error',
            $uploads['error']
        );
    }


    $directory =
        trailingslashit(
            $uploads['basedir']
        ) .
        'storefleet-private-permits';


    if (
        !wp_mkdir_p(
            $directory
        )
    ) {
        return new WP_Error(
            'permit_directory_error',
            'Could not create private permit directory.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Apache Protection
    |--------------------------------------------------------------------------
    */

    $htaccess =
        trailingslashit(
            $directory
        ) .
        '.htaccess';


    if (!file_exists($htaccess)) {

        file_put_contents(
            $htaccess,
            "Require all denied\n"
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Randomized Filename
    |--------------------------------------------------------------------------
    */

    $filename =
        sprintf(
            'merchant-%d-%s.%s',
            $user_id,
            wp_generate_password(
                24,
                false,
                false
            ),
            $check['ext']
        );


    $destination =
        trailingslashit(
            $directory
        ) .
        $filename;


    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        return new WP_Error(
            'permit_move_error',
            'Business permit could not be stored.'
        );
    }


    return [
        'filename' =>
            $filename,

        'path' =>
            $destination,

        'mime_type' =>
            $check['type'],

        'uploaded_at' =>
            current_time('mysql'),
    ];
}