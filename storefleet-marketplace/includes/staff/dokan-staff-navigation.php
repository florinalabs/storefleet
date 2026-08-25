<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Staff Navigation
|--------------------------------------------------------------------------
|
| Feature #30
|
| Staff navigation is operational only.
|
| Staff DO NOT receive sidebar pages for:
|
| - Dashboard
| - Branches
| - Inventory
| - Withdraw
| - Settings
|
| Branch selection is handled globally by the StoreFleet branch selector.
|
| Operational modules require:
|
| permission + current branch
|
*/


/*
|--------------------------------------------------------------------------
| Staff Operational Route Definitions
|--------------------------------------------------------------------------
|
| Route order also controls the preferred landing-page order.
|
| The first route the staff member can access for the selected branch becomes
| their staff dashboard landing page.
|
*/

function storefleet_get_dokan_staff_routes()
{
    return [

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        |
        | Modern Dokan React Products:
        |
        | /dashboard/new/#products/
        |
        */

        'storefleet-products' => [
            'title' =>
                'Products',

            'icon' =>
                '<i class="fas fa-box"></i>',

            'permission' =>
                'products.view',

            'branch_scoped' =>
                true,

            'native_dokan_route' =>
                '',

            'react_route' =>
                'products/',

            'position' =>
                10,
        ],


        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        |
        | StoreFleet-controlled for now.
        |
        | This can later move to Dokan React #orders after the order action
        | permissions are mapped.
        |
        */

        'storefleet-orders' => [
            'title' =>
                'Orders',

            'icon' =>
                '<i class="fas fa-shopping-cart"></i>',

            'permission' =>
                'orders.view',

            'branch_scoped' =>
                true,

            'native_dokan_route' =>
                '',

            'react_route' =>
                '',

            'position' =>
                20,
        ],


        /*
        |--------------------------------------------------------------------------
        | POS
        |--------------------------------------------------------------------------
        |
        | dokan-staff-wepos.php replaces this placeholder with the real
        | wePOS frontend navigation item.
        |
        */

        'storefleet-pos' => [
            'title' =>
                'POS',

            'icon' =>
                '<i class="fas fa-cash-register"></i>',

            'permission' =>
                'pos.use',

            'branch_scoped' =>
                true,

            'native_dokan_route' =>
                '',

            'react_route' =>
                '',

            'position' =>
                30,
        ],


        /*
        |--------------------------------------------------------------------------
        | Delivery
        |--------------------------------------------------------------------------
        */

        'storefleet-delivery' => [
            'title' =>
                'Delivery',

            'icon' =>
                '<i class="fas fa-motorcycle"></i>',

            'permission' =>
                'delivery.view',

            'branch_scoped' =>
                true,

            'native_dokan_route' =>
                '',

            'react_route' =>
                '',

            'position' =>
                40,
        ],


        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        'storefleet-team' => [
            'title' =>
                'Staff',

            'icon' =>
                '<i class="fas fa-users"></i>',

            'permission' =>
                'staff.view',

            'branch_scoped' =>
                true,

            'native_dokan_route' =>
                '',

            'react_route' =>
                '',

            'position' =>
                50,
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| Register Dokan Query Vars
|--------------------------------------------------------------------------
|
| StoreFleet PHP routes require custom query vars.
|
| Native Dokan routes already exist.
|
| React routes all enter through Dokan's existing:
|
| /dashboard/new/
|
*/

add_filter(
    'dokan_query_var_filter',
    'storefleet_register_dokan_staff_query_vars'
);


function storefleet_register_dokan_staff_query_vars(
    $query_vars
) {
    $routes =
        storefleet_get_dokan_staff_routes();

    foreach (
        $routes as
        $route_key => $route
    ) {
        $native_route =
            sanitize_key(
                $route['native_dokan_route']
                ?? ''
            );

        $react_route =
            sanitize_text_field(
                $route['react_route']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | Native Dokan Route
        |--------------------------------------------------------------------------
        */

        if ($native_route !== '') {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Dokan React Route
        |--------------------------------------------------------------------------
        */

        if ($react_route !== '') {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | StoreFleet PHP Route
        |--------------------------------------------------------------------------
        */

        $query_vars[$route_key] =
            $route_key;
    }

    return $query_vars;
}


/*
|--------------------------------------------------------------------------
| Is Current User StoreFleet Staff
|--------------------------------------------------------------------------
*/

function storefleet_dokan_navigation_is_staff()
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
    ) {
        return false;
    }

    return
        storefleet_is_staff_user();
}


/*
|--------------------------------------------------------------------------
| Route Requires Branch Context
|--------------------------------------------------------------------------
*/

function storefleet_dokan_navigation_route_is_branch_scoped(
    array $route
) {
    return
        !empty(
            $route['branch_scoped']
        );
}


/*
|--------------------------------------------------------------------------
| Get Current Navigation Branch ID
|--------------------------------------------------------------------------
|
| The branch selector is now the branch-control UI.
|
| There is no standalone Staff "Branches" navigation page.
|
*/

function storefleet_dokan_navigation_branch_id()
{
    if (
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        return 0;
    }

    return
        absint(
            storefleet_get_current_staff_branch_id()
        );
}


/*
|--------------------------------------------------------------------------
| Staff Can View Navigation Route
|--------------------------------------------------------------------------
*/

function storefleet_staff_can_view_dokan_route(
    array $route,
    $branch_id = 0
) {
    $permission =
        sanitize_text_field(
            $route['permission']
            ?? ''
        );

    if ($permission === '') {
        return false;
    }

    if (
        !function_exists(
            'storefleet_current_staff_can'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Account-Level Route
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_dokan_navigation_route_is_branch_scoped(
            $route
        )
    ) {
        return
            storefleet_current_staff_can(
                $permission
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Branch-Scoped Route
    |--------------------------------------------------------------------------
    */

    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return false;
    }

    return
        storefleet_current_staff_can(
            $permission,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Is Dokan React Dashboard Request
|--------------------------------------------------------------------------
|
| Browser hash fragments are not sent to PHP.
|
| Example:
|
| Browser:
|
| /dashboard/new/#products/
|
| PHP receives:
|
| /dashboard/new/
|
*/

function storefleet_is_dokan_react_dashboard_request()
{
    global $wp;

    return
        isset(
            $wp->query_vars['new']
        );
}


/*
|--------------------------------------------------------------------------
| Get Dokan React Dashboard URL
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_react_dashboard_url(
    $react_route = ''
) {
    $react_route =
        trim(
            sanitize_text_field(
                $react_route
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Base React Dashboard
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'dokan_get_navigation_url'
        )
    ) {
        $base_url =
            dokan_get_navigation_url(
                'new'
            );
    } else {
        $base_url =
            home_url(
                '/dashboard/new/'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Base URL Only
    |--------------------------------------------------------------------------
    */

    if ($react_route === '') {
        return
            $base_url;
    }


    /*
    |--------------------------------------------------------------------------
    | Add React Hash Route
    |--------------------------------------------------------------------------
    */

    return
        $base_url .
        '#' .
        ltrim(
            $react_route,
            '/#'
        );
}


/*
|--------------------------------------------------------------------------
| Get Route URL
|--------------------------------------------------------------------------
|
| Route types:
|
| Dokan React:
|
| /dashboard/new/#products/
|
| Native Dokan:
|
| /dashboard/<native-route>/
|
| StoreFleet:
|
| /dashboard/storefleet-orders/
|
*/

function storefleet_get_dokan_staff_route_url(
    $route_key,
    array $route
) {
    /*
    |--------------------------------------------------------------------------
    | Dokan React Route
    |--------------------------------------------------------------------------
    */

    $react_route =
        sanitize_text_field(
            $route['react_route']
            ?? ''
        );

    if ($react_route !== '') {
        return
            storefleet_get_dokan_react_dashboard_url(
                $react_route
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Native Dokan Route
    |--------------------------------------------------------------------------
    */

    $native_route =
        sanitize_key(
            $route['native_dokan_route']
            ?? ''
        );

    if ($native_route !== '') {
        if (
            function_exists(
                'dokan_get_navigation_url'
            )
        ) {
            return
                dokan_get_navigation_url(
                    $native_route
                );
        }

        return
            home_url(
                '/dashboard/' .
                $native_route .
                '/'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Route
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'dokan_get_navigation_url'
        )
    ) {
        return
            dokan_get_navigation_url(
                $route_key
            );
    }

    return
        home_url(
            '/dashboard/' .
            $route_key .
            '/'
        );
}


/*
|--------------------------------------------------------------------------
| Sort Staff Routes
|--------------------------------------------------------------------------
*/

function storefleet_get_sorted_dokan_staff_routes()
{
    $routes =
        storefleet_get_dokan_staff_routes();

    uasort(
        $routes,
        function (
            $route_a,
            $route_b
        ) {
            $position_a =
                absint(
                    $route_a['position']
                    ?? 999
                );

            $position_b =
                absint(
                    $route_b['position']
                    ?? 999
                );

            return
                $position_a
                <=>
                $position_b;
        }
    );

    return
        $routes;
}


/*
|--------------------------------------------------------------------------
| Get First Accessible Staff Route Key
|--------------------------------------------------------------------------
|
| This replaces the old StoreFleet staff Dashboard landing page.
|
| The first operational module the employee can access for the selected
| branch becomes their landing page.
|
*/

function storefleet_get_first_dokan_staff_route_key(
    $branch_id = 0
) {
    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        $branch_id =
            storefleet_dokan_navigation_branch_id();
    }

    $routes =
        storefleet_get_sorted_dokan_staff_routes();

    foreach (
        $routes as
        $route_key => $route
    ) {
        if (
            storefleet_staff_can_view_dokan_route(
                $route,
                $branch_id
            )
        ) {
            return
                $route_key;
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Get First Accessible Staff Route URL
|--------------------------------------------------------------------------
*/

function storefleet_get_first_dokan_staff_route_url(
    $branch_id = 0
) {
    $route_key =
        storefleet_get_first_dokan_staff_route_key(
            $branch_id
        );

    if ($route_key === '') {
        return '';
    }

    $routes =
        storefleet_get_dokan_staff_routes();

    if (
        !isset(
            $routes[$route_key]
        )
    ) {
        return '';
    }

    return
        storefleet_get_dokan_staff_route_url(
            $route_key,
            $routes[$route_key]
        );
}


/*
|--------------------------------------------------------------------------
| Build StoreFleet Staff Dokan Navigation
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_get_dashboard_nav',
    'storefleet_filter_dokan_staff_navigation',
    9999
);


function storefleet_filter_dokan_staff_navigation(
    $navigation
) {
    /*
    |--------------------------------------------------------------------------
    | Merchant Owner / Non-Staff
    |--------------------------------------------------------------------------
    |
    | Merchant navigation is not modified here.
    |
    */

    if (
        !storefleet_dokan_navigation_is_staff()
    ) {
        return $navigation;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return [];
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Current Branch
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_dokan_navigation_branch_id();


    /*
    |--------------------------------------------------------------------------
    | Build Operational Navigation
    |--------------------------------------------------------------------------
    */

    $routes =
        storefleet_get_sorted_dokan_staff_routes();

    $staff_navigation =
        [];

    foreach (
        $routes as
        $route_key => $route
    ) {
        /*
        |--------------------------------------------------------------------------
        | StoreFleet Authorization
        |--------------------------------------------------------------------------
        */

        if (
            !storefleet_staff_can_view_dokan_route(
                $route,
                $branch_id
            )
        ) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | URL
        |--------------------------------------------------------------------------
        */

        $url =
            storefleet_get_dokan_staff_route_url(
                $route_key,
                $route
            );


        /*
        |--------------------------------------------------------------------------
        | Navigation Item
        |--------------------------------------------------------------------------
        */

        $staff_navigation[
            $route_key
        ] = [
            'title' =>
                $route['title'],

            'icon' =>
                $route['icon'],

            'url' =>
                $url,

            'pos' =>
                absint(
                    $route['position']
                ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Never Merge Merchant Navigation
    |--------------------------------------------------------------------------
    |
    | This prevents Staff from receiving:
    |
    | - Dashboard
    | - Branches management
    | - Inventory page
    | - Withdraw
    | - Settings
    | - payout/bank configuration
    |
    */

    return
        $staff_navigation;
}


/*
|--------------------------------------------------------------------------
| Resolve Current StoreFleet Custom Route
|--------------------------------------------------------------------------
*/

function storefleet_get_current_dokan_staff_route()
{
    global $wp;

    $routes =
        storefleet_get_dokan_staff_routes();

    foreach (
        $routes as
        $route_key => $route
    ) {
        $native_route =
            sanitize_key(
                $route['native_dokan_route']
                ?? ''
            );

        $react_route =
            sanitize_text_field(
                $route['react_route']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | Not StoreFleet PHP Route
        |--------------------------------------------------------------------------
        */

        if (
            $native_route !== ''
            ||
            $react_route !== ''
        ) {
            continue;
        }


        if (
            isset(
                $wp->query_vars[
                    $route_key
                ]
            )
        ) {
            return
                $route_key;
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Resolve Current Native Dokan Staff Route
|--------------------------------------------------------------------------
*/

function storefleet_get_current_native_dokan_staff_route()
{
    global $wp;

    $routes =
        storefleet_get_dokan_staff_routes();

    foreach (
        $routes as
        $route_key => $route
    ) {
        $native_route =
            sanitize_key(
                $route['native_dokan_route']
                ?? ''
            );

        if ($native_route === '') {
            continue;
        }

        if (
            isset(
                $wp->query_vars[
                    $native_route
                ]
            )
        ) {
            return [
                'route_key' =>
                    $route_key,

                'native_route' =>
                    $native_route,

                'route' =>
                    $route,
            ];
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Get Staff Home URL
|--------------------------------------------------------------------------
|
| Compatibility helper.
|
| "Home" now means:
|
| first accessible operational module
|
| NOT a StoreFleet Dashboard page.
|
*/

function storefleet_get_dokan_staff_home_url()
{
    $first_url =
        storefleet_get_first_dokan_staff_route_url();

    if ($first_url !== '') {
        return
            $first_url;
    }


    /*
    |--------------------------------------------------------------------------
    | Safe Fallback
    |--------------------------------------------------------------------------
    |
    | The dashboard root can then resolve after branch context is available.
    |
    */

    return
        home_url(
            '/dashboard/'
        );
}


/*
|--------------------------------------------------------------------------
| Redirect Dokan Dashboard Root
|--------------------------------------------------------------------------
|
| Staff no longer have a Dashboard module.
|
| /dashboard/
|
| redirects to the first permitted operational module for the currently
| selected branch.
|
*/

add_action(
    'template_redirect',
    'storefleet_redirect_dokan_staff_dashboard_home',
    15
);


function storefleet_redirect_dokan_staff_dashboard_home()
{
    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_dokan_navigation_is_staff()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan Dashboard Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'dokan_is_seller_dashboard'
        )
        ||
        !dokan_is_seller_dashboard()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Existing StoreFleet Operational Route
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_get_current_dokan_staff_route()
        !== ''
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Existing Native Dokan Route
    |--------------------------------------------------------------------------
    */

    if (
        storefleet_get_current_native_dokan_staff_route()
        !== null
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan React Dashboard
    |--------------------------------------------------------------------------
    |
    | Never redirect:
    |
    | /dashboard/new/
    |
    | because browser-side React owns:
    |
    | #products/
    | #orders
    | etc.
    |
    */

    if (
        storefleet_is_dokan_react_dashboard_request()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Current Branch
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_dokan_navigation_branch_id();

    if (!$branch_id) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | First Accessible Operational Module
    |--------------------------------------------------------------------------
    */

    $first_url =
        storefleet_get_first_dokan_staff_route_url(
            $branch_id
        );

    if ($first_url === '') {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    wp_safe_redirect(
        $first_url
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Login Redirect
|--------------------------------------------------------------------------
|
| Login first enters /dashboard/.
|
| Once the authenticated dashboard request is made, the redirect above uses
| the real StoreFleet branch context and sends the employee to their first
| permitted operational module.
|
| This avoids relying on branch session state during WordPress login.
|
*/

add_filter(
    'login_redirect',
    'storefleet_staff_dokan_home_login_redirect',
    100,
    3
);


function storefleet_staff_dokan_home_login_redirect(
    $redirect_to,
    $requested_redirect_to,
    $user
) {
    /*
    |--------------------------------------------------------------------------
    | Valid User
    |--------------------------------------------------------------------------
    */

    if (
        !($user instanceof WP_User)
    ) {
        return $redirect_to;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $redirect_to;
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Record
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_staff_by_user_id'
        )
    ) {
        return $redirect_to;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user->ID
        );


    /*
    |--------------------------------------------------------------------------
    | Active Staff
    |--------------------------------------------------------------------------
    */

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return $redirect_to;
    }


    /*
    |--------------------------------------------------------------------------
    | Enter Dokan Dashboard
    |--------------------------------------------------------------------------
    |
    | template_redirect will then choose the first operational route using
    | the staff member's current/available branch.
    |
    */

    return
        home_url(
            '/dashboard/'
        );
}