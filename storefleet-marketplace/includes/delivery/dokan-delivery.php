<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Delivery → Dokan React Dashboard
|--------------------------------------------------------------------------
|
| Issue #35
|
| Delivery is a StoreFleet operational module rendered inside Dokan's modern
| React dashboard.
|
| Route:
|
| /dashboard/new/#storefleet-delivery
|
| Supported users:
|
| - Merchant/vendor owner
| - Authorized StoreFleet staff
|
| StoreFleet remains the authorization source of truth.
|
| This integration does NOT grant Dokan vendor capabilities to staff.
|
*/


/*
|--------------------------------------------------------------------------
| Delivery Active Branch User Meta Key
|--------------------------------------------------------------------------
|
| Staff already use the same operational branch preference key.
|
| Merchant owners are different WordPress users, so sharing the key keeps the
| StoreFleet operational branch concept consistent without mixing accounts.
|
*/

function storefleet_delivery_active_branch_meta_key()
{
    return
        'storefleet_active_branch_id';
}


/*
|--------------------------------------------------------------------------
| Is Current User Merchant Owner
|--------------------------------------------------------------------------
*/

function storefleet_delivery_is_merchant_owner()
{
    return
        is_user_logged_in()
        &&
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner();
}


/*
|--------------------------------------------------------------------------
| Is Current User StoreFleet Staff
|--------------------------------------------------------------------------
*/

function storefleet_delivery_is_staff()
{
    return
        is_user_logged_in()
        &&
        function_exists(
            'storefleet_is_staff_user'
        )
        &&
        storefleet_is_staff_user();
}


/*
|--------------------------------------------------------------------------
| Current Merchant Delivery Branches
|--------------------------------------------------------------------------
|
| Merchant owners may operate Delivery from any active branch they own.
|
*/

function storefleet_get_current_merchant_delivery_branches()
{
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
        ||
        !function_exists(
            'storefleet_get_merchant_branches'
        )
    ) {
        return [];
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    if (!$merchant_id) {
        return [];
    }

    $branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );

    if (empty($branches)) {
        return [];
    }

    $valid_branches = [];

    foreach (
        $branches as $branch
    ) {
        if (!$branch) {
            continue;
        }

        $branch_id =
            absint(
                $branch->id ?? 0
            );

        if (!$branch_id) {
            continue;
        }

        if (
            isset($branch->is_active)
            &&
            (int) $branch->is_active !== 1
        ) {
            continue;
        }

        if (
            function_exists(
                'storefleet_merchant_owns_branch'
            )
            &&
            !storefleet_merchant_owns_branch(
                $merchant_id,
                $branch_id
            )
        ) {
            continue;
        }

        $valid_branches[
            $branch_id
        ] = $branch;
    }

    uasort(
        $valid_branches,
        function (
            $branch_a,
            $branch_b
        ) {
            return
                strcmp(
                    strtolower(
                        (string) (
                            $branch_a->name
                            ?? ''
                        )
                    ),
                    strtolower(
                        (string) (
                            $branch_b->name
                            ?? ''
                        )
                    )
                );
        }
    );

    return
        array_values(
            $valid_branches
        );
}


/*
|--------------------------------------------------------------------------
| Merchant Can Select Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_merchant_can_select_delivery_branch(
    $merchant_id,
    $branch_id
) {
    $merchant_id =
        absint(
            $merchant_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$merchant_id
        ||
        !$branch_id
        ||
        !function_exists(
            'storefleet_merchant_owns_branch'
        )
        ||
        !storefleet_merchant_owns_branch(
            $merchant_id,
            $branch_id
        )
        ||
        !function_exists(
            'storefleet_get_branch'
        )
    ) {
        return false;
    }

    $branch =
        storefleet_get_branch(
            $branch_id
        );

    if (!$branch) {
        return false;
    }

    if (
        isset($branch->is_active)
        &&
        (int) $branch->is_active !== 1
    ) {
        return false;
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Current Merchant Delivery Branch ID
|--------------------------------------------------------------------------
|
| Resolution:
|
| 1. read saved operational branch
| 2. verify merchant still owns active branch
| 3. otherwise default to first active merchant branch
| 4. synchronize corrected value to user meta
|
*/

