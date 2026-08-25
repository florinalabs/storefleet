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

            storefleet_render_dokan_staff_team(
                $staff,
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
| Staff Directory
|--------------------------------------------------------------------------
|
| Read-only staff directory for the CURRENT StoreFleet branch.
|
| Access to this renderer is already protected above by:
|
| - active StoreFleet staff account
| - staff.view
| - current branch
| - same-role permission + branch authorization
|
| Staff management actions remain merchant-owner only in staff-actions.php.
|
*/

function storefleet_render_dokan_staff_team(
    $staff,
    $branch
) {
    $merchant_id =
        absint(
            $staff->merchant_id
            ?? 0
        );

    $branch_id =
        absint(
            $branch->id
            ?? 0
        );

    if (
        !$merchant_id
        ||
        !$branch_id
        ||
        !function_exists(
            'storefleet_get_merchant_staff_for_branch'
        )
    ) {
        storefleet_dokan_staff_content_denied();
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Staff
    |--------------------------------------------------------------------------
    */

    $members =
        storefleet_get_merchant_staff_for_branch(
            $merchant_id,
            $branch_id
        );


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $total_staff =
        count(
            $members
        );

    $active_staff =
        0;

    $suspended_staff =
        0;

    foreach (
        $members as $member
    ) {
        if (
            (int) (
                $member->is_active
                ?? 0
            ) === 1
        ) {
            $active_staff++;

            continue;
        }

        $suspended_staff++;
    }

    ?>

    <style>

        .storefleet-staff-directory-summary {
            display: grid;
            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );
            gap: 16px;
            margin-bottom: 24px;
        }

        .storefleet-staff-directory-stat {
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .storefleet-staff-directory-stat-label {
            display: block;
            margin-bottom: 6px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
        }

        .storefleet-staff-directory-stat-value {
            display: block;
            color: #111827;
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
        }

        .storefleet-staff-directory-context {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .storefleet-staff-directory-context p {
            margin: 0;
            color: #6b7280;
        }

        .storefleet-staff-directory-branch {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 0 11px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .storefleet-staff-directory-person {
            min-width: 210px;
        }

        .storefleet-staff-directory-name {
            display: block;
            color: #111827;
            font-weight: 650;
        }

        .storefleet-staff-directory-login {
            display: block;
            margin-top: 3px;
            color: #6b7280;
            font-size: 12px;
        }

        .storefleet-staff-directory-roles {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .storefleet-staff-directory-role {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
            font-weight: 600;
        }

        .storefleet-staff-directory-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 650;
            white-space: nowrap;
        }

        .storefleet-staff-directory-status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .storefleet-staff-directory-status.is-active {
            background: #dcfce7;
            color: #166534;
        }

        .storefleet-staff-directory-status.is-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .storefleet-staff-directory-empty {
            padding: 44px 24px;
            text-align: center;
            color: #6b7280;
        }

        .storefleet-staff-directory-empty strong {
            display: block;
            margin-bottom: 6px;
            color: #111827;
            font-size: 16px;
        }

        @media (max-width: 800px) {

            .storefleet-staff-directory-summary {
                grid-template-columns: 1fr;
            }
        }

    </style>


    <div class="storefleet-dashboard-page">

        <!--
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-page-header">

            <h1>
                Staff
            </h1>

            <p>
                View staff assigned to the current
                StoreFleet branch.
            </p>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        -->

        <div
            class="
                storefleet-staff-directory-summary
            "
        >

            <div
                class="
                    storefleet-staff-directory-stat
                "
            >

                <span
                    class="
                        storefleet-staff-directory-stat-label
                    "
                >
                    Total Staff
                </span>

                <span
                    class="
                        storefleet-staff-directory-stat-value
                    "
                >
                    <?php
                    echo esc_html(
                        (string) $total_staff
                    );
                    ?>
                </span>

            </div>


            <div
                class="
                    storefleet-staff-directory-stat
                "
            >

                <span
                    class="
                        storefleet-staff-directory-stat-label
                    "
                >
                    Active
                </span>

                <span
                    class="
                        storefleet-staff-directory-stat-value
                    "
                >
                    <?php
                    echo esc_html(
                        (string) $active_staff
                    );
                    ?>
                </span>

            </div>


            <div
                class="
                    storefleet-staff-directory-stat
                "
            >

                <span
                    class="
                        storefleet-staff-directory-stat-label
                    "
                >
                    Suspended
                </span>

                <span
                    class="
                        storefleet-staff-directory-stat-value
                    "
                >
                    <?php
                    echo esc_html(
                        (string) $suspended_staff
                    );
                    ?>
                </span>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Directory
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <div
                    class="
                        storefleet-staff-directory-context
                    "
                >

                    <div>

                        <h2
                            style="
                                margin: 0 0 5px;
                            "
                        >
                            Staff Members
                        </h2>

                        <p>
                            Employees whose StoreFleet roles
                            include this branch.
                        </p>

                    </div>


                    <span
                        class="
                            storefleet-staff-directory-branch
                        "
                    >
                        <?php
                        echo esc_html(
                            $branch->name
                        );
                        ?>
                    </span>

                </div>

            </div>


            <div class="storefleet-card-body">

                <?php if (empty($members)) : ?>

                    <div
                        class="
                            storefleet-staff-directory-empty
                        "
                    >

                        <strong>
                            No staff assigned
                        </strong>

                        No StoreFleet staff members are
                        currently assigned to

                        <?php
                        echo esc_html(
                            $branch->name
                        );
                        ?>.

                    </div>

                <?php else : ?>

                    <div
                        style="
                            width: 100%;
                            overflow-x: auto;
                        "
                    >

                        <table class="storefleet-table">

                            <thead>

                                <tr>

                                    <th>
                                        Staff
                                    </th>

                                    <th>
                                        Roles For This Branch
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php
                                foreach (
                                    $members as $member
                                ) :
                                ?>

                                    <?php

                                    $member_staff_id =
                                        absint(
                                            $member->id
                                            ?? 0
                                        );

                                    $branch_roles = [];

                                    if ($member_staff_id) {

                                        $role_keys =
                                            storefleet_get_staff_roles_for_staff(
                                                $member_staff_id
                                            );

                                        foreach (
                                            $role_keys as $role_key
                                        ) {
                                            $role_key =
                                                sanitize_key(
                                                    $role_key
                                                );

                                            if (
                                                $role_key === ''
                                            ) {
                                                continue;
                                            }

                                            $role_branch_ids =
                                                storefleet_get_staff_role_branch_ids(
                                                    $member_staff_id,
                                                    $role_key
                                                );

                                            if (
                                                !in_array(
                                                    $branch_id,
                                                    $role_branch_ids,
                                                    true
                                                )
                                            ) {
                                                continue;
                                            }

                                            $branch_roles[] =
                                                storefleet_get_staff_role_label(
                                                    $role_key
                                                );
                                        }
                                    }

                                    $branch_roles =
                                        array_values(
                                            array_unique(
                                                array_filter(
                                                    $branch_roles
                                                )
                                            )
                                        );

                                    $is_active =
                                        (int) (
                                            $member->is_active
                                            ?? 0
                                        ) === 1;

                                    ?>

                                    <tr>

                                        <!-- Staff -->

                                        <td
                                            class="
                                                storefleet-staff-directory-person
                                            "
                                        >

                                            <span
                                                class="
                                                    storefleet-staff-directory-name
                                                "
                                            >
                                                <?php
                                                echo esc_html(
                                                    $member->display_name
                                                    ?? ''
                                                );
                                                ?>
                                            </span>


                                            <?php
                                            if (
                                                !empty(
                                                    $member->user_login
                                                )
                                            ) :
                                            ?>

                                                <span
                                                    class="
                                                        storefleet-staff-directory-login
                                                    "
                                                >
                                                    @<?php
                                                    echo esc_html(
                                                        $member->user_login
                                                    );
                                                    ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Roles -->

                                        <td>

                                            <?php
                                            if (
                                                empty(
                                                    $branch_roles
                                                )
                                            ) :
                                            ?>

                                                <span
                                                    style="
                                                        color: #6b7280;
                                                    "
                                                >
                                                    —
                                                </span>

                                            <?php else : ?>

                                                <div
                                                    class="
                                                        storefleet-staff-directory-roles
                                                    "
                                                >

                                                    <?php
                                                    foreach (
                                                        $branch_roles as
                                                        $role_label
                                                    ) :
                                                    ?>

                                                        <span
                                                            class="
                                                                storefleet-staff-directory-role
                                                            "
                                                        >
                                                            <?php
                                                            echo esc_html(
                                                                $role_label
                                                            );
                                                            ?>
                                                        </span>

                                                    <?php endforeach; ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Status -->

                                        <td>

                                            <span
                                                class="
                                                    storefleet-staff-directory-status
                                                    <?php
                                                    echo $is_active
                                                        ? 'is-active'
                                                        : 'is-suspended';
                                                    ?>
                                                "
                                            >
                                                <?php
                                                echo $is_active
                                                    ? 'Active'
                                                    : 'Suspended';
                                                ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

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