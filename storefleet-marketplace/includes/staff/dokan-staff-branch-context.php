<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Staff Branch Context
|--------------------------------------------------------------------------
|
| Feature #30
|
| Branch selection is now the global operational context for staff.
|
| Staff do NOT have a standalone Branches sidebar page.
|
| This file is responsible for:
|
| - resolving all branches accessible to the staff member
| - supporting multiple roles with different branch assignments
| - keeping one active branch
| - validating branch switches server-side
| - storing the selected branch in WordPress user meta
| - rendering the global branch selector
| - preserving the current operational module after switching branches
|
| IMPORTANT:
|
| Selecting a branch does NOT grant module permissions.
|
| Every operational authorization must still use:
|
| storefleet_current_staff_can(
|     'permission.name',
|     $branch_id
| );
|
| This preserves same-role permission + branch authorization.
|
*/


/*
|--------------------------------------------------------------------------
| Active Branch User Meta Key
|--------------------------------------------------------------------------
*/

function storefleet_staff_active_branch_meta_key()
{
    return
        'storefleet_active_branch_id';
}


/*
|--------------------------------------------------------------------------
| Get Active Accessible Branches For Staff
|--------------------------------------------------------------------------
|
| storefleet_get_staff_branches() returns the union of branches available
| through all StoreFleet roles assigned to the employee.
|
| Example:
|
| Cashier
|   Makati
|   BGC
|
| Inventory Staff
|   Makati
|
| Selector:
|
|   Makati
|   BGC
|
| But inventory.manage + BGC can still be denied because the Inventory Staff
| role itself does not have BGC.
|
*/