function storefleet_get_current_merchant_delivery_branch_id()
{
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
    ) {
        return 0;
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $user_id =
        get_current_user_id();

    if (
        !$merchant_id
        ||
        !$user_id
    ) {
        return 0;
    }

    $branches =
        storefleet_get_current_merchant_delivery_branches();

    if (empty($branches)) {
        delete_user_meta(
            $user_id,
            storefleet_delivery_active_branch_meta_key()
        );

        return 0;
    }

    $saved_branch_id =
        absint(
            get_user_meta(
                $user_id,
                storefleet_delivery_active_branch_meta_key(),
                true
            )
        );

    if (
        $saved_branch_id
        &&
        storefleet_merchant_can_select_delivery_branch(
            $merchant_id,
            $saved_branch_id
        )
    ) {
        return
            $saved_branch_id;
    }

    $first_branch =
        reset(
            $branches
        );

    $branch_id =
        absint(
            $first_branch->id
            ?? 0
        );

    if (!$branch_id) {
        return 0;
    }

    update_user_meta(
        $user_id,
        storefleet_delivery_active_branch_meta_key(),
        $branch_id
    );

    return
        $branch_id;
}


/*
|--------------------------------------------------------------------------
| Current Merchant Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_current_merchant_delivery_branch()
{
    $branch_id =
        storefleet_get_current_merchant_delivery_branch_id();

    if (
        !$branch_id
        ||
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
| Set Current Merchant Delivery Branch
|--------------------------------------------------------------------------
*/

function storefleet_set_current_merchant_delivery_branch(
    $branch_id
) {
    if (
        !storefleet_delivery_is_merchant_owner()
        ||
        !function_exists(
            'storefleet_get_current_merchant_id'
        )
    ) {
        return false;
    }

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$merchant_id
        ||
        !$branch_id
        ||
        !storefleet_merchant_can_select_delivery_branch(
            $merchant_id,
            $branch_id
        )
    ) {
        return false;
    }

    update_user_meta(
        get_current_user_id(),
        storefleet_delivery_active_branch_meta_key(),
        $branch_id
    );

    return true;
}


/*
|--------------------------------------------------------------------------
| Delivery React URL
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_delivery_react_url()
{
    if (
        function_exists(
            'storefleet_get_dokan_react_dashboard_url'
        )
    ) {
        return
            storefleet_get_dokan_react_dashboard_url(
                'storefleet-delivery'
            );
    }

    if (
        function_exists(
            'dokan_get_navigation_url'
        )
    ) {
        return
            dokan_get_navigation_url(
                'new'
            ) .
            '#storefleet-delivery';
    }

    return
        home_url(
            '/dashboard/new/#storefleet-delivery'
        );
}


/*
|--------------------------------------------------------------------------
| Handle Merchant Delivery Branch Switch
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_handle_merchant_delivery_branch_switch',
    5
);


function storefleet_handle_merchant_delivery_branch_switch()
{
    if (
        strtoupper(
            $_SERVER['REQUEST_METHOD']
            ?? ''
        ) !== 'POST'
    ) {
        return;
    }

    $action =
        isset(
            $_POST[
                'storefleet_delivery_branch_action'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_delivery_branch_action'
                    ]
                )
            )
            : '';

    if (
        $action !==
        'switch_branch'
    ) {
        return;
    }

    if (
        !storefleet_delivery_is_merchant_owner()
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to switch this StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }

    check_admin_referer(
        'storefleet_switch_delivery_branch',
        'storefleet_delivery_branch_nonce'
    );

    $branch_id =
        isset(
            $_POST[
                'storefleet_branch_id'
            ]
        )
            ? absint(
                wp_unslash(
                    $_POST[
                        'storefleet_branch_id'
                    ]
                )
            )
            : 0;

    if (
        !$branch_id
        ||
        !storefleet_set_current_merchant_delivery_branch(
            $branch_id
        )
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to access the selected StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Branch Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }

    wp_safe_redirect(
        storefleet_get_dokan_delivery_react_url()
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Delivery Permissions
|--------------------------------------------------------------------------
|
| Merchant/vendor owner:
|
| - operational access to branches they own
| - can view/manage Delivery
| - can switch between active owned branches
|
| Staff:
|
| - existing StoreFleet role permission model
| - existing selected staff branch context
| - same-role branch authorization remains authoritative
|
*/

