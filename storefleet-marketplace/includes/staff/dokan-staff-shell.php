<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Staff Shell
|--------------------------------------------------------------------------
|
| Feature #30
|
| StoreFleet employees remain:
|
| - WordPress role: storefleet_staff
| - NOT Dokan vendors
| - NOT dokandar
|
| Staff no longer have a StoreFleet Dashboard page.
|
| The staff interface now consists only of authorized operational modules:
|
| - Products
| - Orders
| - wePOS
| - Delivery
| - Staff
|
| Branch selection is a global operational context and replaces the old
| Branches sidebar page.
|
| Every operational module is authorized using:
|
| StoreFleet permission + selected branch
|
*/


/*
|--------------------------------------------------------------------------
| Enqueue Dokan React Assets For Staff
|--------------------------------------------------------------------------
|
| Dokan normally loads these assets for:
|
| /dashboard/new/
|
| StoreFleet explicitly ensures they are available for staff because staff
| deliberately remain non-vendor WordPress users.
|
*/

add_action(
    'wp_enqueue_scripts',
    'storefleet_enqueue_dokan_react_staff_assets',
    100
);


function storefleet_enqueue_dokan_react_staff_assets()
{
    /*
    |--------------------------------------------------------------------------
    | Logged In
    |--------------------------------------------------------------------------
    */

    if (!is_user_logged_in()) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | React Dashboard Request Only
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
    | Dokan React Frontend
    |--------------------------------------------------------------------------
    */

    wp_enqueue_script(
        'dokan-react-frontend'
    );

    wp_enqueue_style(
        'dokan-react-frontend'
    );
}


/*
|--------------------------------------------------------------------------
| Intercept Dokan Dashboard Shortcode
|--------------------------------------------------------------------------
|
| Dokan's normal [dokan-dashboard] shortcode rejects non-seller users.
|
| StoreFleet intercepts it before Dokan performs that seller-role check.
|
*/

add_filter(
    'pre_do_shortcode_tag',
    'storefleet_intercept_dokan_dashboard_shortcode',
    10,
    4
);