function storefleet_get_staff_context_branches(
    $staff_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    if (
        !$staff_id
        ||
        !function_exists(
            'storefleet_get_staff'
        )
        ||
        !function_exists(
            'storefleet_get_staff_branches'
        )
    ) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Record
    |--------------------------------------------------------------------------
    */

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Union Of Assigned Branches
    |--------------------------------------------------------------------------
    */

    $branches =
        storefleet_get_staff_branches(
            $staff_id
        );

    if (
        empty(
            $branches
        )
    ) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize + Validate
    |--------------------------------------------------------------------------
    */

    $valid_branches =
        [];

    foreach (
        $branches as $branch
    ) {
        if (!$branch) {
            continue;
        }

        $branch_id =
            absint(
                $branch->id
                ?? 0
            );

        if (!$branch_id) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Active Branches Only
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $branch->is_active
            )
            &&
            (int) $branch->is_active !== 1
        ) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Merchant Isolation
        |--------------------------------------------------------------------------
        */

        $merchant_id =
            absint(
                $staff->merchant_id
                ?? 0
            );

        if (
            $merchant_id
            &&
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


        /*
        |--------------------------------------------------------------------------
        | De-Duplicate By Branch ID
        |--------------------------------------------------------------------------
        */

        $valid_branches[
            $branch_id
        ] =
            $branch;
    }


    /*
    |--------------------------------------------------------------------------
    | Stable Alphabetical Ordering
    |--------------------------------------------------------------------------
    */

    uasort(
        $valid_branches,
        function (
            $branch_a,
            $branch_b
        ) {
            $name_a =
                strtolower(
                    (string) (
                        $branch_a->name
                        ?? ''
                    )
                );

            $name_b =
                strtolower(
                    (string) (
                        $branch_b->name
                        ?? ''
                    )
                );

            return
                strcmp(
                    $name_a,
                    $name_b
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
| Current Staff Context Branches
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_context_branches()
{
    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return [];
    }

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return [];
    }

    return
        storefleet_get_staff_context_branches(
            $staff->id
        );
}


/*
|--------------------------------------------------------------------------
| Staff Can Select Branch
|--------------------------------------------------------------------------
|
| This checks membership in the staff member's branch union.
|
| It deliberately does NOT mean every permission is valid for the branch.
|
*/

function storefleet_staff_can_select_context_branch(
    $staff_id,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$staff_id
        ||
        !$branch_id
    ) {
        return false;
    }

    $branches =
        storefleet_get_staff_context_branches(
            $staff_id
        );

    foreach (
        $branches as $branch
    ) {
        if (
            absint(
                $branch->id
                ?? 0
            ) ===
            $branch_id
        ) {
            return true;
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Find Accessible Branch Object
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_context_branch(
    $staff_id,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$staff_id
        ||
        !$branch_id
    ) {
        return null;
    }

    $branches =
        storefleet_get_staff_context_branches(
            $staff_id
        );

    foreach (
        $branches as $branch
    ) {
        if (
            absint(
                $branch->id
                ?? 0
            ) ===
            $branch_id
        ) {
            return
                $branch;
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Get Current Staff Active Branch ID
|--------------------------------------------------------------------------
|
| Resolution:
|
| 1. Read saved branch from WordPress user meta.
| 2. Verify branch is still accessible.
| 3. If missing/invalid, choose first accessible active branch.
| 4. Save corrected branch back to user meta.
|
*/

function storefleet_get_current_staff_branch_id()
{
    if (!is_user_logged_in()) {
        return 0;
    }

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return 0;
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | IDs
    |--------------------------------------------------------------------------
    */

    $staff_id =
        absint(
            $staff->id
            ?? 0
        );

    $user_id =
        absint(
            $staff->user_id
            ?? 0
        );

    if (
        !$staff_id
        ||
        !$user_id
    ) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Accessible Branches
    |--------------------------------------------------------------------------
    */

    $branches =
        storefleet_get_staff_context_branches(
            $staff_id
        );

    if (
        empty(
            $branches
        )
    ) {
        delete_user_meta(
            $user_id,
            storefleet_staff_active_branch_meta_key()
        );

        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Saved Active Branch
    |--------------------------------------------------------------------------
    */

    $saved_branch_id =
        absint(
            get_user_meta(
                $user_id,
                storefleet_staff_active_branch_meta_key(),
                true
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Saved Branch Still Available
    |--------------------------------------------------------------------------
    */

    if (
        $saved_branch_id
        &&
        storefleet_staff_can_select_context_branch(
            $staff_id,
            $saved_branch_id
        )
    ) {
        return
            $saved_branch_id;
    }


    /*
    |--------------------------------------------------------------------------
    | Default To First Available Branch
    |--------------------------------------------------------------------------
    */

    $first_branch =
        reset(
            $branches
        );

    $default_branch_id =
        absint(
            $first_branch->id
            ?? 0
        );

    if (!$default_branch_id) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Persist Default
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        storefleet_staff_active_branch_meta_key(),
        $default_branch_id
    );

    return
        $default_branch_id;
}


/*
|--------------------------------------------------------------------------
| Get Current Staff Active Branch Object
|--------------------------------------------------------------------------
*/

function storefleet_get_current_staff_branch()
{
    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return null;
    }

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {
        return null;
    }

    $branch_id =
        storefleet_get_current_staff_branch_id();

    if (!$branch_id) {
        return null;
    }

    return
        storefleet_get_staff_context_branch(
            $staff->id,
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Set Staff Active Branch
|--------------------------------------------------------------------------
*/

function storefleet_set_staff_active_branch(
    $staff_id,
    $branch_id
) {
    $staff_id =
        absint(
            $staff_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$staff_id
        ||
        !$branch_id
    ) {
        return false;
    }

    if (
        !function_exists(
            'storefleet_get_staff'
        )
    ) {
        return false;
    }

    $staff =
        storefleet_get_staff(
            $staff_id
        );

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Must Be In Staff Branch Union
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_staff_can_select_context_branch(
            $staff_id,
            $branch_id
        )
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress User
    |--------------------------------------------------------------------------
    */

    $user_id =
        absint(
            $staff->user_id
            ?? 0
        );

    if (!$user_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Save Branch Preference
    |--------------------------------------------------------------------------
    */

    update_user_meta(
        $user_id,
        storefleet_staff_active_branch_meta_key(),
        $branch_id
    );

    return true;
}


/*
|--------------------------------------------------------------------------
| Get Current Server-Side Operational Route
|--------------------------------------------------------------------------
|
| For normal StoreFleet PHP routes PHP already knows the route.
|
| For Dokan React routes PHP cannot see the hash fragment. The selector's
| JavaScript adds the React route key to the POST request instead.
|
*/

function storefleet_get_current_staff_branch_context_route_key()
{
    /*
    |--------------------------------------------------------------------------
    | StoreFleet PHP Route
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_get_current_dokan_staff_route'
        )
    ) {
        $route_key =
            sanitize_key(
                storefleet_get_current_dokan_staff_route()
            );

        if ($route_key !== '') {
            return
                $route_key;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Native Dokan Route
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_get_current_native_dokan_staff_route'
        )
    ) {
        $native =
            storefleet_get_current_native_dokan_staff_route();

        if (
            is_array(
                $native
            )
            &&
            !empty(
                $native['route_key']
            )
        ) {
            return
                sanitize_key(
                    $native['route_key']
                );
        }
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Get React Route → StoreFleet Route Map
|--------------------------------------------------------------------------
|
| Used only by the branch-selector JavaScript.
|
| Example:
|
| products/
|
| becomes:
|
| products => storefleet-products
|
*/

function storefleet_get_staff_branch_context_react_route_map()
{
    if (
        !function_exists(
            'storefleet_get_dokan_staff_routes'
        )
    ) {
        return [];
    }

    $routes =
        storefleet_get_dokan_staff_routes();

    $map =
        [];

    foreach (
        $routes as
        $route_key => $route
    ) {
        $react_route =
            trim(
                sanitize_text_field(
                    $route['react_route']
                    ?? ''
                ),
                '/'
            );

        if ($react_route === '') {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | First React Path Segment
        |--------------------------------------------------------------------------
        |
        | products/
        |
        | products/123/edit
        |
        | both belong to the Products operational module.
        |
        */

        $segments =
            explode(
                '/',
                $react_route
            );

        $root =
            sanitize_key(
                $segments[0]
                ?? ''
            );

        if ($root === '') {
            continue;
        }

        $map[$root] =
            sanitize_key(
                $route_key
            );
    }

    return
        $map;
}


/*
|--------------------------------------------------------------------------
| Validate Operational Route On Selected Branch
|--------------------------------------------------------------------------
*/

function storefleet_staff_branch_context_route_is_allowed(
    $route_key,
    $branch_id
) {
    $route_key =
        sanitize_key(
            $route_key
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        $route_key === ''
        ||
        !$branch_id
        ||
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

    $routes =
        storefleet_get_dokan_staff_routes();

    if (
        !isset(
            $routes[$route_key]
        )
    ) {
        return false;
    }

    return
        storefleet_staff_can_view_dokan_route(
            $routes[$route_key],
            $branch_id
        );
}


/*
|--------------------------------------------------------------------------
| Branch Switch Redirect URL
|--------------------------------------------------------------------------
|
| Priority:
|
| 1. Preserve current operational module if it is allowed on the NEW branch.
| 2. Otherwise redirect to the first permitted operational module.
| 3. Final compatibility fallback: /dashboard/
|
| There is deliberately NO storefleet-home fallback anymore.
|
*/

function storefleet_get_staff_branch_switch_redirect_url(
    $preferred_route_key = '',
    $branch_id = 0
) {
    $preferred_route_key =
        sanitize_key(
            $preferred_route_key
        );

    $branch_id =
        absint(
            $branch_id
        );


    /*
    |--------------------------------------------------------------------------
    | Current Branch If None Supplied
    |--------------------------------------------------------------------------
    */

    if (!$branch_id) {
        $branch_id =
            storefleet_get_current_staff_branch_id();
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve Current Operational Module
    |--------------------------------------------------------------------------
    */

    if (
        $preferred_route_key !== ''
        &&
        storefleet_staff_branch_context_route_is_allowed(
            $preferred_route_key,
            $branch_id
        )
        &&
        function_exists(
            'storefleet_get_dokan_staff_routes'
        )
        &&
        function_exists(
            'storefleet_get_dokan_staff_route_url'
        )
    ) {
        $routes =
            storefleet_get_dokan_staff_routes();

        return
            storefleet_get_dokan_staff_route_url(
                $preferred_route_key,
                $routes[$preferred_route_key]
            );
    }


    /*
    |--------------------------------------------------------------------------
    | First Permitted Operational Module
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_get_first_dokan_staff_route_url'
        )
    ) {
        $first_url =
            storefleet_get_first_dokan_staff_route_url(
                $branch_id
            );

        if ($first_url !== '') {
            return
                $first_url;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Compatibility Fallback
    |--------------------------------------------------------------------------
    */

    return
        home_url(
            '/dashboard/'
        );
}


/*
|--------------------------------------------------------------------------
| Handle Branch Switch
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_handle_staff_branch_context_switch',
    5
);


function storefleet_handle_staff_branch_context_switch()
{
    /*
    |--------------------------------------------------------------------------
    | POST Only
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            $_SERVER['REQUEST_METHOD']
            ?? ''
        ) !==
        'POST'
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Correct StoreFleet Action
    |--------------------------------------------------------------------------
    */

    $action =
        isset(
            $_POST[
                'storefleet_staff_branch_action'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_staff_branch_action'
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


    /*
    |--------------------------------------------------------------------------
    | Logged-In StoreFleet Staff Required
    |--------------------------------------------------------------------------
    */

    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_is_staff_user'
        )
        ||
        !storefleet_is_staff_user()
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to switch StoreFleet branches.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Nonce
    |--------------------------------------------------------------------------
    */

    check_admin_referer(
        'storefleet_switch_staff_branch',
        'storefleet_staff_branch_nonce'
    );


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
        wp_die(
            esc_html__(
                'StoreFleet staff authorization is unavailable.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        wp_die(
            esc_html__(
                'Your StoreFleet staff account is not active.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Requested Branch
    |--------------------------------------------------------------------------
    */

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

    if (!$branch_id) {
        wp_die(
            esc_html__(
                'Please select a valid StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'Invalid Branch',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    400,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Preferred Operational Route
    |--------------------------------------------------------------------------
    |
    | This may come from:
    |
    | - current PHP route
    | - current native Dokan route
    | - React route detected in the browser
    |
    */

    $preferred_route_key =
        isset(
            $_POST[
                'storefleet_staff_return_route'
            ]
        )
            ? sanitize_key(
                wp_unslash(
                    $_POST[
                        'storefleet_staff_return_route'
                    ]
                )
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Server-Side Branch Validation + Save
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_set_staff_active_branch(
            $staff->id,
            $branch_id
        )
    ) {
        wp_die(
            esc_html__(
                'You are not authorized to access the selected StoreFleet branch.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'StoreFleet Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' =>
                    403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect Using NEW Branch Permissions
    |--------------------------------------------------------------------------
    */

    $redirect_url =
        storefleet_get_staff_branch_switch_redirect_url(
            $preferred_route_key,
            $branch_id
        );

    wp_safe_redirect(
        $redirect_url
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Render Branch Selector
|--------------------------------------------------------------------------
|
| One branch:
|
| Current Branch
| Makati
|
| Multiple branches:
|
| Current Branch
| [ Makati ▼ ]
|
| Selecting another branch immediately reloads the operational module using
| that branch as StoreFleet's authorization context.
|
*/

function storefleet_render_dokan_staff_branch_selector()
{
    /*
    |--------------------------------------------------------------------------
    | Logged In
    |--------------------------------------------------------------------------
    */

    if (
        !is_user_logged_in()
        ||
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Staff
    |--------------------------------------------------------------------------
    */

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
    | Accessible Branch Union
    |--------------------------------------------------------------------------
    */

    $branches =
        storefleet_get_staff_context_branches(
            $staff->id
        );


    /*
    |--------------------------------------------------------------------------
    | Current Operational Route
    |--------------------------------------------------------------------------
    */

    $current_route_key =
        storefleet_get_current_staff_branch_context_route_key();


    /*
    |--------------------------------------------------------------------------
    | React Route Map
    |--------------------------------------------------------------------------
    */

    $react_route_map =
        storefleet_get_staff_branch_context_react_route_map();

    ?>

    <div
        class="
            storefleet-staff-branch-context
        "
        style="
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
        "
    >

        <div>

            <strong>
                Current Branch
            </strong>

            <div
                style="
                    margin-top: 3px;
                    color: #6b7280;
                    font-size: 13px;
                "
            >
                StoreFleet operational context
            </div>

        </div>


        <?php if (empty($branches)) : ?>

            <div
                style="
                    color: #b91c1c;
                    font-weight: 600;
                "
            >
                No active branch access
            </div>


        <?php elseif (count($branches) === 1) : ?>

            <?php

            $branch =
                reset(
                    $branches
                );

            ?>

            <div>

                <strong>
                    <?php
                    echo esc_html(
                        $branch->name
                        ?? 'Branch'
                    );
                    ?>
                </strong>

            </div>


        <?php else : ?>

            <?php

            $current_branch_id =
                storefleet_get_current_staff_branch_id();

            ?>

            <form
                method="post"
                action=""
                class="
                    storefleet-staff-branch-selector-form
                "
                style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin: 0;
                "
            >

                <?php

                wp_nonce_field(
                    'storefleet_switch_staff_branch',
                    'storefleet_staff_branch_nonce'
                );

                ?>


                <input
                    type="hidden"
                    name="storefleet_staff_branch_action"
                    value="switch_branch"
                >


                <input
                    type="hidden"
                    name="storefleet_staff_return_route"
                    class="
                        storefleet-staff-return-route
                    "
                    value="<?php
                    echo esc_attr(
                        $current_route_key
                    );
                    ?>"
                >


                <select
                    name="storefleet_branch_id"
                    class="
                        storefleet-staff-branch-select
                    "
                    style="
                        min-width: 220px;
                        padding: 8px 34px 8px 10px;
                        border: 1px solid #d1d5db;
                        border-radius: 6px;
                        background: #ffffff;
                    "
                >

                    <?php
                    foreach (
                        $branches as $branch
                    ) :
                    ?>

                        <?php

                        $branch_id =
                            absint(
                                $branch->id
                                ?? 0
                            );

                        if (!$branch_id) {
                            continue;
                        }

                        ?>

                        <option
                            value="<?php
                            echo esc_attr(
                                $branch_id
                            );
                            ?>"
                            <?php
                            selected(
                                $current_branch_id,
                                $branch_id
                            );
                            ?>
                        >

                            <?php
                            echo esc_html(
                                $branch->name
                                ?? (
                                    'Branch #' .
                                    $branch_id
                                )
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </form>


            <script>
            (function () {
                'use strict';

                var forms =
                    document.querySelectorAll(
                        '.storefleet-staff-branch-selector-form'
                    );

                if (!forms.length) {
                    return;
                }

                var reactRouteMap =
                    <?php
                    echo wp_json_encode(
                        $react_route_map
                    );
                    ?>;


                forms.forEach(
                    function (form) {
                        var select =
                            form.querySelector(
                                '.storefleet-staff-branch-select'
                            );

                        var returnRoute =
                            form.querySelector(
                                '.storefleet-staff-return-route'
                            );

                        if (!select) {
                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Determine Current React Module
                        |--------------------------------------------------------------------------
                        |
                        | PHP cannot see:
                        |
                        | #products/
                        |
                        | so the browser supplies the corresponding StoreFleet
                        | route key when the branch changes.
                        |
                        */

                        if (
                            returnRoute
                            &&
                            window.location.hash
                            &&
                            reactRouteMap
                        ) {
                            var hash =
                                window.location.hash
                                    .replace(/^#\/?/, '')
                                    .replace(/^\/+/, '');

                            var root =
                                hash
                                    .split('/')[0];

                            if (
                                root
                                &&
                                reactRouteMap[root]
                            ) {
                                returnRoute.value =
                                    reactRouteMap[root];
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Switch Branch
                        |--------------------------------------------------------------------------
                        */

                        select.addEventListener(
                            'change',
                            function () {
                                form.submit();
                            }
                        );
                    }
                );
            })();
            </script>

        <?php endif; ?>

    </div>

    <?php
}