function storefleet_get_current_dokan_delivery_permissions()
{
    $permissions = [
        'branchId' =>
            0,

        'branchName' =>
            '',

        'view' =>
            false,

        'manage' =>
            false,

        'rider' =>
            false,

        'isMerchant' =>
            false,

        'isStaff' =>
            false,

        'branches' =>
            [],

        'branchNonce' =>
            '',
    ];


    /*
    |--------------------------------------------------------------------------
    | Merchant / Vendor Owner
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_delivery_is_merchant_owner()
    ) {
        $branch_id =
            storefleet_get_current_merchant_delivery_branch_id();

        if (!$branch_id) {
            return $permissions;
        }

        $branch =
            storefleet_get_current_merchant_delivery_branch();

        $permissions['branchId'] =
            $branch_id;

        $permissions['branchName'] =
            $branch
                ? sanitize_text_field(
                    $branch->name
                    ?? ''
                )
                : '';

        $permissions['isMerchant'] =
            true;

        $permissions['branchNonce'] =
            wp_create_nonce(
                'storefleet_switch_delivery_branch'
            );

        if (
            function_exists(
                'storefleet_current_user_can_storefleet'
            )
        ) {
            $permissions['view'] =
                (bool) storefleet_current_user_can_storefleet(
                    'delivery.view',
                    $branch_id
                );

            $permissions['manage'] =
                (bool) storefleet_current_user_can_storefleet(
                    'delivery.manage',
                    $branch_id
                );
        }

        $merchant_branches =
            storefleet_get_current_merchant_delivery_branches();

        foreach (
            $merchant_branches as $merchant_branch
        ) {
            $merchant_branch_id =
                absint(
                    $merchant_branch->id
                    ?? 0
                );

            if (!$merchant_branch_id) {
                continue;
            }

            $permissions['branches'][] = [
                'id' =>
                    $merchant_branch_id,

                'name' =>
                    sanitize_text_field(
                        $merchant_branch->name
                        ?? ''
                    ),
            ];
        }

        return $permissions;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_delivery_is_staff()
        ||
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return $permissions;
    }

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (!$branch_id) {
        return $permissions;
    }

    $permissions['branchId'] =
        $branch_id;

    $permissions['isStaff'] =
        true;

    if (
        function_exists(
            'storefleet_get_current_staff_branch'
        )
    ) {
        $branch =
            storefleet_get_current_staff_branch();

        if (
            $branch
            &&
            isset($branch->name)
        ) {
            $permissions['branchName'] =
                sanitize_text_field(
                    $branch->name
                );
        }
    }

    if (
        !function_exists(
            'storefleet_current_staff_can'
        )
    ) {
        return $permissions;
    }

    $permissions['view'] =
        (bool) storefleet_current_staff_can(
            'delivery.view',
            $branch_id
        );

    $permissions['manage'] =
        (bool) storefleet_current_staff_can(
            'delivery.manage',
            $branch_id
        );

    $permissions['rider'] =
        (bool) storefleet_current_staff_can(
            'delivery.rider',
            $branch_id
        );

    return $permissions;
}


/*
|--------------------------------------------------------------------------
| Add Delivery To Merchant Dokan Navigation
|--------------------------------------------------------------------------
|
| Staff navigation is already built by dokan-staff-navigation.php.
|
| This filter only adds Delivery to the merchant/vendor owner's normal Dokan
| navigation. It does not alter Staff navigation.
|
*/

add_filter(
    'dokan_get_dashboard_nav',
    'storefleet_add_merchant_delivery_navigation',
    60
);


function storefleet_add_merchant_delivery_navigation(
    $menus
) {
    if (
        !storefleet_delivery_is_merchant_owner()
    ) {
        return $menus;
    }

    $branch_id =
        storefleet_get_current_merchant_delivery_branch_id();

    if (!$branch_id) {
        return $menus;
    }

    if (
        !function_exists(
            'storefleet_current_user_can_storefleet'
        )
        ||
        !storefleet_current_user_can_storefleet(
            'delivery.view',
            $branch_id
        )
    ) {
        return $menus;
    }

    $menus[
        'storefleet-delivery'
    ] = [
        'title' =>
            __(
                'Delivery',
                'storefleet-marketplace'
            ),

        'icon' =>
            '<i class="fas fa-motorcycle"></i>',

        'url' =>
            storefleet_get_dokan_delivery_react_url(),

        'pos' =>
            54,
    ];

    return $menus;
}


/*
|--------------------------------------------------------------------------
| Register Delivery React Route
|--------------------------------------------------------------------------
*/

add_action(
    'wp_enqueue_scripts',
    'storefleet_register_dokan_react_delivery_route',
    122
);


function storefleet_register_dokan_react_delivery_route()
{
    /*
    |--------------------------------------------------------------------------
    | Logged In Merchant Or Staff
    |--------------------------------------------------------------------------
    */

    if (!is_user_logged_in()) {
        return;
    }

    $is_staff =
        storefleet_delivery_is_staff();

    $is_merchant =
        storefleet_delivery_is_merchant_owner();

    if (
        !$is_staff
        &&
        !$is_merchant
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan React Dashboard Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_is_dokan_react_dashboard_request'
        )
        ||
        !storefleet_is_dokan_react_dashboard_request()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Delivery Authorization
    |--------------------------------------------------------------------------
    */

    $permissions =
        storefleet_get_current_dokan_delivery_permissions();

    if (
        empty(
            $permissions['view']
        )
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Ensure Dokan React Assets
    |--------------------------------------------------------------------------
    */

    if (
        !wp_script_is(
            'dokan-react-frontend',
            'enqueued'
        )
    ) {
        wp_enqueue_script(
            'dokan-react-frontend'
        );
    }

    if (
        !wp_style_is(
            'dokan-react-frontend',
            'enqueued'
        )
    ) {
        wp_enqueue_style(
            'dokan-react-frontend'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delivery Dashboard Styles
    |--------------------------------------------------------------------------
    */

    $styles = <<<'CSS'

.storefleet-delivery-dashboard {
    width: 100%;
}

.storefleet-delivery-merchant-branch-context {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
}

.storefleet-delivery-merchant-branch-context strong {
    color: #111827;
}

.storefleet-delivery-branch-context-label {
    margin-top: 3px;
    color: #6b7280;
    font-size: 13px;
}

.storefleet-delivery-branch-switch-form {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.storefleet-delivery-branch-select {
    min-width: 220px;
    min-height: 38px;
    padding: 7px 34px 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #ffffff;
    color: #111827;
    font-size: 14px;
}

.storefleet-delivery-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.storefleet-delivery-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 700;
    color: #111827;
}

.storefleet-delivery-header p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}

.storefleet-delivery-access-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: #f5f3ff;
    color: #6d28d9;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}

.storefleet-delivery-access-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.storefleet-delivery-stats {
    display: grid;
    grid-template-columns:
        repeat(
            4,
            minmax(0, 1fr)
        );
    gap: 16px;
    margin-bottom: 24px;
}

.storefleet-delivery-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 96px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.storefleet-delivery-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    border-radius: 10px;
    font-size: 19px;
    font-weight: 700;
}

.storefleet-delivery-stat-icon.riders {
    background: #f5f3ff;
    color: #7c3aed;
}

.storefleet-delivery-stat-icon.pending {
    background: #fff7ed;
    color: #ea580c;
}

.storefleet-delivery-stat-icon.transit {
    background: #eff6ff;
    color: #2563eb;
}

.storefleet-delivery-stat-icon.completed {
    background: #f0fdf4;
    color: #16a34a;
}

.storefleet-delivery-stat-content {
    min-width: 0;
}

.storefleet-delivery-stat-label {
    margin-bottom: 4px;
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
}

.storefleet-delivery-stat-value {
    color: #111827;
    font-size: 25px;
    line-height: 1;
    font-weight: 700;
}

.storefleet-delivery-panel {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.storefleet-delivery-panel-header {
    padding: 20px 22px 18px;
    border-bottom: 1px solid #e5e7eb;
}

.storefleet-delivery-panel-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.storefleet-delivery-panel-title {
    margin: 0;
    color: #111827;
    font-size: 20px;
    font-weight: 650;
}

.storefleet-delivery-toolbar {
    display: grid;
    grid-template-columns:
        180px
        180px
        minmax(220px, 1fr)
        auto;
    gap: 12px;
    align-items: center;
}

.storefleet-delivery-control {
    width: 100%;
    min-height: 42px;
    padding: 0 12px;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    color: #374151;
    font-size: 14px;
    outline: none;
}

.storefleet-delivery-control:focus {
    border-color: #7c3aed;
    box-shadow:
        0 0 0 1px #7c3aed;
}

.storefleet-delivery-control:disabled {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
}

.storefleet-delivery-search-wrap {
    position: relative;
}

.storefleet-delivery-search-icon {
    position: absolute;
    top: 50%;
    left: 13px;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 16px;
    pointer-events: none;
}

.storefleet-delivery-search {
    padding-left: 38px;
}

.storefleet-delivery-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 17px;
    border: 0;
    border-radius: 7px;
    background: #7047eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.storefleet-delivery-button:hover {
    background: #6038da;
}

.storefleet-delivery-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.storefleet-delivery-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.storefleet-delivery-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.storefleet-delivery-table th {
    padding: 13px 18px;
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
    color: #4b5563;
    font-size: 12px;
    font-weight: 650;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.storefleet-delivery-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f0f1f3;
    color: #374151;
    font-size: 14px;
    vertical-align: middle;
}

.storefleet-delivery-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 285px;
    padding: 40px 24px;
    text-align: center;
}

.storefleet-delivery-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 58px;
    margin-bottom: 16px;
    border-radius: 14px;
    background: #f5f3ff;
    color: #7c3aed;
    font-size: 25px;
}

.storefleet-delivery-empty h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 17px;
    font-weight: 650;
}