function storefleet_intercept_dokan_dashboard_shortcode(
    $return,
    $tag,
    $attr,
    $match
) {
    /*
    |--------------------------------------------------------------------------
    | Dokan Dashboard Only
    |--------------------------------------------------------------------------
    */

    if (
        $tag !==
        'dokan-dashboard'
    ) {
        return $return;
    }


    /*
    |--------------------------------------------------------------------------
    | Logged In
    |--------------------------------------------------------------------------
    */

    if (!is_user_logged_in()) {
        return $return;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Only
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        return $return;
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'StoreFleet staff authorization is unavailable.'
            );
    }

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return
            storefleet_render_dokan_staff_shell_error(
                'Your StoreFleet staff account could not be found.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff Required
    |--------------------------------------------------------------------------
    */

    if (
        (int) $staff->is_active
        !== 1
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'Your StoreFleet staff account is suspended.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | There is deliberately NO dashboard.view check here anymore.
    |
    | Dashboard is no longer a staff module.
    |
    | Authorization happens against the requested operational module and the
    | currently selected branch.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Ensure Dokan Merchant Context
    |--------------------------------------------------------------------------
    |
    | StoreFleet maps the employee to the merchant owner through _vendor_id.
    |
    | Staff still do NOT receive dokandar.
    |
    */

    if (
        function_exists(
            'storefleet_sync_staff_dokan_context'
        )
    ) {
        storefleet_sync_staff_dokan_context(
            get_current_user_id()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Render Staff Shell
    |--------------------------------------------------------------------------
    */

    return
        storefleet_render_dokan_staff_dashboard_shell(
            $staff
        );
}


/*
|--------------------------------------------------------------------------
| Get Active Staff Branch
|--------------------------------------------------------------------------
*/

function storefleet_get_dokan_staff_shell_branch_id()
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
| Staff Has Access To At Least One React Module
|--------------------------------------------------------------------------
|
| PHP cannot see browser hash fragments.
|
| Browser:
|
| /dashboard/new/#products/
|
| PHP:
|
| /dashboard/new/
|
| Therefore entrance to the Dokan React shell is allowed when the staff
| member has at least one StoreFleet React module available on the selected
| branch.
|
| Fine-grained React route authorization is handled separately.
|
*/

function storefleet_staff_can_access_dokan_react_dashboard()
{
    /*
    |--------------------------------------------------------------------------
    | Required Functions
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_dokan_staff_routes'
        )
        ||
        !function_exists(
            'storefleet_staff_can_view_dokan_route'
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branch
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_get_dokan_staff_shell_branch_id();

    if (!$branch_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Check React Routes
    |--------------------------------------------------------------------------
    */

    $routes =
        storefleet_get_dokan_staff_routes();

    foreach (
        $routes as $route
    ) {
        $react_route =
            sanitize_text_field(
                $route['react_route']
                ?? ''
            );

        if ($react_route === '') {
            continue;
        }

        if (
            storefleet_staff_can_view_dokan_route(
                $route,
                $branch_id
            )
        ) {
            return true;
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Render Dokan React Staff Dashboard
|--------------------------------------------------------------------------
|
| This renders the SAME modern Dokan React dashboard template used by the
| merchant:
|
| dashboard/new-dashboard.php
|
| The actual React module is selected by the browser hash:
|
| #products/
| #orders
| etc.
|
*/

function storefleet_render_dokan_react_staff_dashboard(
    $staff
) {
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
        return
            storefleet_render_dokan_staff_shell_error(
                'Your StoreFleet staff account is not active.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Active Branch Required
    |--------------------------------------------------------------------------
    */

    $branch_id =
        storefleet_get_dokan_staff_shell_branch_id();

    if (!$branch_id) {
        return
            storefleet_render_dokan_staff_shell_error(
                'No active StoreFleet branch is available.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | React Module Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_access_dokan_react_dashboard()
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'You are not authorized to access this operational module for the selected branch.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Global Branch Selector
    |--------------------------------------------------------------------------
    |
    | new-dashboard.php fires:
    |
    | dokan_dashboard_content_inside_before
    |
    | The selector therefore appears above the modern Dokan React content.
    |
    */

    $branch_selector_added =
        false;

    if (
        function_exists(
            'storefleet_render_dokan_staff_branch_selector'
        )
    ) {
        add_action(
            'dokan_dashboard_content_inside_before',
            'storefleet_render_dokan_staff_branch_selector',
            1
        );

        $branch_selector_added =
            true;
    }


    /*
    |--------------------------------------------------------------------------
    | Render Modern Dokan Dashboard
    |--------------------------------------------------------------------------
    */

    ob_start();

    dokan_get_template_part(
        'dashboard/new-dashboard'
    );

    $output =
        ob_get_clean();


    /*
    |--------------------------------------------------------------------------
    | Remove Temporary Hook
    |--------------------------------------------------------------------------
    */

    if ($branch_selector_added) {
        remove_action(
            'dokan_dashboard_content_inside_before',
            'storefleet_render_dokan_staff_branch_selector',
            1
        );
    }

    return
        $output;
}


/*
|--------------------------------------------------------------------------
| Render Dokan Staff Operational Shell
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_dashboard_shell(
    $staff
) {
    /*
    |--------------------------------------------------------------------------
    | Route Definitions Required
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_dokan_staff_routes'
        )
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'StoreFleet staff operational routes are unavailable.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Active Branch
    |--------------------------------------------------------------------------
    |
    | Staff no longer have account-level Dashboard or Branches pages.
    |
    | Every remaining staff route is operational and requires a selected
    | branch.
    |
    */

    $branch_id =
        storefleet_get_dokan_staff_shell_branch_id();

    if (!$branch_id) {
        return
            storefleet_render_dokan_staff_shell_error(
                'No active StoreFleet branch is available.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan React Dashboard
    |--------------------------------------------------------------------------
    |
    | Handle /dashboard/new/ before normal PHP route resolution.
    |
    */

    if (
        function_exists(
            'storefleet_is_dokan_react_dashboard_request'
        )
        &&
        storefleet_is_dokan_react_dashboard_request()
    ) {
        return
            storefleet_render_dokan_react_staff_dashboard(
                $staff
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    $routes =
        storefleet_get_dokan_staff_routes();


    /*
    |--------------------------------------------------------------------------
    | Detect Native Dokan PHP Route
    |--------------------------------------------------------------------------
    */

    $native_context =
        null;

    if (
        function_exists(
            'storefleet_get_current_native_dokan_staff_route'
        )
    ) {
        $native_context =
            storefleet_get_current_native_dokan_staff_route();
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Requested Route
    |--------------------------------------------------------------------------
    */

    $route_key =
        '';

    $native_route =
        '';

    if (
        is_array(
            $native_context
        )
        &&
        !empty(
            $native_context['route_key']
        )
    ) {
        $route_key =
            sanitize_key(
                $native_context['route_key']
            );

        $native_route =
            sanitize_key(
                $native_context['native_route']
                ?? ''
            );
    } elseif (
        function_exists(
            'storefleet_get_current_dokan_staff_route'
        )
    ) {
        $route_key =
            storefleet_get_current_dokan_staff_route();
    }


    /*
    |--------------------------------------------------------------------------
    | No Operational Route Selected
    |--------------------------------------------------------------------------
    |
    | We intentionally DO NOT fall back to storefleet-home.
    |
    | The navigation layer redirects /dashboard/ to the employee's first
    | authorized operational module before shortcode rendering.
    |
    */

    if ($route_key === '') {
        return
            storefleet_render_dokan_staff_shell_error(
                'No StoreFleet operational module was selected.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Valid Route
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $routes[$route_key]
        )
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'The requested StoreFleet operational module could not be found.'
            );
    }

    $route =
        $routes[$route_key];


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Permission
    |--------------------------------------------------------------------------
    */

    $permission =
        sanitize_text_field(
            $route['permission']
            ?? ''
        );

    if ($permission === '') {
        return
            storefleet_render_dokan_staff_shell_error(
                'The requested operational module has no StoreFleet permission mapping.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Scope
    |--------------------------------------------------------------------------
    */

    $branch_scoped =
        !empty(
            $route['branch_scoped']
        );


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Authorization
    |--------------------------------------------------------------------------
    */

    if ($branch_scoped) {
        /*
        |--------------------------------------------------------------------------
        | Permission + Selected Branch
        |--------------------------------------------------------------------------
        */

        if (
            !function_exists(
                'storefleet_current_staff_can'
            )
            ||
            !storefleet_current_staff_can(
                $permission,
                $branch_id
            )
        ) {
            return
                storefleet_render_dokan_staff_shell_error(
                    'You are not authorized to access this module for the selected branch.'
                );
        }
    } else {
        /*
        |--------------------------------------------------------------------------
        | Account-Level Compatibility
        |--------------------------------------------------------------------------
        |
        | Retained for future non-operational routes if needed.
        |
        */

        if (
            !function_exists(
                'storefleet_current_staff_can'
            )
            ||
            !storefleet_current_staff_can(
                $permission
            )
        ) {
            return
                storefleet_render_dokan_staff_shell_error(
                    'You are not authorized to access this StoreFleet module.'
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Native Dokan PHP Capability Check
    |--------------------------------------------------------------------------
    */

    if ($native_route !== '') {
        $required_dokan_capability =
            storefleet_get_native_dokan_staff_route_capability(
                $native_route
            );

        if (
            $required_dokan_capability !== ''
            &&
            !current_user_can(
                $required_dokan_capability
            )
        ) {
            return
                storefleet_render_dokan_staff_shell_error(
                    'The required Dokan permission is not available for this staff account.'
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Native Dokan PHP Rendering
    |--------------------------------------------------------------------------
    |
    | Native Dokan templates contain their own dashboard wrapper.
    |
    | Never nest them inside the StoreFleet wrapper.
    |
    */

    if ($native_route !== '') {
        /*
        |--------------------------------------------------------------------------
        | Temporary Branch Selector Hook
        |--------------------------------------------------------------------------
        */

        $branch_selector_added =
            false;

        if (
            $branch_scoped
            &&
            function_exists(
                'storefleet_render_dokan_staff_branch_selector'
            )
        ) {
            add_action(
                'dokan_dashboard_content_inside_before',
                'storefleet_render_dokan_staff_branch_selector',
                1
            );

            $branch_selector_added =
                true;
        }


        /*
        |--------------------------------------------------------------------------
        | Render Native Dokan Page
        |--------------------------------------------------------------------------
        */

        ob_start();

        storefleet_render_native_dokan_staff_content(
            $native_route,
            $staff
        );

        $native_output =
            ob_get_clean();


        /*
        |--------------------------------------------------------------------------
        | Remove Temporary Hook
        |--------------------------------------------------------------------------
        */

        if ($branch_selector_added) {
            remove_action(
                'dokan_dashboard_content_inside_before',
                'storefleet_render_dokan_staff_branch_selector',
                1
            );
        }

        return
            $native_output;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Content Renderer Required
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_render_dokan_staff_content'
        )
    ) {
        return
            storefleet_render_dokan_staff_shell_error(
                'StoreFleet operational content is unavailable.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Start StoreFleet Custom Route Output
    |--------------------------------------------------------------------------
    */

    ob_start();


    /*
    |--------------------------------------------------------------------------
    | Dokan Wrapper Start
    |--------------------------------------------------------------------------
    */

    do_action(
        'dokan_dashboard_wrap_start'
    );

    ?>

    <div
        class="
            dokan-dashboard-wrap
            storefleet-staff-dokan-dashboard
        "
    >

        <?php

        /*
        |--------------------------------------------------------------------------
        | Staff Navigation
        |--------------------------------------------------------------------------
        |
        | dokan-staff-navigation.php replaces Dokan's merchant navigation
        | with the authorized StoreFleet operational menu.
        |
        */

        do_action(
            'dokan_dashboard_content_before'
        );

        ?>


        <div
            class="
                dokan-dashboard-content
                storefleet-staff-dashboard-content
            "
        >

            <?php

            /*
            |--------------------------------------------------------------------------
            | Before Operational Content
            |--------------------------------------------------------------------------
            */

            do_action(
                'dokan_dashboard_content_inside_before'
            );


            /*
            |--------------------------------------------------------------------------
            | Global Branch Selector
            |--------------------------------------------------------------------------
            |
            | This replaces the old Branches sidebar page for staff.
            |
            | One authorized branch:
            |
            |     Current Branch: Makati
            |
            | Multiple authorized branches:
            |
            |     Current Branch: [ Makati ▼ ]
            |
            */

            if (
                function_exists(
                    'storefleet_render_dokan_staff_branch_selector'
                )
            ) {
                storefleet_render_dokan_staff_branch_selector();
            }


            /*
            |--------------------------------------------------------------------------
            | Operational Route Content
            |--------------------------------------------------------------------------
            */

            storefleet_render_dokan_staff_content(
                $route_key,
                $route
            );


            /*
            |--------------------------------------------------------------------------
            | After Operational Content
            |--------------------------------------------------------------------------
            */

            do_action(
                'dokan_dashboard_content_inside_after'
            );

            ?>

        </div>


        <?php

        /*
        |--------------------------------------------------------------------------
        | After Dashboard Content
        |--------------------------------------------------------------------------
        */

        do_action(
            'dokan_dashboard_content_after'
        );

        ?>

    </div>

    <?php

    do_action(
        'dokan_dashboard_wrap_end'
    );


    /*
    |--------------------------------------------------------------------------
    | Return StoreFleet Shell
    |--------------------------------------------------------------------------
    */

    return
        ob_get_clean();
}


/*
|--------------------------------------------------------------------------
| Native Dokan PHP Route Capability
|--------------------------------------------------------------------------
|
| Kept intentionally narrow.
|
| NEVER expose:
|
| - Withdraw
| - Settings
| - payout configuration
| - bank details
| - merchant financial controls
|
*/

function storefleet_get_native_dokan_staff_route_capability(
    $native_route
) {
    $native_route =
        sanitize_key(
            $native_route
        );

    switch ($native_route) {
        case 'products':
            return
                'dokan_view_product_menu';

        case 'orders':
            return
                'dokan_view_order_menu';

        default:
            return '';
    }
}


/*
|--------------------------------------------------------------------------
| Render Native Dokan PHP Content
|--------------------------------------------------------------------------
|
| Products now uses:
|
| /dashboard/new/#products/
|
| This renderer remains available only for specifically approved Dokan PHP
| routes or compatibility fallbacks.
|
*/

function storefleet_render_native_dokan_staff_content(
    $native_route,
    $staff
) {
    $native_route =
        sanitize_key(
            $native_route
        );


    /*
    |--------------------------------------------------------------------------
    | Products Legacy Fallback
    |--------------------------------------------------------------------------
    */

    if (
        $native_route ===
        'products'
    ) {
        dokan_get_template_part(
            'products/products'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Orders Legacy Fallback
    |--------------------------------------------------------------------------
    */

    if (
        $native_route ===
        'orders'
    ) {
        dokan_get_template_part(
            'orders/orders'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Unknown Native Route
    |--------------------------------------------------------------------------
    */

    ?>

    <div
        style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
        "
    >

        <h2>
            StoreFleet
        </h2>

        <p>
            The requested Dokan operational module
            is not configured.
        </p>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Staff Shell Error
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_shell_error(
    $message
) {
    $message =
        sanitize_text_field(
            $message
        );

    ob_start();

    ?>

    <div
        class="
            dokan-dashboard-wrap
            storefleet-staff-dokan-dashboard
        "
    >

        <?php

        do_action(
            'dokan_dashboard_content_before'
        );

        ?>

        <div
            class="
                dokan-dashboard-content
                storefleet-staff-dashboard-content
            "
        >

            <?php

            /*
            |--------------------------------------------------------------------------
            | Show Branch Selector When Possible
            |--------------------------------------------------------------------------
            |
            | This is useful if access changes after switching branches.
            |
            */

            if (
                function_exists(
                    'storefleet_render_dokan_staff_branch_selector'
                )
                &&
                storefleet_get_dokan_staff_shell_branch_id()
            ) {
                storefleet_render_dokan_staff_branch_selector();
            }

            ?>

            <div
                style="
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    padding: 24px;
                "
            >

                <h2>
                    StoreFleet
                </h2>

                <p>
                    <?php
                    echo esc_html(
                        $message
                    );
                    ?>
                </p>

            </div>

        </div>

        <?php

        do_action(
            'dokan_dashboard_content_after'
        );

        ?>

    </div>

    <?php

    return
        ob_get_clean();
}