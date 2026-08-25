<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Register Staff Portal Route
|--------------------------------------------------------------------------
*/

add_action(
    'init',
    'storefleet_register_staff_portal_route'
);


function storefleet_register_staff_portal_route()
{
    add_rewrite_rule(
        '^staff/?$',
        'index.php?storefleet_staff_portal=1',
        'top'
    );
}


/*
|--------------------------------------------------------------------------
| Register Staff Portal Query Variable
|--------------------------------------------------------------------------
*/

add_filter(
    'query_vars',
    function ($query_vars) {

        $query_vars[] =
            'storefleet_staff_portal';

        return $query_vars;
    }
);


/*
|--------------------------------------------------------------------------
| Staff Portal URL
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_portal_url()
{
    return home_url(
        '/staff/'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect Staff After Login
|--------------------------------------------------------------------------
*/

add_filter(
    'login_redirect',
    'storefleet_staff_login_redirect',
    20,
    3
);


function storefleet_staff_login_redirect(
    $redirect_to,
    $requested_redirect_to,
    $user
) {
    if (
        !($user instanceof WP_User)
    ) {
        return $redirect_to;
    }

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $redirect_to;
    }

    return storefleet_get_staff_portal_url();
}


/*
|--------------------------------------------------------------------------
| Block Inactive Staff During Login
|--------------------------------------------------------------------------
*/

add_filter(
    'wp_authenticate_user',
    'storefleet_validate_staff_login',
    20,
    2
);