.storefleet-delivery-empty p {
    max-width: 460px;
    margin: 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.55;
}

.storefleet-delivery-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 55px;
    padding: 0 20px;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 13px;
}

.storefleet-delivery-branch-label {
    font-weight: 600;
    color: #374151;
}

@media (max-width: 1100px) {
    .storefleet-delivery-stats {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }

    .storefleet-delivery-toolbar {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }
}

@media (max-width: 700px) {
    .storefleet-delivery-merchant-branch-context,
    .storefleet-delivery-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .storefleet-delivery-branch-switch-form,
    .storefleet-delivery-branch-select {
        width: 100%;
    }

    .storefleet-delivery-stats {
        grid-template-columns:
            1fr;
    }

    .storefleet-delivery-toolbar {
        grid-template-columns:
            1fr;
    }

    .storefleet-delivery-panel-title-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .storefleet-delivery-footer {
        align-items: flex-start;
        flex-direction: column;
        padding-top: 14px;
        padding-bottom: 14px;
    }
}

CSS;


    wp_add_inline_style(
        'dokan-react-frontend',
        $styles
    );


    /*
    |--------------------------------------------------------------------------
    | Permission Payload
    |--------------------------------------------------------------------------
    */

    wp_add_inline_script(
        'dokan-react-frontend',
        'window.storefleetDokanDeliveryPermissions = ' .
        wp_json_encode(
            $permissions
        ) .
        ';',
        'before'
    );


    /*
    |--------------------------------------------------------------------------
    | React Component + Route
    |--------------------------------------------------------------------------
    */

    $script = <<<'JS'
