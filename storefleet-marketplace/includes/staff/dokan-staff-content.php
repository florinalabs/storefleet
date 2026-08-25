<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet Dokan Staff Content
|--------------------------------------------------------------------------
|
| Feature #30
|
| Responsible for:
|
| - rendering StoreFleet staff modules inside Dokan
| - server-side permission enforcement
| - active branch authorization
| - role-specific branch authorization
|
| IMPORTANT:
|
| Dashboard home uses global dashboard.view.
|
| Operational modules use:
|
| permission + CURRENT BRANCH
|
| The SAME StoreFleet role must grant both.
|
*/


/*
|--------------------------------------------------------------------------
| Load Staff Content
|--------------------------------------------------------------------------
*/

add_action(
    'dokan_load_custom_template',
    'storefleet_load_dokan_staff_content',
    1
);


function storefleet_load_dokan_staff_content(
    $query_vars
) {
    if (
        !function_exists(
            'storefleet_dokan_navigation_is_staff'
        )
        ||
        !storefleet_dokan_navigation_is_staff()
    ) {
        return;
    }

    if (
        empty($query_vars)
        ||
        !is_array($query_vars)
    ) {
        return;
    }

    if (
        !function_exists(
            'storefleet_get_dokan_staff_routes'
        )
    ) {
        return;
    }

    $routes =
        storefleet_get_dokan_staff_routes();

    foreach (
        $routes as
        $route_key => $route
    ) {
        if (
            !array_key_exists(
                $route_key,
                $query_vars
            )
        ) {
            continue;
        }

        storefleet_render_dokan_staff_content(
            $route_key,
            $route
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Route Uses Branch Context
|--------------------------------------------------------------------------
|
| Dashboard home is account-level.
|
| Every operational module is branch-aware.
|
*/

function storefleet_dokan_staff_route_requires_branch(
    $route_key
) {
    return
        $route_key !==
        'storefleet-home';
}


/*
|--------------------------------------------------------------------------
| Resolve Active Branch
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_get_active_branch()
{
    if (
        !function_exists(
            'storefleet_get_current_staff_branch_id'
        )
        ||
        !function_exists(
            'storefleet_get_current_staff_branch'
        )
    ) {
        return null;
    }

    $branch_id =
        absint(
            storefleet_get_current_staff_branch_id()
        );

    if (!$branch_id) {
        return null;
    }

    $branch =
        storefleet_get_current_staff_branch();

    if (!$branch) {
        return null;
    }

    return $branch;
}


/*
|--------------------------------------------------------------------------
| Render Protected Route
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_content(
    $route_key,
    array $route
) {
    /*
    |--------------------------------------------------------------------------
    | Permission
    |--------------------------------------------------------------------------
    */

    $permission =
        sanitize_text_field(
            $route['permission'] ?? ''
        );

    if ($permission === '') {
        storefleet_dokan_staff_content_denied();
    }


    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_current_staff'
        )
    ) {
        storefleet_dokan_staff_content_denied();
    }

    $staff =
        storefleet_get_current_staff();

    if (
        !$staff
        ||
        (int) $staff->is_active !== 1
    ) {
        storefleet_dokan_staff_content_denied();
    }


    /*
    |--------------------------------------------------------------------------
    | Dashboard Home Permission
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_dokan_staff_route_requires_branch(
            $route_key
        )
    ) {
        if (
            !storefleet_current_staff_can(
                $permission
            )
        ) {
            storefleet_dokan_staff_content_denied();
        }

        storefleet_render_dokan_staff_home(
            $staff
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Branch Required
    |--------------------------------------------------------------------------
    */

    $branch =
        storefleet_dokan_staff_get_active_branch();

    if (!$branch) {
        storefleet_dokan_staff_no_branch();
    }

    $branch_id =
        absint(
            $branch->id
        );


    /*
    |--------------------------------------------------------------------------
    | Permission + Branch Must Come From Same Role
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Cashier:
    |   Makati + BGC
    |
    | Inventory Staff:
    |   Makati
    |
    | Current branch:
    |   BGC
    |
    | inventory.manage:
    |   DENIED
    |
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
        storefleet_dokan_staff_branch_denied(
            $branch
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */

    switch ($route_key) {

        case 'storefleet-pos':

            storefleet_render_dokan_staff_placeholder(
                'POS',
                'Process StoreFleet POS transactions for the current branch.',
                'pos.use',
                $branch
            );

            break;


        case 'storefleet-orders':

            storefleet_render_dokan_staff_placeholder(
                'Orders',
                'View and process marketplace orders for the current branch.',
                'orders.view',
                $branch
            );

            break;


        case 'storefleet-inventory':

            storefleet_render_dokan_staff_inventory(
                $staff,
                $branch
            );

            break;


        case 'storefleet-products':

            storefleet_render_dokan_staff_placeholder(
                'Products',
                'View merchant products required for operations at the current branch.',
                'products.view',
                $branch
            );

            break;


        case 'storefleet-branches':

            storefleet_render_dokan_staff_branches(
                $staff,
                $branch
            );

            break;


        case 'storefleet-delivery':

            storefleet_render_dokan_staff_placeholder(
                'Delivery',
                'View delivery operations permitted for the current branch.',
                'delivery.view',
                $branch
            );

            break;


        case 'storefleet-team':

            storefleet_render_dokan_staff_placeholder(
                'Staff',
                'View staff information permitted for the current branch.',
                'staff.view',
                $branch
            );

            break;


        default:

            storefleet_dokan_staff_content_not_found();
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard Home
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_home(
    $staff
) {
    $staff_id =
        absint(
            $staff->id
        );

    $roles =
        storefleet_get_staff_roles_for_staff(
            $staff_id
        );

    $branches =
        storefleet_get_staff_branches(
            $staff_id
        );

    $current_branch =
        storefleet_dokan_staff_get_active_branch();

    $current_branch_id =
        $current_branch
            ? absint(
                $current_branch->id
            )
            : 0;

    ?>

    <div class="storefleet-dashboard-page">

        <div class="storefleet-page-header">

            <h1>
                Staff Dashboard
            </h1>

            <p>
                Welcome,

                <strong>
                    <?php
                    echo esc_html(
                        $staff->display_name
                    );
                    ?>
                </strong>.
            </p>

        </div>


        <div
            style="
                display: grid;
                grid-template-columns:
                    repeat(
                        auto-fit,
                        minmax(220px, 1fr)
                    );
                gap: 16px;
                margin-bottom: 24px;
            "
        >

            <!-- Account -->

            <div class="storefleet-card">

                <div class="storefleet-card-body">

                    <h3>
                        Account
                    </h3>

                    <p>
                        <strong>
                            <?php
                            echo esc_html(
                                $staff->display_name
                            );
                            ?>
                        </strong>
                    </p>

                    <p>
                        <?php
                        echo esc_html(
                            $staff->user_email
                        );
                        ?>
                    </p>

                    <p>
                        Status:
                        <strong>
                            Active
                        </strong>
                    </p>

                </div>

            </div>


            <!-- Roles -->

            <div class="storefleet-card">

                <div class="storefleet-card-body">

                    <h3>
                        Your Roles
                    </h3>

                    <?php if (empty($roles)) : ?>

                        <p>
                            No roles assigned.
                        </p>

                    <?php else : ?>

                        <ul>

                            <?php
                            foreach (
                                $roles as $role_key
                            ) :
                            ?>

                                <li>

                                    <?php
                                    echo esc_html(
                                        storefleet_get_staff_role_label(
                                            $role_key
                                        )
                                    );
                                    ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Branches -->

            <div class="storefleet-card">

                <div class="storefleet-card-body">

                    <h3>
                        Branch Access
                    </h3>

                    <?php if (empty($branches)) : ?>

                        <p>
                            No branches assigned.
                        </p>

                    <?php else : ?>

                        <ul>

                            <?php
                            foreach (
                                $branches as $branch
                            ) :
                            ?>

                                <li>

                                    <?php
                                    echo esc_html(
                                        $branch->name
                                    );
                                    ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Available Tools -->

        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    Available Tools
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php if (!$current_branch_id) : ?>

                    <p>
                        You currently have no active
                        StoreFleet branch context.
                    </p>

                <?php else : ?>

                    <p
                        style="
                            margin-top: 0;
                            color: #6b7280;
                        "
                    >
                        Showing tools available for

                        <strong>
                            <?php
                            echo esc_html(
                                $current_branch->name
                            );
                            ?>
                        </strong>.
                    </p>


                    <div
                        style="
                            display: grid;
                            grid-template-columns:
                                repeat(
                                    auto-fit,
                                    minmax(190px, 1fr)
                                );
                            gap: 14px;
                        "
                    >

                        <?php

                        $tools = [

                            'pos.use' => [
                                'POS',
                                'storefleet-pos',
                            ],

                            'orders.view' => [
                                'Orders',
                                'storefleet-orders',
                            ],

                            'inventory.view' => [
                                'Inventory',
                                'storefleet-inventory',
                            ],

                            'products.view' => [
                                'Products',
                                'storefleet-products',
                            ],

                            'branches.view' => [
                                'Branches',
                                'storefleet-branches',
                            ],

                            'delivery.view' => [
                                'Delivery',
                                'storefleet-delivery',
                            ],

                            'staff.view' => [
                                'Staff',
                                'storefleet-team',
                            ],
                        ];


                        foreach (
                            $tools as
                            $tool_permission => $tool
                        ) {
                            if (
                                !storefleet_current_staff_can(
                                    $tool_permission,
                                    $current_branch_id
                                )
                            ) {
                                continue;
                            }

                            storefleet_render_dokan_staff_tool_card(
                                $tool[0],
                                $tool[1]
                            );
                        }

                        ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Tool Card
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_tool_card(
    $title,
    $route
) {
    $url =
        function_exists(
            'dokan_get_navigation_url'
        )
            ? dokan_get_navigation_url(
                $route
            )
            : home_url(
                '/dashboard/' .
                $route .
                '/'
            );

    ?>

    <a
        href="<?php
        echo esc_url(
            $url
        );
        ?>"
        style="
            display: block;
            padding: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            text-decoration: none;
            color: inherit;
        "
    >

        <strong>
            <?php
            echo esc_html(
                $title
            );
            ?>
        </strong>

    </a>

    <?php
}


/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
|
| Feature #30 does not implement inventory editing.
|
| This page confirms the current branch and branch-specific permission.
|
*/

function storefleet_render_dokan_staff_inventory(
    $staff,
    $branch
) {
    $branch_id =
        absint(
            $branch->id
        );

    $can_manage =
        storefleet_current_staff_can(
            'inventory.manage',
            $branch_id
        );

    ?>

    <div class="storefleet-dashboard-page">

        <div class="storefleet-page-header">

            <h1>
                Inventory
            </h1>

            <p>
                Inventory access for the current
                StoreFleet branch.
            </p>

        </div>


        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    Branch Inventory Access
                </h2>

            </div>


            <div class="storefleet-card-body">

                <table class="storefleet-table">

                    <thead>

                        <tr>

                            <th>
                                Branch
                            </th>

                            <th>
                                Access
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>

                                <?php
                                echo esc_html(
                                    $branch->name
                                );
                                ?>

                            </td>

                            <td>

                                <?php if ($can_manage) : ?>

                                    Manage Inventory

                                <?php else : ?>

                                    View Only

                                <?php endif; ?>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Branches
|--------------------------------------------------------------------------
|
| Show which assigned roles grant access to the CURRENT branch.
|
*/

function storefleet_render_dokan_staff_branches(
    $staff,
    $current_branch
) {
    $staff_id =
        absint(
            $staff->id
        );

    $current_branch_id =
        absint(
            $current_branch->id
        );

    $assignments =
        storefleet_get_staff_role_assignments(
            $staff_id
        );

    ?>

    <div class="storefleet-dashboard-page">

        <div class="storefleet-page-header">

            <h1>
                Branches
            </h1>

            <p>
                Role access for the currently selected
                StoreFleet branch.
            </p>

        </div>


        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    <?php
                    echo esc_html(
                        $current_branch->name
                    );
                    ?>
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php if (empty($assignments)) : ?>

                    <p>
                        No role assignments found.
                    </p>

                <?php else : ?>

                    <?php

                    $matched_roles = 0;

                    foreach (
                        $assignments as $assignment
                    ) :

                        $role_key =
                            sanitize_key(
                                $assignment->role_key
                            );

                        if (
                            !storefleet_staff_role_can_access_branch(
                                $staff_id,
                                $role_key,
                                $current_branch_id
                            )
                        ) {
                            continue;
                        }

                        $matched_roles++;

                        $scope =
                            storefleet_get_staff_role_scope(
                                $staff_id,
                                $role_key
                            );

                        ?>

                        <div
                            style="
                                padding: 16px 0;
                                border-bottom:
                                    1px solid #e5e7eb;
                            "
                        >

                            <h3>
                                <?php
                                echo esc_html(
                                    storefleet_get_staff_role_label(
                                        $role_key
                                    )
                                );
                                ?>
                            </h3>

                            <p>
                                Scope:

                                <strong>

                                    <?php
                                    echo esc_html(
                                        $scope ===
                                            'all_branches'
                                            ? 'All branches'
                                            : 'Selected branches'
                                    );
                                    ?>

                                </strong>
                            </p>

                            <p>
                                Current branch access:

                                <strong>
                                    Allowed
                                </strong>
                            </p>

                        </div>

                    <?php endforeach; ?>


                    <?php if ($matched_roles === 0) : ?>

                        <p>
                            No assigned role grants access
                            to this branch.
                        </p>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Generic Module Placeholder
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_staff_placeholder(
    $title,
    $description,
    $permission,
    $branch
) {
    ?>

    <div class="storefleet-dashboard-page">

        <div class="storefleet-page-header">

            <h1>
                <?php
                echo esc_html(
                    $title
                );
                ?>
            </h1>

            <p>
                <?php
                echo esc_html(
                    $description
                );
                ?>
            </p>

        </div>


        <div class="storefleet-card">

            <div class="storefleet-card-body">

                <p>
                    <strong>
                        Current Branch:
                    </strong>

                    <?php
                    echo esc_html(
                        $branch->name
                    );
                    ?>
                </p>


                <p>
                    <strong>
                        Permission:
                    </strong>

                    <?php
                    echo esc_html(
                        $permission
                    );
                    ?>
                </p>


                <p>
                    Your staff role is authorized to use
                    this module for the current branch.
                </p>


                <p>
                    The full operational workflow will
                    be implemented in its dedicated feature.
                </p>

            </div>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| No Active Branch
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_no_branch()
{
    wp_die(
        esc_html__(
            'You do not currently have an active StoreFleet branch available for this module.',
            'storefleet-marketplace'
        ),
        esc_html__(
            'StoreFleet Branch Required',
            'storefleet-marketplace'
        ),
        [
            'response' => 403,
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Branch Permission Denied
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_branch_denied(
    $branch
) {
    $branch_name =
        isset(
            $branch->name
        )
            ? sanitize_text_field(
                $branch->name
            )
            : '';

    $message =
        $branch_name !== ''
            ? sprintf(
                __(
                    'Your assigned StoreFleet roles do not permit this operation for %s.',
                    'storefleet-marketplace'
                ),
                $branch_name
            )
            : __(
                'Your assigned StoreFleet roles do not permit this operation for the selected branch.',
                'storefleet-marketplace'
            );

    wp_die(
        esc_html(
            $message
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


/*
|--------------------------------------------------------------------------
| General Access Denied
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_content_denied()
{
    wp_die(
        esc_html__(
            'You are not authorized to access this StoreFleet dashboard module.',
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


/*
|--------------------------------------------------------------------------
| Not Found
|--------------------------------------------------------------------------
*/

function storefleet_dokan_staff_content_not_found()
{
    wp_die(
        esc_html__(
            'The requested StoreFleet staff dashboard module could not be found.',
            'storefleet-marketplace'
        ),
        esc_html__(
            'StoreFleet',
            'storefleet-marketplace'
        ),
        [
            'response' => 404,
        ]
    );
}