function storefleet_validate_staff_login(
    $user,
    $password
) {
    if (
        !($user instanceof WP_User)
    ) {
        return $user;
    }

    if (
        !in_array(
            'storefleet_staff',
            (array) $user->roles,
            true
        )
    ) {
        return $user;
    }

    $staff =
        storefleet_get_staff_by_user_id(
            $user->ID
        );

    if (!$staff) {
        return new WP_Error(
            'storefleet_staff_missing',
            __(
                'Your StoreFleet staff account is not configured correctly.',
                'storefleet-marketplace'
            )
        );
    }

    if (
        (int) $staff->is_active
        !== 1
    ) {
        return new WP_Error(
            'storefleet_staff_inactive',
            __(
                'Your StoreFleet staff account is suspended. Contact your merchant manager.',
                'storefleet-marketplace'
            )
        );
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| Hide Admin Bar From Staff
|--------------------------------------------------------------------------
*/

add_filter(
    'show_admin_bar',
    'storefleet_hide_admin_bar_for_staff'
);


function storefleet_hide_admin_bar_for_staff(
    $show
) {
    if (
        is_user_logged_in()
        &&
        storefleet_is_staff_user()
    ) {
        return false;
    }

    return $show;
}


/*
|--------------------------------------------------------------------------
| Block Staff From WordPress Admin
|--------------------------------------------------------------------------
*/

add_action(
    'admin_init',
    'storefleet_block_staff_wp_admin'
);


function storefleet_block_staff_wp_admin()
{
    if (wp_doing_ajax()) {
        return;
    }

    if (
        !is_user_logged_in()
        ||
        !storefleet_is_staff_user()
    ) {
        return;
    }

    wp_safe_redirect(
        storefleet_get_staff_portal_url()
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Render Staff Portal
|--------------------------------------------------------------------------
*/

add_action(
    'template_redirect',
    'storefleet_render_staff_portal'
);


function storefleet_render_staff_portal()
{
    if (
        !get_query_var(
            'storefleet_staff_portal'
        )
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Login Required
    |--------------------------------------------------------------------------
    */

    if (!is_user_logged_in()) {

        wp_safe_redirect(
            wp_login_url(
                storefleet_get_staff_portal_url()
            )
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Staff Account Required
    |--------------------------------------------------------------------------
    */

    if (!storefleet_is_staff_user()) {

        wp_die(
            esc_html__(
                'This page is only available to StoreFleet staff.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Staff
    |--------------------------------------------------------------------------
    */

    $staff =
        storefleet_get_current_staff();

    if (!$staff) {

        wp_die(
            esc_html__(
                'Your StoreFleet staff account could not be found.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'Staff Account Error',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
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

        wp_die(
            esc_html__(
                'Your StoreFleet staff account is suspended.',
                'storefleet-marketplace'
            ),
            esc_html__(
                'Access Denied',
                'storefleet-marketplace'
            ),
            [
                'response' => 403,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Core Staff Data
    |--------------------------------------------------------------------------
    */

    $staff_id =
        absint(
            $staff->id
        );

    $phone =
        storefleet_get_staff_phone(
            $staff->user_id
        );


    /*
    |--------------------------------------------------------------------------
    | Multi-Role Assignments
    |--------------------------------------------------------------------------
    */

    $role_assignments =
        storefleet_get_staff_role_assignments(
            $staff_id
        );


    /*
    |--------------------------------------------------------------------------
    | Effective Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions are now the UNION of all StoreFleet staff roles.
    |
    */

    $permissions =
        storefleet_get_staff_permissions(
            $staff_id
        );


    /*
    |--------------------------------------------------------------------------
    | Combined Branch Access
    |--------------------------------------------------------------------------
    |
    | This represents the union of branches available through all roles.
    |
    | Detailed branch access per role is displayed separately below.
    |
    */

    $branches =
        storefleet_get_staff_branches(
            $staff_id
        );


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    status_header(200);

    nocache_headers();

    get_header();

    ?>

    <style>

        .storefleet-staff-portal {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        .storefleet-staff-portal-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .storefleet-staff-portal-card h2 {
            margin-top: 0;
        }

        .storefleet-staff-role-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .storefleet-staff-role-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
            font-weight: 600;
        }

        .storefleet-staff-role-access {
            padding: 16px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .storefleet-staff-role-access:first-child {
            padding-top: 0;
        }

        .storefleet-staff-role-access:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .storefleet-staff-role-access h3 {
            margin:
                0 0 8px;
            font-size: 16px;
        }

        .storefleet-staff-role-scope {
            display: inline-block;
            padding: 3px 8px;
            margin-left: 6px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            font-weight: 600;
            vertical-align: middle;
        }

        .storefleet-staff-branch-list {
            margin:
                10px 0 0;
            padding-left: 20px;
        }

        .storefleet-staff-branch-list li {
            margin-bottom: 6px;
        }

        .storefleet-staff-tools {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(210px, 1fr)
                );
            gap: 14px;
            margin-top: 18px;
        }

        .storefleet-staff-tool {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
            background: #ffffff;
        }

        .storefleet-staff-tool strong {
            display: block;
            margin-bottom: 6px;
        }

        .storefleet-staff-tool p {
            margin: 0;
            color: #6b7280;
            line-height: 1.5;
        }

        .storefleet-staff-muted {
            color: #6b7280;
        }

        .storefleet-staff-account-row {
            margin-bottom: 12px;
        }

        .storefleet-staff-account-row:last-child {
            margin-bottom: 0;
        }

    </style>


    <main class="storefleet-staff-portal">


        <!--
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        -->

        <div
            style="
                margin-bottom: 30px;
            "
        >

            <h1>
                StoreFleet Staff
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


        <!--
        |--------------------------------------------------------------------------
        | Account
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-staff-portal-card">

            <h2>
                Your Account
            </h2>


            <div class="storefleet-staff-account-row">

                <strong>
                    Email:
                </strong>

                <?php
                echo esc_html(
                    $staff->user_email
                );
                ?>

            </div>


            <?php if ($phone !== '') : ?>

                <div class="storefleet-staff-account-row">

                    <strong>
                        Phone:
                    </strong>

                    <?php
                    echo esc_html(
                        $phone
                    );
                    ?>

                </div>

            <?php endif; ?>


            <div class="storefleet-staff-account-row">

                <strong>
                    Status:
                </strong>

                Active

            </div>


            <div class="storefleet-staff-account-row">

                <strong>
                    Roles:
                </strong>


                <?php
                if (
                    empty(
                        $role_assignments
                    )
                ) :
                ?>

                    <div
                        class="
                            storefleet-staff-muted
                        "
                    >
                        No operational roles assigned.
                    </div>

                <?php else : ?>

                    <div
                        class="
                            storefleet-staff-role-list
                        "
                    >

                        <?php
                        foreach (
                            $role_assignments
                            as $assignment
                        ) :
                        ?>

                            <?php

                            $role_key =
                                sanitize_key(
                                    $assignment->role_key
                                );

                            ?>

                            <span
                                class="
                                    storefleet-staff-role-pill
                                "
                            >
                                <?php

                                echo esc_html(
                                    storefleet_get_staff_role_label(
                                        $role_key
                                    )
                                );

                                ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Branch Access By Role
        |--------------------------------------------------------------------------
        -->

        <?php
        if (
            in_array(
                'branches.view',
                $permissions,
                true
            )
        ) :
        ?>

            <div class="storefleet-staff-portal-card">

                <h2>
                    Branch Access
                </h2>


                <p class="storefleet-staff-muted">
                    Your branch access is determined separately
                    for each assigned role.
                </p>


                <?php
                if (
                    empty(
                        $role_assignments
                    )
                ) :
                ?>

                    <p>
                        You currently have no role assignments.
                    </p>

                <?php else : ?>

                    <?php
                    foreach (
                        $role_assignments
                        as $assignment
                    ) :
                    ?>

                        <?php

                        $role_key =
                            sanitize_key(
                                $assignment->role_key
                            );


                        $role_label =
                            storefleet_get_staff_role_label(
                                $role_key
                            );


                        $scope_type =
                            storefleet_get_staff_role_scope(
                                $staff_id,
                                $role_key
                            );


                        $role_branches =
                            storefleet_get_staff_role_branches(
                                $staff_id,
                                $role_key
                            );

                        ?>

                        <div
                            class="
                                storefleet-staff-role-access
                            "
                        >

                            <h3>

                                <?php
                                echo esc_html(
                                    $role_label
                                );
                                ?>


                                <?php
                                if (
                                    $scope_type
                                    === 'all_branches'
                                ) :
                                ?>

                                    <span
                                        class="
                                            storefleet-staff-role-scope
                                        "
                                    >
                                        All branches
                                    </span>

                                <?php else : ?>

                                    <span
                                        class="
                                            storefleet-staff-role-scope
                                        "
                                    >
                                        Selected branches
                                    </span>

                                <?php endif; ?>

                            </h3>


                            <?php
                            if (
                                empty(
                                    $role_branches
                                )
                            ) :
                            ?>

                                <p
                                    class="
                                        storefleet-staff-muted
                                    "
                                >
                                    No branch access assigned
                                    to this role.
                                </p>

                            <?php else : ?>

                                <ul
                                    class="
                                        storefleet-staff-branch-list
                                    "
                                >

                                    <?php
                                    foreach (
                                        $role_branches
                                        as $branch
                                    ) :
                                    ?>

                                        <li>

                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $branch->name
                                                );
                                                ?>
                                            </strong>


                                            <?php

                                            $address =
                                                storefleet_get_branch_address(
                                                    $branch
                                                );

                                            if (
                                                $address !== ''
                                            ) :
                                            ?>

                                                —
                                                <?php
                                                echo esc_html(
                                                    $address
                                                );
                                                ?>

                                            <?php endif; ?>


                                            <?php
                                            if (
                                                isset(
                                                    $branch->is_active
                                                )
                                                &&
                                                (int)
                                                $branch->is_active
                                                !== 1
                                            ) :
                                            ?>

                                                <small>
                                                    (Inactive)
                                                </small>

                                            <?php endif; ?>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!--
        |--------------------------------------------------------------------------
        | Combined Branch Summary
        |--------------------------------------------------------------------------
        -->

        <?php
        if (
            in_array(
                'branches.view',
                $permissions,
                true
            )
            &&
            !empty(
                $branches
            )
        ) :
        ?>

            <div class="storefleet-staff-portal-card">

                <h2>
                    Accessible Branches
                </h2>

                <p class="storefleet-staff-muted">
                    These are all branches you can access
                    through any of your assigned roles.
                </p>


                <ul class="storefleet-staff-branch-list">

                    <?php
                    foreach (
                        $branches
                        as $branch
                    ) :
                    ?>

                        <li>

                            <strong>
                                <?php
                                echo esc_html(
                                    $branch->name
                                );
                                ?>
                            </strong>


                            <?php

                            $address =
                                storefleet_get_branch_address(
                                    $branch
                                );

                            if (
                                $address !== ''
                            ) :
                            ?>

                                —
                                <?php
                                echo esc_html(
                                    $address
                                );
                                ?>

                            <?php endif; ?>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!--
        |--------------------------------------------------------------------------
        | StoreFleet Modules
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-staff-portal-card">

            <h2>
                StoreFleet Tools
            </h2>


            <?php
            if (
                empty(
                    $permissions
                )
            ) :
            ?>

                <p>
                    No StoreFleet operational tools are
                    currently assigned to your account.
                </p>

            <?php else : ?>


                <div class="storefleet-staff-tools">


                    <!-- Orders -->

                    <?php
                    if (
                        in_array(
                            'orders.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Orders
                            </strong>

                            <p>

                                <?php
                                if (
                                    in_array(
                                        'orders.manage',
                                        $permissions,
                                        true
                                    )
                                ) :
                                ?>

                                    View and process orders
                                    for branches permitted
                                    by your Order or Management roles.

                                <?php else : ?>

                                    View orders for branches
                                    permitted by your assigned roles.

                                <?php endif; ?>

                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- POS -->

                    <?php
                    if (
                        in_array(
                            'pos.use',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                POS
                            </strong>

                            <p>
                                Use StoreFleet POS in branches
                                where one of your POS-enabled
                                roles has access.
                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Inventory -->

                    <?php
                    if (
                        in_array(
                            'inventory.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Inventory
                            </strong>

                            <p>

                                <?php
                                if (
                                    in_array(
                                        'inventory.manage',
                                        $permissions,
                                        true
                                    )
                                ) :
                                ?>

                                    View and update stock in
                                    branches permitted by your
                                    inventory-enabled roles.

                                <?php else : ?>

                                    View stock in branches
                                    permitted by your assigned roles.

                                <?php endif; ?>

                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Staff -->

                    <?php
                    if (
                        in_array(
                            'staff.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Staff
                            </strong>

                            <p>
                                View staff information
                                permitted by your operational role.
                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Delivery -->

                    <?php
                    if (
                        in_array(
                            'delivery.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Delivery
                            </strong>

                            <p>

                                <?php
                                if (
                                    in_array(
                                        'delivery.rider',
                                        $permissions,
                                        true
                                    )
                                ) :
                                ?>

                                    Work as an in-house rider
                                    in branches assigned to your
                                    Delivery Staff role.

                                <?php elseif (
                                    in_array(
                                        'delivery.manage',
                                        $permissions,
                                        true
                                    )
                                ) :
                                ?>

                                    View and manage delivery
                                    operations for permitted branches.

                                <?php else : ?>

                                    View delivery operations
                                    for permitted branches.

                                <?php endif; ?>

                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Products -->

                    <?php
                    if (
                        in_array(
                            'products.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Products
                            </strong>

                            <p>
                                View merchant products needed
                                for your assigned StoreFleet
                                operations.
                            </p>

                        </div>

                    <?php endif; ?>


                    <!-- Branches -->

                    <?php
                    if (
                        in_array(
                            'branches.view',
                            $permissions,
                            true
                        )
                    ) :
                    ?>

                        <div class="storefleet-staff-tool">

                            <strong>
                                Branches
                            </strong>

                            <p>
                                View branches available through
                                your assigned roles.
                            </p>

                        </div>

                    <?php endif; ?>


                </div>

            <?php endif; ?>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        -->

        <p>

            <a
                href="<?php

                    echo esc_url(
                        wp_logout_url(
                            storefleet_get_staff_portal_url()
                        )
                    );

                ?>"
            >
                Log out
            </a>

        </p>

    </main>

    <?php

    get_footer();

    exit;
}