(function () {
    'use strict';

    if (
        !window.storefleetDokanDeliveryPermissions
    ) {
        return;
    }

    if (
        !window.wp ||
        !window.wp.hooks ||
        !window.wp.element ||
        typeof window.wp.hooks.addFilter !== 'function'
    ) {
        return;
    }

    var permissions =
        window.storefleetDokanDeliveryPermissions;

    var createElement =
        window.wp.element.createElement;


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    function permissionLabel() {
        if (permissions.isMerchant) {
            return 'Merchant Delivery';
        }

        if (permissions.manage) {
            return 'Manage Delivery';
        }

        if (permissions.rider) {
            return 'Rider Operations';
        }

        return 'View Delivery';
    }


    function statCard(
        icon,
        iconClass,
        label,
        value
    ) {
        return createElement(
            'div',
            {
                className:
                    'storefleet-delivery-stat-card'
            },

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-stat-icon ' +
                        iconClass
                },
                icon
            ),

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-stat-content'
                },

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-stat-label'
                    },
                    label
                ),

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-stat-value'
                    },
                    String(value)
                )
            )
        );
    }


    function merchantBranchSelector() {
        if (!permissions.isMerchant) {
            return null;
        }

        var branches =
            Array.isArray(permissions.branches)
                ? permissions.branches
                : [];

        var branchName =
            permissions.branchName ||
            'No active branch';

        var rightSide;

        if (branches.length === 0) {
            rightSide =
                createElement(
                    'strong',
                    null,
                    'No active branches'
                );
        } else if (branches.length === 1) {
            rightSide =
                createElement(
                    'strong',
                    null,
                    branchName
                );
        } else {
            rightSide =
                createElement(
                    'form',
                    {
                        method:
                            'post',

                        action:
                            '',

                        className:
                            'storefleet-delivery-branch-switch-form'
                    },

                    createElement(
                        'input',
                        {
                            type:
                                'hidden',

                            name:
                                'storefleet_delivery_branch_action',

                            value:
                                'switch_branch'
                        }
                    ),

                    createElement(
                        'input',
                        {
                            type:
                                'hidden',

                            name:
                                'storefleet_delivery_branch_nonce',

                            value:
                                permissions.branchNonce || ''
                        }
                    ),

                    createElement(
                        'select',
                        {
                            name:
                                'storefleet_branch_id',

                            className:
                                'storefleet-delivery-branch-select',

                            value:
                                String(permissions.branchId || ''),

                            onChange:
                                function (event) {
                                    if (
                                        event &&
                                        event.currentTarget &&
                                        event.currentTarget.form
                                    ) {
                                        event.currentTarget.form.submit();
                                    }
                                }
                        },

                        branches.map(
                            function (branch) {
                                return createElement(
                                    'option',
                                    {
                                        key:
                                            String(branch.id),

                                        value:
                                            String(branch.id)
                                    },
                                    branch.name
                                );
                            }
                        )
                    )
                );
        }

        return createElement(
            'div',
            {
                className:
                    'storefleet-delivery-merchant-branch-context'
            },

            createElement(
                'div',
                null,

                createElement(
                    'strong',
                    null,
                    'Current Branch'
                ),

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-branch-context-label'
                    },
                    'StoreFleet operational context'
                )
            ),

            rightSide
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delivery Component
    |--------------------------------------------------------------------------
    |
    | Delivery data is intentionally empty for now.
    |
    | The UI shell is complete, but real delivery rows will be connected to:
    |
    | - StoreFleet orders
    | - fulfillment branches
    | - Lalamove
    |
    */

    function StoreFleetDelivery() {
        var branchName =
            permissions.branchName ||
            'Selected branch';

        var canManage =
            !!permissions.manage;

        var metrics = {
            activeRiders: 0,
            pending: 0,
            inTransit: 0,
            completedToday: 0
        };


        return createElement(
            'div',
            {
                className:
                    'storefleet-delivery-dashboard'
            },


            /*
            |------------------------------------------------------------------
            | Merchant Branch Selector
            |------------------------------------------------------------------
            |
            | Staff already receive the global StoreFleet branch selector from
            | dokan-staff-branch-context.php.
            |
            */

            merchantBranchSelector(),


            /*
            |------------------------------------------------------------------
            | Header
            |------------------------------------------------------------------
            */

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-header'
                },

                createElement(
                    'div',
                    null,

                    createElement(
                        'h1',
                        null,
                        'Delivery'
                    ),

                    createElement(
                        'p',
                        null,
                        'Manage StoreFleet delivery operations for the selected branch.'
                    )
                ),

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-access-badge'
                    },

                    createElement(
                        'span',
                        {
                            className:
                                'storefleet-delivery-access-dot'
                        }
                    ),

                    permissionLabel()
                )
            ),


            /*
            |------------------------------------------------------------------
            | Summary Cards
            |------------------------------------------------------------------
            */

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-stats'
                },

                statCard(
                    'R',
                    'riders',
                    'Active Riders',
                    metrics.activeRiders
                ),

                statCard(
                    'P',
                    'pending',
                    'Pending Deliveries',
                    metrics.pending
                ),

                statCard(
                    'T',
                    'transit',
                    'In Transit',
                    metrics.inTransit
                ),

                statCard(
                    '✓',
                    'completed',
                    'Completed Today',
                    metrics.completedToday
                )
            ),


            /*
            |------------------------------------------------------------------
            | Delivery Queue
            |------------------------------------------------------------------
            */

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-panel'
                },


                /*
                |------------------------------------------------------------------
                | Panel Header
                |------------------------------------------------------------------
                */

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-panel-header'
                    },

                    createElement(
                        'div',
                        {
                            className:
                                'storefleet-delivery-panel-title-row'
                        },

                        createElement(
                            'h2',
                            {
                                className:
                                    'storefleet-delivery-panel-title'
                            },
                            'Delivery Queue'
                        ),

                        createElement(
                            'span',
                            {
                                className:
                                    'storefleet-delivery-branch-label'
                            },
                            branchName
                        )
                    ),


                    /*
                    |------------------------------------------------------------------
                    | Toolbar
                    |------------------------------------------------------------------
                    */

                    createElement(
                        'div',
                        {
                            className:
                                'storefleet-delivery-toolbar'
                        },

                        createElement(
                            'select',
                            {
                                className:
                                    'storefleet-delivery-control',

                                disabled:
                                    true,

                                defaultValue:
                                    'all'
                            },

                            createElement(
                                'option',
                                {
                                    value:
                                        'all'
                                },
                                'All Statuses'
                            )
                        ),

                        createElement(
                            'select',
                            {
                                className:
                                    'storefleet-delivery-control',

                                disabled:
                                    true,

                                defaultValue:
                                    'all'
                            },

                            createElement(
                                'option',
                                {
                                    value:
                                        'all'
                                },
                                'All Riders'
                            )
                        ),

                        createElement(
                            'div',
                            {
                                className:
                                    'storefleet-delivery-search-wrap'
                            },

                            createElement(
                                'span',
                                {
                                    className:
                                        'storefleet-delivery-search-icon'
                                },
                                '⌕'
                            ),

                            createElement(
                                'input',
                                {
                                    type:
                                        'search',

                                    className:
                                        'storefleet-delivery-control storefleet-delivery-search',

                                    placeholder:
                                        'Search orders, customers, addresses...',

                                    disabled:
                                        true
                                }
                            )
                        ),

                        canManage
                            ? createElement(
                                'button',
                                {
                                    type:
                                        'button',

                                    className:
                                        'storefleet-delivery-button',

                                    disabled:
                                        true,

                                    title:
                                        'Delivery assignment will be enabled when the StoreFleet delivery workflow is connected.'
                                },

                                createElement(
                                    'span',
                                    null,
                                    '+'
                                ),

                                'Assign Delivery'
                            )
                            : createElement(
                                'span',
                                null
                            )
                    )
                ),


                /*
                |------------------------------------------------------------------
                | Table Header
                |------------------------------------------------------------------
                */

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-table-wrap'
                    },

                    createElement(
                        'table',
                        {
                            className:
                                'storefleet-delivery-table'
                        },

                        createElement(
                            'thead',
                            null,

                            createElement(
                                'tr',
                                null,

                                createElement(
                                    'th',
                                    null,
                                    'Order #'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'Customer'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'Address'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'Rider'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'Status'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'ETA'
                                ),

                                createElement(
                                    'th',
                                    null,
                                    'Actions'
                                )
                            )
                        )
                    )
                ),


                /*
                |------------------------------------------------------------------
                | Empty State
                |------------------------------------------------------------------
                */

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-empty'
                    },

                    createElement(
                        'div',
                        {
                            className:
                                'storefleet-delivery-empty-icon'
                        },
                        '↗'
                    ),

                    createElement(
                        'h3',
                        null,
                        'No deliveries yet'
                    ),

                    createElement(
                        'p',
                        null,
                        'There are currently no delivery records for ' +
                        branchName +
                        '. Orders assigned for delivery will appear here.'
                    )
                ),


                /*
                |------------------------------------------------------------------
                | Footer
                |------------------------------------------------------------------
                */

                createElement(
                    'div',
                    {
                        className:
                            'storefleet-delivery-footer'
                    },

                    createElement(
                        'span',
                        null,
                        'Showing 0 deliveries'
                    ),

                    createElement(
                        'span',
                        null,
                        'Branch: ' +
                        branchName
                    )
                )
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan Dashboard Route
    |--------------------------------------------------------------------------
    */

    window.wp.hooks.addFilter(
        'dokan-dashboard-routes',
        'storefleet/delivery-route',
        function (routes) {
            if (!Array.isArray(routes)) {
                return routes;
            }

            var exists =
                routes.some(
                    function (route) {
                        return (
                            route &&
                            route.id ===
                                'storefleet-delivery'
                        );
                    }
                );

            if (exists) {
                return routes;
            }

            var nextRoutes =
                routes.slice();

            nextRoutes.push(
                {
                    id:
                        'storefleet-delivery',

                    title:
                        'Delivery',

                    path:
                        '/storefleet-delivery',

                    exact:
                        true,

                    order:
                        60,

                    capabilities:
                        [],

                    element:
                        createElement(
                            StoreFleetDelivery
                        )
                }
            );

            return nextRoutes;
        }
    );

})();
JS;


    /*
    |--------------------------------------------------------------------------
    | Execute Before Dokan React
    |--------------------------------------------------------------------------
    */

    wp_add_inline_script(
        'dokan-react-frontend',
        $script,
        'before'
    );
}