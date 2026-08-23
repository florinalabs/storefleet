<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Customer API Routes
|--------------------------------------------------------------------------
*/

add_action(
    'rest_api_init',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Register
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/register',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_register',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/login',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_login',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Current Customer
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/me',
            array(
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'storefleet_customer_me',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        */

        register_rest_route(
            'storefleet/v1',
            '/customers/logout',
            array(
                'methods' =>
                    WP_REST_Server::CREATABLE,

                'callback' =>
                    'storefleet_customer_logout',

                'permission_callback' =>
                    'storefleet_customer_api_permission',
            )
        );
    }
);


/*
|--------------------------------------------------------------------------
| Internal API Authentication
|--------------------------------------------------------------------------
|
| Browsers should NOT call these WordPress routes directly.
|
| The expected flow is:
|
| Browser
|   -> Next.js API
|   -> WordPress StoreFleet API
|
| Next.js attaches X-StoreFleet-Key server-side.
|
*/

function storefleet_customer_api_permission(
    WP_REST_Request $request
) {
    if (
        !defined(
            'STOREFLEET_INTERNAL_API_KEY'
        )
    ) {
        return new WP_Error(
            'storefleet_api_not_configured',
            'StoreFleet API authentication is not configured.',
            array(
                'status' => 500,
            )
        );
    }


    $expected_key =
        (string)
        STOREFLEET_INTERNAL_API_KEY;


    $provided_key =
        (string)
        $request->get_header(
            'x-storefleet-key'
        );


    if (
        $expected_key === '' ||
        $provided_key === ''
    ) {
        return new WP_Error(
            'storefleet_unauthorized',
            'Unauthorized request.',
            array(
                'status' => 401,
            )
        );
    }


    if (
        !hash_equals(
            $expected_key,
            $provided_key
        )
    ) {
        return new WP_Error(
            'storefleet_unauthorized',
            'Unauthorized request.',
            array(
                'status' => 401,
            )
        );
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| Register Customer
|--------------------------------------------------------------------------
*/

function storefleet_customer_register(
    WP_REST_Request $request
) {

    /*
    |--------------------------------------------------------------------------
    | Ensure WooCommerce Customer Role Exists
    |--------------------------------------------------------------------------
    */

    if (!get_role('customer')) {
        return new WP_Error(
            'storefleet_customer_role_missing',
            'Customer accounts are currently unavailable.',
            array(
                'status' => 503,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Input
    |--------------------------------------------------------------------------
    */

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


    $email =
        sanitize_email(
            wp_unslash(
                (string)
                $request->get_param(
                    'email'
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


    $password =
        (string)
        $request->get_param(
            'password'
        );


    /*
    |--------------------------------------------------------------------------
    | Required Fields
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {
        return new WP_Error(
            'storefleet_first_name_required',
            'First name is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($last_name === '') {
        return new WP_Error(
            'storefleet_last_name_required',
            'Last name is required.',
            array(
                'status' => 422,
            )
        );
    }


    if ($email === '') {
        return new WP_Error(
            'storefleet_email_required',
            'Email address is required.',
            array(
                'status' => 422,
            )
        );
    }


    if (!is_email($email)) {
        return new WP_Error(
            'storefleet_invalid_email',
            'Please enter a valid email address.',
            array(
                'status' => 422,
            )
        );
    }


    if ($phone === '') {
        return new WP_Error(
            'storefleet_phone_required',
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
            'storefleet_invalid_phone',
            'Please enter a valid mobile number.',
            array(
                'status' => 422,
            )
        );
    }


    if (
        strlen($password) < 10
    ) {
        return new WP_Error(
            'storefleet_password_too_short',
            'Password must contain at least 10 characters.',
            array(
                'status' => 422,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Existing Account
    |--------------------------------------------------------------------------
    */

    if (email_exists($email)) {
        return new WP_Error(
            'storefleet_email_exists',
            'An account already exists with this email address.',
            array(
                'status' => 409,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Username
    |--------------------------------------------------------------------------
    */

    $username =
        storefleet_customer_generate_username(
            $email,
            $first_name,
            $last_name
        );


    /*
    |--------------------------------------------------------------------------
    | Create WordPress Customer
    |--------------------------------------------------------------------------
    */

    $user_id =
        wp_insert_user(
            array(
                'user_login' =>
                    $username,

                'user_email' =>
                    $email,

                'user_pass' =>
                    $password,

                'first_name' =>
                    $first_name,

                'last_name' =>
                    $last_name,

                'display_name' =>
                    trim(
                        $first_name .
                        ' ' .
                        $last_name
                    ),

                'role' =>
                    'customer',
            )
        );


    if (is_wp_error($user_id)) {
        return new WP_Error(
            'storefleet_customer_create_failed',
            'Unable to create your StoreFleet account.',
            array(
                'status' => 500,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WooCommerce Customer Metadata
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        'billing_first_name',
        $first_name
    );


    update_user_meta(
        $user_id,
        'billing_last_name',
        $last_name
    );


    update_user_meta(
        $user_id,
        'billing_email',
        $email
    );


    update_user_meta(
        $user_id,
        'billing_phone',
        $phone
    );


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Customer Metadata
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        'storefleet_customer_phone',
        $phone
    );


    update_user_meta(
        $user_id,
        'storefleet_customer_status',
        'active'
    );


    update_user_meta(
        $user_id,
        'storefleet_customer_registered_at',
        current_time(
            'mysql',
            true
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Hide WordPress Admin Bar
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        'show_admin_bar_front',
        'false'
    );


    /*
    |--------------------------------------------------------------------------
    | Load Customer
    |--------------------------------------------------------------------------
    */

    $user =
        get_user_by(
            'id',
            $user_id
        );


    if (!$user) {
        return new WP_Error(
            'storefleet_customer_load_failed',
            'The account was created but could not be loaded.',
            array(
                'status' => 500,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Customer Session
    |--------------------------------------------------------------------------
    |
    | Registration automatically signs the customer in.
    |
    */

    $session =
        storefleet_customer_create_session(
            $user_id,
            $request,
            false
        );


    if (is_wp_error($session)) {
        return $session;
    }


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Your StoreFleet account has been created.',

            'customer' =>
                storefleet_customer_public_data(
                    $user
                ),

            'session' =>
                $session,
        ),
        201
    );
}


/*
|--------------------------------------------------------------------------
| Login Customer
|--------------------------------------------------------------------------
*/

function storefleet_customer_login(
    WP_REST_Request $request
) {

    /*
    |--------------------------------------------------------------------------
    | Input
    |--------------------------------------------------------------------------
    */

    $login =
        sanitize_text_field(
            wp_unslash(
                (string)
                $request->get_param(
                    'login'
                )
            )
        );


    $password =
        (string)
        $request->get_param(
            'password'
        );


    $remember =
        storefleet_customer_parse_boolean(
            $request->get_param(
                'remember'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $login === '' ||
        $password === ''
    ) {
        return new WP_Error(
            'storefleet_login_required',
            'Email or username and password are required.',
            array(
                'status' => 422,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Authenticate
    |--------------------------------------------------------------------------
    */

    $user =
        wp_authenticate(
            $login,
            $password
        );


    /*
    |--------------------------------------------------------------------------
    | Generic Authentication Error
    |--------------------------------------------------------------------------
    |
    | Do not reveal whether an email address or username exists.
    |
    */

    if (is_wp_error($user)) {
        return new WP_Error(
            'storefleet_invalid_login',
            'Invalid email, username or password.',
            array(
                'status' => 401,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customers Only
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'customer',
            (array)
            $user->roles,
            true
        )
    ) {
        return new WP_Error(
            'storefleet_customer_login_only',
            'This login is for StoreFleet customers.',
            array(
                'status' => 403,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Status
    |--------------------------------------------------------------------------
    */

    $status =
        get_user_meta(
            $user->ID,
            'storefleet_customer_status',
            true
        );


    if (
        $status !== '' &&
        $status !== 'active'
    ) {
        return new WP_Error(
            'storefleet_customer_inactive',
            'This customer account is currently unavailable.',
            array(
                'status' => 403,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Session
    |--------------------------------------------------------------------------
    */

    $session =
        storefleet_customer_create_session(
            $user->ID,
            $request,
            $remember
        );


    if (is_wp_error($session)) {
        return $session;
    }


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Signed in successfully.',

            'customer' =>
                storefleet_customer_public_data(
                    $user
                ),

            'session' =>
                $session,
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Current Customer
|--------------------------------------------------------------------------
*/

function storefleet_customer_me(
    WP_REST_Request $request
) {

    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    $user =
        get_user_by(
            'id',
            $session['user_id']
        );


    if (!$user) {
        return new WP_Error(
            'storefleet_customer_not_found',
            'Customer account could not be found.',
            array(
                'status' => 404,
            )
        );
    }


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'customer' =>
                storefleet_customer_public_data(
                    $user
                ),
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Logout Customer
|--------------------------------------------------------------------------
*/

function storefleet_customer_logout(
    WP_REST_Request $request
) {

    $session =
        storefleet_customer_validate_session(
            $request
        );


    if (is_wp_error($session)) {
        return $session;
    }


    storefleet_customer_destroy_session(
        $session['user_id'],
        $session['token_hash']
    );


    return new WP_REST_Response(
        array(
            'success' =>
                true,

            'message' =>
                'Signed out successfully.',
        ),
        200
    );
}


/*
|--------------------------------------------------------------------------
| Create Customer Session
|--------------------------------------------------------------------------
*/

function storefleet_customer_create_session(
    $user_id,
    WP_REST_Request $request,
    $remember = false
) {

    $user_id =
        absint($user_id);


    if ($user_id <= 0) {
        return new WP_Error(
            'storefleet_invalid_customer',
            'Unable to create customer session.',
            array(
                'status' => 500,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Normal:
    | 7 days
    |
    | Remember Me:
    | 30 days
    |
    */

    $lifetime =
        $remember
            ? 30 * DAY_IN_SECONDS
            : 7 * DAY_IN_SECONDS;


    $created_at =
        time();


    $expires_at =
        $created_at +
        $lifetime;


    /*
    |--------------------------------------------------------------------------
    | Generate Random Token
    |--------------------------------------------------------------------------
    |
    | The raw token is returned once.
    |
    | WordPress only stores its SHA-256 hash.
    |
    */

    try {
        $token =
            bin2hex(
                random_bytes(32)
            );
    } catch (Exception $exception) {
        return new WP_Error(
            'storefleet_session_create_failed',
            'Unable to create customer session.',
            array(
                'status' => 500,
            )
        );
    }


    $token_hash =
        hash(
            'sha256',
            $token
        );


    /*
    |--------------------------------------------------------------------------
    | Existing Sessions
    |--------------------------------------------------------------------------
    */

    $sessions =
        get_user_meta(
            $user_id,
            'storefleet_customer_sessions',
            true
        );


    if (!is_array($sessions)) {
        $sessions = array();
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Expired Sessions
    |--------------------------------------------------------------------------
    */

    foreach (
        $sessions as
        $existing_hash =>
        $existing_session
    ) {

        $existing_expiry =
            isset(
                $existing_session[
                    'expires_at'
                ]
            )
                ? absint(
                    $existing_session[
                        'expires_at'
                    ]
                )
                : 0;


        if (
            $existing_expiry <=
            time()
        ) {

            delete_transient(
                storefleet_customer_session_transient_key(
                    $existing_hash
                )
            );


            unset(
                $sessions[
                    $existing_hash
                ]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Limit Active Sessions
    |--------------------------------------------------------------------------
    |
    | A customer may have up to 8 active devices/sessions.
    |
    */

    if (
        count($sessions) >= 8
    ) {

        uasort(
            $sessions,
            function (
                $a,
                $b
            ) {

                $a_created =
                    isset(
                        $a['created_at']
                    )
                        ? absint(
                            $a['created_at']
                        )
                        : 0;


                $b_created =
                    isset(
                        $b['created_at']
                    )
                        ? absint(
                            $b['created_at']
                        )
                        : 0;


                return
                    $a_created <=>
                    $b_created;
            }
        );


        while (
            count($sessions) >= 8
        ) {

            $oldest_hash =
                array_key_first(
                    $sessions
                );


            if (
                $oldest_hash ===
                null
            ) {
                break;
            }


            delete_transient(
                storefleet_customer_session_transient_key(
                    $oldest_hash
                )
            );


            unset(
                $sessions[
                    $oldest_hash
                ]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    */

    $user_agent =
        sanitize_text_field(
            (string)
            $request->get_header(
                'user-agent'
            )
        );


    if (
        strlen($user_agent) >
        255
    ) {
        $user_agent =
            substr(
                $user_agent,
                0,
                255
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Session Metadata
    |--------------------------------------------------------------------------
    */

    $sessions[
        $token_hash
    ] =
        array(
            'created_at' =>
                $created_at,

            'expires_at' =>
                $expires_at,

            'user_agent' =>
                $user_agent,
        );


    update_user_meta(
        $user_id,
        'storefleet_customer_sessions',
        $sessions
    );


    /*
    |--------------------------------------------------------------------------
    | Fast Token -> User Lookup
    |--------------------------------------------------------------------------
    */

    set_transient(
        storefleet_customer_session_transient_key(
            $token_hash
        ),
        $user_id,
        $lifetime
    );


    /*
    |--------------------------------------------------------------------------
    | Response Session
    |--------------------------------------------------------------------------
    */

    return array(
        'token' =>
            $token,

        'expires_at' =>
            gmdate(
                'c',
                $expires_at
            ),

        'expires_in' =>
            $lifetime,

        'remember' =>
            (bool)
            $remember,
    );
}


/*
|--------------------------------------------------------------------------
| Validate Customer Session
|--------------------------------------------------------------------------
*/

function storefleet_customer_validate_session(
    WP_REST_Request $request
) {

    /*
    |--------------------------------------------------------------------------
    | Extract Token
    |--------------------------------------------------------------------------
    */

    $token =
        storefleet_customer_get_session_token(
            $request
        );


    if ($token === '') {
        return new WP_Error(
            'storefleet_customer_session_required',
            'Customer session is required.',
            array(
                'status' => 401,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Basic Token Validation
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^[a-f0-9]{64}$/',
            $token
        )
    ) {
        return new WP_Error(
            'storefleet_invalid_customer_session',
            'Customer session is invalid or expired.',
            array(
                'status' => 401,
            )
        );
    }


    $token_hash =
        hash(
            'sha256',
            $token
        );


    /*
    |--------------------------------------------------------------------------
    | Find User
    |--------------------------------------------------------------------------
    */

    $user_id =
        get_transient(
            storefleet_customer_session_transient_key(
                $token_hash
            )
        );


    $user_id =
        absint(
            $user_id
        );


    if ($user_id <= 0) {
        return new WP_Error(
            'storefleet_invalid_customer_session',
            'Customer session is invalid or expired.',
            array(
                'status' => 401,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm User
    |--------------------------------------------------------------------------
    */

    $user =
        get_user_by(
            'id',
            $user_id
        );


    if (
        !$user ||
        !in_array(
            'customer',
            (array)
            $user->roles,
            true
        )
    ) {

        storefleet_customer_destroy_session(
            $user_id,
            $token_hash
        );


        return new WP_Error(
            'storefleet_invalid_customer_session',
            'Customer session is invalid or expired.',
            array(
                'status' => 401,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Session Metadata
    |--------------------------------------------------------------------------
    */

    $sessions =
        get_user_meta(
            $user_id,
            'storefleet_customer_sessions',
            true
        );


    if (
        !is_array($sessions) ||
        !isset(
            $sessions[
                $token_hash
            ]
        )
    ) {

        delete_transient(
            storefleet_customer_session_transient_key(
                $token_hash
            )
        );


        return new WP_Error(
            'storefleet_invalid_customer_session',
            'Customer session is invalid or expired.',
            array(
                'status' => 401,
            )
        );
    }


    $expires_at =
        isset(
            $sessions[
                $token_hash
            ]['expires_at']
        )
            ? absint(
                $sessions[
                    $token_hash
                ]['expires_at']
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | Expired
    |--------------------------------------------------------------------------
    */

    if (
        $expires_at <=
        time()
    ) {

        storefleet_customer_destroy_session(
            $user_id,
            $token_hash
        );


        return new WP_Error(
            'storefleet_customer_session_expired',
            'Customer session has expired.',
            array(
                'status' => 401,
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Status
    |--------------------------------------------------------------------------
    */

    $status =
        get_user_meta(
            $user_id,
            'storefleet_customer_status',
            true
        );


    if (
        $status !== '' &&
        $status !== 'active'
    ) {
        return new WP_Error(
            'storefleet_customer_inactive',
            'This customer account is currently unavailable.',
            array(
                'status' => 403,
            )
        );
    }


    return array(
        'user_id' =>
            $user_id,

        'token_hash' =>
            $token_hash,

        'expires_at' =>
            $expires_at,
    );
}


/*
|--------------------------------------------------------------------------
| Destroy Customer Session
|--------------------------------------------------------------------------
*/

function storefleet_customer_destroy_session(
    $user_id,
    $token_hash
) {

    $user_id =
        absint($user_id);


    $token_hash =
        sanitize_text_field(
            (string)
            $token_hash
        );


    if (
        $user_id <= 0 ||
        $token_hash === ''
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Fast Lookup
    |--------------------------------------------------------------------------
    */

    delete_transient(
        storefleet_customer_session_transient_key(
            $token_hash
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Delete User Session Entry
    |--------------------------------------------------------------------------
    */

    $sessions =
        get_user_meta(
            $user_id,
            'storefleet_customer_sessions',
            true
        );


    if (!is_array($sessions)) {
        return;
    }


    if (
        isset(
            $sessions[
                $token_hash
            ]
        )
    ) {

        unset(
            $sessions[
                $token_hash
            ]
        );


        update_user_meta(
            $user_id,
            'storefleet_customer_sessions',
            $sessions
        );
    }
}


/*
|--------------------------------------------------------------------------
| Get Session Token
|--------------------------------------------------------------------------
|
| Next.js may send:
|
| Authorization: Bearer <token>
|
| or:
|
| X-StoreFleet-Session: <token>
|
*/

function storefleet_customer_get_session_token(
    WP_REST_Request $request
) {

    /*
    |--------------------------------------------------------------------------
    | Authorization Bearer
    |--------------------------------------------------------------------------
    */

    $authorization =
        trim(
            (string)
            $request->get_header(
                'authorization'
            )
        );


    if (
        $authorization !== '' &&
        preg_match(
            '/^Bearer\s+(.+)$/i',
            $authorization,
            $matches
        )
    ) {

        return trim(
            (string)
            $matches[1]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Custom Header
    |--------------------------------------------------------------------------
    */

    $token =
        trim(
            (string)
            $request->get_header(
                'x-storefleet-session'
            )
        );


    return $token;
}


/*
|--------------------------------------------------------------------------
| Session Transient Key
|--------------------------------------------------------------------------
*/

function storefleet_customer_session_transient_key(
    $token_hash
) {
    return
        'storefleet_customer_session_' .
        sanitize_key(
            (string)
            $token_hash
        );
}


/*
|--------------------------------------------------------------------------
| Customer Public Data
|--------------------------------------------------------------------------
*/

function storefleet_customer_public_data(
    WP_User $user
) {

    $phone =
        get_user_meta(
            $user->ID,
            'billing_phone',
            true
        );


    if ($phone === '') {
        $phone =
            get_user_meta(
                $user->ID,
                'storefleet_customer_phone',
                true
            );
    }


    return array(
        'id' =>
            (int)
            $user->ID,

        'first_name' =>
            (string)
            $user->first_name,

        'last_name' =>
            (string)
            $user->last_name,

        'display_name' =>
            (string)
            $user->display_name,

        'email' =>
            (string)
            $user->user_email,

        'phone' =>
            (string)
            $phone,
    );
}


/*
|--------------------------------------------------------------------------
| Generate Customer Username
|--------------------------------------------------------------------------
*/

function storefleet_customer_generate_username(
    $email,
    $first_name,
    $last_name
) {

    /*
    |--------------------------------------------------------------------------
    | Start With Email Local Part
    |--------------------------------------------------------------------------
    */

    $email_parts =
        explode(
            '@',
            $email
        );


    $base =
        isset(
            $email_parts[0]
        )
            ? $email_parts[0]
            : 'customer';


    $base =
        sanitize_user(
            $base,
            true
        );


    /*
    |--------------------------------------------------------------------------
    | Fallback To Name
    |--------------------------------------------------------------------------
    */

    if ($base === '') {
        $base =
            sanitize_user(
                $first_name .
                '.' .
                $last_name,
                true
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Final Fallback
    |--------------------------------------------------------------------------
    */

    if ($base === '') {
        $base =
            'customer';
    }


    /*
    |--------------------------------------------------------------------------
    | Ensure Unique
    |--------------------------------------------------------------------------
    */

    $username =
        $base;


    $counter =
        1;


    while (
        username_exists(
            $username
        )
    ) {

        $username =
            $base .
            $counter;


        $counter++;


        /*
        |--------------------------------------------------------------------------
        | Safety
        |--------------------------------------------------------------------------
        */

        if ($counter > 1000) {
            $username =
                $base .
                wp_rand(
                    100000,
                    999999
                );

            break;
        }
    }


    return $username;
}


/*
|--------------------------------------------------------------------------
| Parse Boolean
|--------------------------------------------------------------------------
*/

function storefleet_customer_parse_boolean(
    $value
) {

    if (is_bool($value)) {
        return $value;
    }


    if (is_int($value)) {
        return $value === 1;
    }


    $value =
        strtolower(
            trim(
                (string)
                $value
            )
        );


    return in_array(
        $value,
        array(
            '1',
            'true',
            'yes',
            'on',
        ),
        true
    );
}