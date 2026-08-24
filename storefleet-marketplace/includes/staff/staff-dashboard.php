<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Register Staff Query Variable
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_query_var_filter',
    function ($query_vars) {
        $query_vars['storefleet-staff'] = 'storefleet-staff';

        return $query_vars;
    }
);


/*
|--------------------------------------------------------------------------
| Add Staff To Dokan Navigation
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_get_dashboard_nav',
    function ($menus) {

        if (!storefleet_is_merchant_owner()) {
            return $menus;
        }

        $menus['storefleet-staff'] = [
            'title' => __(
                'Staff',
                'storefleet-marketplace'
            ),

            'icon' =>
                '<i class="fas fa-users"></i>',

            'url' =>
                dokan_get_navigation_url(
                    'storefleet-staff'
                ),

            'pos' => 56,
        ];

        return $menus;
    },
    50
);


/*
|--------------------------------------------------------------------------
| Active Staff Navigation
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_dashboard_nav_active',
    function (
        $active_menu,
        $request,
        $active
    ) {
        global $wp;

        if (
            isset(
                $wp->query_vars[
                    'storefleet-staff'
                ]
            )
        ) {
            return 'storefleet-staff';
        }

        return $active_menu;
    },
    10,
    3
);


/*
|--------------------------------------------------------------------------
| Render Staff Dashboard
|--------------------------------------------------------------------------
*/

add_action(
    'dokan_load_custom_template',
    'storefleet_render_staff_dashboard'
);


function storefleet_render_staff_dashboard(
    $query_vars
) {
    /*
    |--------------------------------------------------------------------------
    | Staff Route Only
    |--------------------------------------------------------------------------
    */

    if (
        empty($query_vars)
        ||
        !array_key_exists(
            'storefleet-staff',
            $query_vars
        )
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owner Only
    |--------------------------------------------------------------------------
    */

    if (!storefleet_is_merchant_owner()) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant
    |--------------------------------------------------------------------------
    */

    $merchant_id =
        absint(
            storefleet_get_current_merchant_id()
        );


    /*
    |--------------------------------------------------------------------------
    | Staff Records
    |--------------------------------------------------------------------------
    */

    $staff =
        storefleet_get_merchant_staff(
            $merchant_id
        );


    /*
    |--------------------------------------------------------------------------
    | Active Branches
    |--------------------------------------------------------------------------
    |
    | Used when creating new employees.
    |
    */

    $branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );


    /*
    |--------------------------------------------------------------------------
    | All Merchant Branches
    |--------------------------------------------------------------------------
    |
    | Used when editing because an existing staff role may already reference
    | a branch that has since been made inactive.
    |
    */

    $all_branches =
        storefleet_get_merchant_branches(
            $merchant_id
        );


    /*
    |--------------------------------------------------------------------------
    | Edit Staff
    |--------------------------------------------------------------------------
    */

    $edit_staff_id =
        isset($_GET['sf_edit_staff'])
            ? absint(
                wp_unslash(
                    $_GET['sf_edit_staff']
                )
            )
            : 0;

    $editing_staff = null;

    if (
        $edit_staff_id
        &&
        storefleet_merchant_owns_staff(
            $merchant_id,
            $edit_staff_id
        )
    ) {
        $editing_staff =
            storefleet_get_staff(
                $edit_staff_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Notice
    |--------------------------------------------------------------------------
    */

    $notice =
        isset($_GET['sf_notice'])
            ? sanitize_key(
                wp_unslash(
                    $_GET['sf_notice']
                )
            )
            : '';

    ?>

    <style>

        /*
        |--------------------------------------------------------------------------
        | Multi Role UI
        |--------------------------------------------------------------------------
        */

        .storefleet-role-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(280px, 1fr)
                );
            gap: 16px;
            margin-top: 12px;
        }

        .storefleet-role-card {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;
        }

        .storefleet-role-card.is-enabled {
            border-color: #86efac;
            box-shadow:
                0 0 0 1px #dcfce7;
        }

        .storefleet-role-card-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 16px;
            margin: 0;
            cursor: pointer;
        }

        .storefleet-role-card-header input {
            margin-top: 3px;
        }

        .storefleet-role-title {
            display: block;
            color: #111827;
            font-size: 14px;
            font-weight: 700;
        }

        .storefleet-role-description {
            display: block;
            margin-top: 4px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        .storefleet-role-details {
            display: none;
            padding:
                0 16px 16px;
        }

        .storefleet-role-card.is-enabled
        .storefleet-role-details {
            display: block;
        }

        .storefleet-role-scope {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin:
                10px 0 12px;
        }

        .storefleet-role-scope label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            cursor: pointer;
        }

        .storefleet-role-branches {
            margin-top: 10px;
        }

        .storefleet-role-branches.is-hidden {
            display: none;
        }

        .storefleet-role-branch-box {
            margin-top: 10px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }

        .storefleet-role-pill {
            display: inline-block;
            padding: 4px 9px;
            margin:
                0 4px 4px 0;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .storefleet-role-access-line {
            margin-bottom: 8px;
            font-size: 13px;
        }

        .storefleet-role-access-line:last-child {
            margin-bottom: 0;
        }

        .storefleet-role-access-label {
            color: #374151;
            font-weight: 600;
        }

        .storefleet-role-branch-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            cursor: pointer;
        }

        .storefleet-role-branch-checkbox:last-child {
            margin-bottom: 0;
        }

        .storefleet-role-branch-checkbox input {
            margin: 0;
        }

        .storefleet-muted {
            color: #6b7280;
        }

    </style>


    <div class="storefleet-dashboard-page">

        <!--
        |--------------------------------------------------------------------------
        | Page Header
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-page-header">

            <h1>
                <?php
                esc_html_e(
                    'Staff',
                    'storefleet-marketplace'
                );
                ?>
            </h1>

            <p>
                Add employees, assign multiple roles,
                and control branch access separately
                for each role.
            </p>

        </div>


        <?php
        storefleet_staff_notice(
            $notice
        );
        ?>


        <!--
        |--------------------------------------------------------------------------
        | Staff List
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    Your Staff
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php if (empty($staff)) : ?>

                    <p>
                        No staff members have been created yet.
                    </p>

                <?php else : ?>

                    <table class="storefleet-table">

                        <thead>

                            <tr>
                                <th>Staff</th>
                                <th>Login</th>
                                <th>Phone</th>
                                <th>Roles</th>
                                <th>Branch Access</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($staff as $member) : ?>

                                <?php

                                $phone =
                                    storefleet_get_staff_phone(
                                        $member->user_id
                                    );


                                $role_assignments =
                                    storefleet_get_staff_role_assignments(
                                        $member->id
                                    );

                                ?>

                                <tr>

                                    <!-- Staff -->

                                    <td>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $member->display_name
                                            );
                                            ?>
                                        </strong>

                                        <br>

                                        <small>
                                            <?php
                                            echo esc_html(
                                                $member->user_email
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <!-- Login -->

                                    <td>

                                        <?php
                                        echo esc_html(
                                            $member->user_login
                                        );
                                        ?>

                                    </td>


                                    <!-- Phone -->

                                    <td>

                                        <?php

                                        if ($phone !== '') {

                                            echo esc_html(
                                                $phone
                                            );

                                        } else {

                                            echo '—';
                                        }

                                        ?>

                                    </td>


                                    <!-- Roles -->

                                    <td>

                                        <?php
                                        if (
                                            empty(
                                                $role_assignments
                                            )
                                        ) :
                                        ?>

                                            —

                                        <?php else : ?>

                                            <?php
                                            foreach (
                                                $role_assignments
                                                as $assignment
                                            ) :
                                            ?>

                                                <span
                                                    class="storefleet-role-pill"
                                                >
                                                    <?php

                                                    echo esc_html(
                                                        storefleet_get_staff_role_label(
                                                            $assignment->role_key
                                                        )
                                                    );

                                                    ?>
                                                </span>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Role Branch Access -->

                                    <td>

                                        <?php
                                        if (
                                            empty(
                                                $role_assignments
                                            )
                                        ) :
                                        ?>

                                            —

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


                                                $scope_type =
                                                    storefleet_get_staff_role_scope(
                                                        $member->id,
                                                        $role_key
                                                    );

                                                ?>

                                                <div
                                                    class="
                                                        storefleet-role-access-line
                                                    "
                                                >

                                                    <span
                                                        class="
                                                            storefleet-role-access-label
                                                        "
                                                    >
                                                        <?php

                                                        echo esc_html(
                                                            storefleet_get_staff_role_label(
                                                                $role_key
                                                            )
                                                        );

                                                        ?>:
                                                    </span>


                                                    <?php
                                                    if (
                                                        $scope_type
                                                        === 'all_branches'
                                                    ) :
                                                    ?>

                                                        All branches

                                                    <?php else : ?>

                                                        <?php

                                                        $role_branch_names =
                                                            storefleet_get_staff_role_branch_names(
                                                                $member->id,
                                                                $role_key
                                                            );


                                                        if (
                                                            empty(
                                                                $role_branch_names
                                                            )
                                                        ) {

                                                            echo '—';

                                                        } else {

                                                            echo esc_html(
                                                                implode(
                                                                    ', ',
                                                                    $role_branch_names
                                                                )
                                                            );
                                                        }

                                                        ?>

                                                    <?php endif; ?>

                                                </div>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <?php
                                        if (
                                            (int)
                                            $member->is_active
                                            === 1
                                        ) :
                                        ?>

                                            <span
                                                class="
                                                    storefleet-status
                                                    storefleet-status-active
                                                "
                                            >
                                                Active
                                            </span>

                                        <?php else : ?>

                                            <span
                                                class="
                                                    storefleet-status
                                                    storefleet-status-inactive
                                                "
                                            >
                                                Suspended
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Actions -->

                                    <td>

                                        <div
                                            style="
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                                flex-wrap: wrap;
                                            "
                                        >

                                            <!-- Edit -->

                                            <a
                                                href="<?php

                                                    echo esc_url(
                                                        add_query_arg(
                                                            'sf_edit_staff',
                                                            absint(
                                                                $member->id
                                                            ),
                                                            dokan_get_navigation_url(
                                                                'storefleet-staff'
                                                            )
                                                        )
                                                    );

                                                ?>"
                                                style="
                                                    display: inline-flex;
                                                    align-items: center;
                                                    justify-content: center;
                                                    padding: 6px 12px;
                                                    min-height: 32px;
                                                    border: 1px solid #22c55e;
                                                    border-radius: 6px;
                                                    background: #dcfce7;
                                                    color: #166534;
                                                    font-size: 13px;
                                                    font-weight: 600;
                                                    line-height: 1;
                                                    text-decoration: none;
                                                    white-space: nowrap;
                                                "
                                            >
                                                Edit
                                            </a>


                                            <?php
                                            if (
                                                (int)
                                                $member->is_active
                                                === 1
                                            ) :
                                            ?>

                                                <!-- Suspend -->

                                                <form
                                                    method="post"
                                                    style="
                                                        display: inline-block;
                                                        margin: 0;
                                                    "
                                                >

                                                    <?php

                                                    wp_nonce_field(
                                                        'storefleet_suspend_staff_' .
                                                        absint(
                                                            $member->id
                                                        ),
                                                        'storefleet_staff_nonce'
                                                    );

                                                    ?>

                                                    <input
                                                        type="hidden"
                                                        name="storefleet_staff_action"
                                                        value="suspend"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="staff_id"
                                                        value="<?php
                                                            echo esc_attr(
                                                                $member->id
                                                            );
                                                        ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="storefleet-button"
                                                        onclick="
                                                            return confirm(
                                                                'Suspend this staff member? Their current StoreFleet sessions will be logged out.'
                                                            );
                                                        "
                                                    >
                                                        Suspend
                                                    </button>

                                                </form>

                                            <?php else : ?>

                                                <!-- Reactivate -->

                                                <form
                                                    method="post"
                                                    style="
                                                        display: inline-block;
                                                        margin: 0;
                                                    "
                                                >

                                                    <?php

                                                    wp_nonce_field(
                                                        'storefleet_reactivate_staff_' .
                                                        absint(
                                                            $member->id
                                                        ),
                                                        'storefleet_staff_nonce'
                                                    );

                                                    ?>

                                                    <input
                                                        type="hidden"
                                                        name="storefleet_staff_action"
                                                        value="reactivate"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="staff_id"
                                                        value="<?php
                                                            echo esc_attr(
                                                                $member->id
                                                            );
                                                        ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="storefleet-button"
                                                    >
                                                        Reactivate
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Edit Staff
        |--------------------------------------------------------------------------
        -->

        <?php if ($editing_staff) : ?>

            <div class="storefleet-card">

                <div class="storefleet-card-header">

                    <h2>
                        Edit Staff Member
                    </h2>

                </div>


                <div class="storefleet-card-body">

                    <?php

                    storefleet_render_edit_staff_form(
                        $editing_staff,
                        $all_branches
                    );

                    ?>

                </div>

            </div>

        <?php endif; ?>


        <!--
        |--------------------------------------------------------------------------
        | Add Staff
        |--------------------------------------------------------------------------
        -->

        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    Add Staff Member
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php if (empty($branches)) : ?>

                    <div
                        class="
                            storefleet-notice
                            storefleet-notice-error
                        "
                    >
                        You must create at least one
                        active branch before adding staff.
                    </div>

                <?php else : ?>

                    <?php

                    storefleet_render_staff_form(
                        $branches
                    );

                    ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Multi Role UI JavaScript
    |--------------------------------------------------------------------------
    -->

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const roleCards =
                    document.querySelectorAll(
                        '[data-storefleet-role-card]'
                    );


                roleCards.forEach(
                    function (card) {

                        const enabledCheckbox =
                            card.querySelector(
                                '[data-storefleet-role-enabled]'
                            );


                        if (!enabledCheckbox) {
                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Role Enabled State
                        |--------------------------------------------------------------------------
                        */

                        function updateRoleCard()
                        {
                            if (
                                enabledCheckbox.checked
                            ) {
                                card.classList.add(
                                    'is-enabled'
                                );
                            } else {

                                card.classList.remove(
                                    'is-enabled'
                                );
                            }
                        }


                        enabledCheckbox.addEventListener(
                            'change',
                            updateRoleCard
                        );


                        updateRoleCard();


                        /*
                        |--------------------------------------------------------------------------
                        | Branch Scope
                        |--------------------------------------------------------------------------
                        */

                        const scopeRadios =
                            card.querySelectorAll(
                                '[data-storefleet-role-scope]'
                            );


                        const branchArea =
                            card.querySelector(
                                '[data-storefleet-role-branches]'
                            );


                        function updateScope()
                        {
                            if (!branchArea) {
                                return;
                            }


                            let scope =
                                'selected_branches';


                            scopeRadios.forEach(
                                function (radio) {

                                    if (radio.checked) {
                                        scope =
                                            radio.value;
                                    }
                                }
                            );


                            if (
                                scope ===
                                'all_branches'
                            ) {

                                branchArea.classList.add(
                                    'is-hidden'
                                );

                            } else {

                                branchArea.classList.remove(
                                    'is-hidden'
                                );
                            }
                        }


                        scopeRadios.forEach(
                            function (radio) {

                                radio.addEventListener(
                                    'change',
                                    updateScope
                                );
                            }
                        );


                        updateScope();
                    }
                );
            }
        );

    </script>

    <?php
}


/*
|--------------------------------------------------------------------------
| Create Staff Form
|--------------------------------------------------------------------------
*/

function storefleet_render_staff_form(
    $branches
) {
    ?>

    <form method="post">

        <?php

        wp_nonce_field(
            'storefleet_create_staff',
            'storefleet_staff_nonce'
        );

        ?>

        <input
            type="hidden"
            name="storefleet_staff_action"
            value="create"
        >


        <div class="storefleet-form-grid">

            <!-- First Name -->

            <div class="storefleet-form-group">

                <label for="first_name">
                    First Name *
                </label>

                <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    class="storefleet-form-control"
                    autocomplete="given-name"
                    required
                >

            </div>


            <!-- Last Name -->

            <div class="storefleet-form-group">

                <label for="last_name">
                    Last Name
                </label>

                <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    class="storefleet-form-control"
                    autocomplete="family-name"
                >

            </div>


            <!-- Email -->

            <div class="storefleet-form-group">

                <label for="staff_email">
                    Email Address *
                </label>

                <input
                    id="staff_email"
                    type="email"
                    name="email"
                    class="storefleet-form-control"
                    autocomplete="email"
                    placeholder="staff@example.com"
                    required
                >

                <small class="storefleet-help">
                    The employee can use this email
                    for their StoreFleet staff account.
                </small>

            </div>


            <!-- Phone -->

            <div class="storefleet-form-group">

                <label for="staff_phone">
                    Phone Number *
                </label>

                <input
                    id="staff_phone"
                    type="text"
                    name="phone"
                    class="storefleet-form-control"
                    autocomplete="tel"
                    placeholder="+63 917 123 4567"
                    required
                >

                <small class="storefleet-help">
                    Used for employee contact and
                    operational coordination.
                </small>

            </div>


            <!-- Password -->

            <div class="storefleet-form-group">

                <label for="staff_password">
                    Temporary Password *
                </label>

                <input
                    id="staff_password"
                    type="password"
                    name="password"
                    class="storefleet-form-control"
                    autocomplete="new-password"
                    minlength="10"
                    required
                >

                <small class="storefleet-help">
                    Minimum 10 characters.
                </small>

            </div>


            <!-- Account Type -->

            <div class="storefleet-form-group">

                <label>
                    Account Type
                </label>

                <div
                    style="
                        padding: 12px 14px;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        background: #f9fafb;
                    "
                >

                    <strong>
                        StoreFleet Staff
                    </strong>

                    <div
                        style="
                            margin-top: 4px;
                            color: #6b7280;
                            font-size: 12px;
                        "
                    >
                        Individual employee login.
                    </div>

                </div>

            </div>


            <!-- Roles & Branch Access -->

            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label>
                    Roles &amp; Branch Access *
                </label>

                <small
                    class="storefleet-help"
                    style="
                        display: block;
                        margin-bottom: 10px;
                    "
                >
                    Select one or more roles.
                    Each role can have its own branch access.
                </small>


                <?php

                storefleet_render_staff_role_access_fields(
                    $branches,
                    0,
                    'create'
                );

                ?>

            </div>


            <!-- Security Notice -->

            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <div
                    style="
                        padding: 14px 16px;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        background: #f9fafb;
                    "
                >

                    <strong
                        style="
                            display: block;
                            margin-bottom: 5px;
                        "
                    >
                        Individual Staff Account
                    </strong>

                    <p
                        style="
                            margin: 0;
                            color: #6b7280;
                            font-size: 13px;
                            line-height: 1.5;
                        "
                    >
                        One employee can have multiple
                        StoreFleet roles while keeping
                        one login. Permissions and branch
                        access are enforced independently
                        for each selected role.
                    </p>

                </div>

            </div>


            <!-- Submit -->

            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <button
                    type="submit"
                    class="storefleet-button"
                >
                    Add Staff Member
                </button>

            </div>

        </div>

    </form>

    <?php
}


/*
|--------------------------------------------------------------------------
| Edit Staff Form
|--------------------------------------------------------------------------
*/

function storefleet_render_edit_staff_form(
    $staff,
    $branches
) {
    if (!$staff) {
        return;
    }


    $user =
        get_userdata(
            $staff->user_id
        );


    if (!$user) {
        return;
    }


    $phone =
        storefleet_get_staff_phone(
            $staff->user_id
        );

    ?>

    <form method="post">

        <?php

        wp_nonce_field(
            'storefleet_update_staff_' .
            absint(
                $staff->id
            ),
            'storefleet_staff_nonce'
        );

        ?>


        <input
            type="hidden"
            name="storefleet_staff_action"
            value="update"
        >


        <input
            type="hidden"
            name="staff_id"
            value="<?php
                echo esc_attr(
                    $staff->id
                );
            ?>"
        >


        <div class="storefleet-form-grid">

            <!-- First Name -->

            <div class="storefleet-form-group">

                <label for="edit_staff_first_name">
                    First Name *
                </label>

                <input
                    id="edit_staff_first_name"
                    type="text"
                    name="first_name"
                    class="storefleet-form-control"
                    autocomplete="given-name"
                    value="<?php
                        echo esc_attr(
                            $user->first_name
                        );
                    ?>"
                    required
                >

            </div>


            <!-- Last Name -->

            <div class="storefleet-form-group">

                <label for="edit_staff_last_name">
                    Last Name
                </label>

                <input
                    id="edit_staff_last_name"
                    type="text"
                    name="last_name"
                    class="storefleet-form-control"
                    autocomplete="family-name"
                    value="<?php
                        echo esc_attr(
                            $user->last_name
                        );
                    ?>"
                >

            </div>


            <!-- Email -->

            <div class="storefleet-form-group">

                <label for="edit_staff_email">
                    Email Address *
                </label>

                <input
                    id="edit_staff_email"
                    type="email"
                    name="email"
                    class="storefleet-form-control"
                    autocomplete="email"
                    value="<?php
                        echo esc_attr(
                            $user->user_email
                        );
                    ?>"
                    required
                >

            </div>


            <!-- Phone -->

            <div class="storefleet-form-group">

                <label for="edit_staff_phone">
                    Phone Number *
                </label>

                <input
                    id="edit_staff_phone"
                    type="text"
                    name="phone"
                    class="storefleet-form-control"
                    autocomplete="tel"
                    value="<?php
                        echo esc_attr(
                            $phone
                        );
                    ?>"
                    required
                >

            </div>


            <!-- Username -->

            <div class="storefleet-form-group">

                <label for="edit_staff_username">
                    Login Username
                </label>

                <input
                    id="edit_staff_username"
                    type="text"
                    class="storefleet-form-control"
                    value="<?php
                        echo esc_attr(
                            $user->user_login
                        );
                    ?>"
                    disabled
                >

                <small class="storefleet-help">
                    Username cannot be changed.
                </small>

            </div>


            <!-- Account Status -->

            <div class="storefleet-form-group">

                <label>
                    Account Status
                </label>

                <div
                    style="
                        padding: 12px 14px;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        background: #f9fafb;
                    "
                >

                    <?php
                    if (
                        (int)
                        $staff->is_active
                        === 1
                    ) :
                    ?>

                        <strong>
                            Active
                        </strong>

                    <?php else : ?>

                        <strong>
                            Suspended
                        </strong>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Roles & Branch Access -->

            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label>
                    Roles &amp; Branch Access *
                </label>

                <small
                    class="storefleet-help"
                    style="
                        display: block;
                        margin-bottom: 10px;
                    "
                >
                    Add or remove roles and control
                    branch access independently for
                    each role.
                </small>


                <?php

                storefleet_render_staff_role_access_fields(
                    $branches,
                    $staff->id,
                    'edit-' .
                    absint(
                        $staff->id
                    )
                );

                ?>

            </div>


            <!-- Actions -->

            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <button
                    type="submit"
                    class="storefleet-button"
                >
                    Save Staff Changes
                </button>


                <a
                    href="<?php
                        echo esc_url(
                            dokan_get_navigation_url(
                                'storefleet-staff'
                            )
                        );
                    ?>"
                    style="
                        display: inline-block;
                        margin-left: 12px;
                    "
                >
                    Cancel
                </a>

            </div>

        </div>

    </form>

    <?php
}


/*
|--------------------------------------------------------------------------
| Role + Branch Access Fields
|--------------------------------------------------------------------------
*/

function storefleet_render_staff_role_access_fields(
    $branches,
    $staff_id = 0,
    $context = 'create'
) {
    $staff_id =
        absint(
            $staff_id
        );


    $roles =
        storefleet_get_staff_roles();


    /*
    |--------------------------------------------------------------------------
    | Existing Role Assignments
    |--------------------------------------------------------------------------
    */

    $existing_roles = [];


    if ($staff_id) {

        $assignments =
            storefleet_get_staff_role_assignments(
                $staff_id
            );


        foreach (
            $assignments as $assignment
        ) {
            $role_key =
                sanitize_key(
                    $assignment->role_key
                );


            if ($role_key === '') {
                continue;
            }


            $existing_roles[
                $role_key
            ] = [

                'scope_type' =>
                    storefleet_get_staff_role_scope(
                        $staff_id,
                        $role_key
                    ),

                'branch_ids' =>
                    storefleet_get_staff_role_branch_ids(
                        $staff_id,
                        $role_key
                    ),
            ];
        }
    }


    ?>

    <div class="storefleet-role-grid">

        <?php
        foreach (
            $roles as
            $role_key => $role_label
        ) :
        ?>

            <?php

            $role_key =
                sanitize_key(
                    $role_key
                );


            $is_enabled =
                isset(
                    $existing_roles[
                        $role_key
                    ]
                );


            $scope_type =
                $is_enabled
                    ? sanitize_key(
                        $existing_roles[
                            $role_key
                        ]['scope_type']
                    )
                    : 'selected_branches';


            if (
                !in_array(
                    $scope_type,
                    [
                        'selected_branches',
                        'all_branches',
                    ],
                    true
                )
            ) {
                $scope_type =
                    'selected_branches';
            }


            $assigned_branch_ids =
                $is_enabled
                    ? array_values(
                        array_unique(
                            array_map(
                                'absint',
                                $existing_roles[
                                    $role_key
                                ]['branch_ids']
                            )
                        )
                    )
                    : [];


            $input_id =
                sanitize_html_class(
                    'sf-role-' .
                    $context .
                    '-' .
                    $role_key
                );

            ?>


            <div
                class="
                    storefleet-role-card
                    <?php
                    echo $is_enabled
                        ? 'is-enabled'
                        : '';
                    ?>
                "
                data-storefleet-role-card
            >

                <!-- Role Checkbox -->

                <label
                    class="
                        storefleet-role-card-header
                    "
                    for="<?php
                        echo esc_attr(
                            $input_id
                        );
                    ?>"
                >

                    <input
                        id="<?php
                            echo esc_attr(
                                $input_id
                            );
                        ?>"
                        type="checkbox"
                        name="roles[<?php
                            echo esc_attr(
                                $role_key
                            );
                        ?>][enabled]"
                        value="1"
                        data-storefleet-role-enabled
                        <?php
                        checked(
                            $is_enabled
                        );
                        ?>
                    >


                    <span>

                        <span
                            class="
                                storefleet-role-title
                            "
                        >
                            <?php
                            echo esc_html(
                                $role_label
                            );
                            ?>
                        </span>


                        <span
                            class="
                                storefleet-role-description
                            "
                        >
                            <?php

                            echo esc_html(
                                storefleet_get_staff_role_description(
                                    $role_key
                                )
                            );

                            ?>
                        </span>

                    </span>

                </label>


                <!-- Role Details -->

                <div class="storefleet-role-details">


                    <?php
                    if (
                        $role_key
                        === 'manager'
                    ) :
                    ?>

                        <!-- Manager Scope -->

                        <strong>
                            Branch Scope
                        </strong>


                        <div class="storefleet-role-scope">

                            <label>

                                <input
                                    type="radio"
                                    name="roles[<?php
                                        echo esc_attr(
                                            $role_key
                                        );
                                    ?>][scope_type]"
                                    value="selected_branches"
                                    data-storefleet-role-scope
                                    <?php
                                    checked(
                                        $scope_type,
                                        'selected_branches'
                                    );
                                    ?>
                                >

                                Selected branches

                            </label>


                            <label>

                                <input
                                    type="radio"
                                    name="roles[<?php
                                        echo esc_attr(
                                            $role_key
                                        );
                                    ?>][scope_type]"
                                    value="all_branches"
                                    data-storefleet-role-scope
                                    <?php
                                    checked(
                                        $scope_type,
                                        'all_branches'
                                    );
                                    ?>
                                >

                                All merchant branches

                            </label>

                        </div>


                    <?php else : ?>


                        <input
                            type="hidden"
                            name="roles[<?php
                                echo esc_attr(
                                    $role_key
                                );
                            ?>][scope_type]"
                            value="selected_branches"
                        >


                    <?php endif; ?>


                    <!-- Branch Selection -->

                    <div
                        class="
                            storefleet-role-branches
                            <?php
                            echo (
                                $scope_type
                                === 'all_branches'
                            )
                                ? 'is-hidden'
                                : '';
                            ?>
                        "
                        data-storefleet-role-branches
                    >

                        <strong>
                            Branch Access
                        </strong>


                        <div
                            class="
                                storefleet-role-branch-box
                            "
                        >

                            <?php
                            if (
                                empty(
                                    $branches
                                )
                            ) :
                            ?>

                                <span
                                    class="
                                        storefleet-muted
                                    "
                                >
                                    No branches available.
                                </span>


                            <?php else : ?>


                                <?php
                                foreach (
                                    $branches
                                    as $branch
                                ) :
                                ?>

                                    <?php

                                    $branch_id =
                                        absint(
                                            $branch->id
                                        );


                                    $branch_checked =
                                        in_array(
                                            $branch_id,
                                            $assigned_branch_ids,
                                            true
                                        );

                                    ?>


                                    <label
                                        class="
                                            storefleet-role-branch-checkbox
                                        "
                                    >

                                        <input
                                            type="checkbox"
                                            name="roles[<?php
                                                echo esc_attr(
                                                    $role_key
                                                );
                                            ?>][branch_ids][]"
                                            value="<?php
                                                echo esc_attr(
                                                    $branch_id
                                                );
                                            ?>"
                                            <?php
                                            checked(
                                                $branch_checked
                                            );
                                            ?>
                                        >


                                        <span>

                                            <?php
                                            echo esc_html(
                                                $branch->name
                                            );
                                            ?>


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
                                                    (Inactive Branch)
                                                </small>

                                            <?php endif; ?>

                                        </span>

                                    </label>


                                <?php endforeach; ?>


                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


        <?php endforeach; ?>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Role Descriptions
|--------------------------------------------------------------------------
*/

function storefleet_get_staff_role_description(
    $role_key
) {
    $role_key =
        sanitize_key(
            $role_key
        );


    $descriptions = [

        'manager' =>
            'Broad merchant operations. Can use selected branches or all merchant branches.',

        'branch_manager' =>
            'Manages operations for one or more assigned branches.',

        'cashier' =>
            'Uses POS and views operational product and inventory information.',

        'inventory_staff' =>
            'Views and manages inventory for assigned branches.',

        'order_staff' =>
            'Views and processes marketplace orders for assigned branches.',

        'delivery_staff' =>
            'Works as an in-house rider for assigned branches.',
    ];


    return
        $descriptions[$role_key]
        ?? '';
}


/*
|--------------------------------------------------------------------------
| Staff Notices
|--------------------------------------------------------------------------
*/

function storefleet_staff_notice(
    $notice
) {
    $messages = [

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        'staff-created' => [
            'type' =>
                'success',

            'message' =>
                'Staff member and login account created successfully.',
        ],


        'staff-updated' => [
            'type' =>
                'success',

            'message' =>
                'Staff details, roles and branch access updated successfully.',
        ],


        'staff-suspended' => [
            'type' =>
                'success',

            'message' =>
                'Staff member suspended and logged out.',
        ],


        'staff-reactivated' => [
            'type' =>
                'success',

            'message' =>
                'Staff member reactivated successfully.',
        ],


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        'missing-name' => [
            'type' =>
                'error',

            'message' =>
                'First name is required.',
        ],


        'invalid-email' => [
            'type' =>
                'error',

            'message' =>
                'Enter a valid email address.',
        ],


        'email-exists' => [
            'type' =>
                'error',

            'message' =>
                'A WordPress account already uses that email address.',
        ],


        'missing-phone' => [
            'type' =>
                'error',

            'message' =>
                'Phone number is required.',
        ],


        'weak-password' => [
            'type' =>
                'error',

            'message' =>
                'Temporary password must contain at least 10 characters.',
        ],


        'invalid-role' => [
            'type' =>
                'error',

            'message' =>
                'Select at least one valid staff role.',
        ],


        'no-branch' => [
            'type' =>
                'error',

            'message' =>
                'Each selected role must have at least one branch unless that role uses All Branches.',
        ],


        'invalid-branch' => [
            'type' =>
                'error',

            'message' =>
                'One of the selected branches is invalid.',
        ],


        'invalid-staff' => [
            'type' =>
                'error',

            'message' =>
                'The selected staff member is invalid.',
        ],


        /*
        |--------------------------------------------------------------------------
        | Errors
        |--------------------------------------------------------------------------
        */

        'user-error' => [
            'type' =>
                'error',

            'message' =>
                'The staff login account could not be created.',
        ],


        'staff-error' => [
            'type' =>
                'error',

            'message' =>
                'The staff member could not be saved.',
        ],


        'update-error' => [
            'type' =>
                'error',

            'message' =>
                'The staff member could not be updated.',
        ],
    ];


    if (
        !isset(
            $messages[
                $notice
            ]
        )
    ) {
        return;
    }


    $item =
        $messages[
            $notice
        ];


    $class =
        $item['type']
        === 'success'
            ? 'storefleet-notice-success'
            : 'storefleet-notice-error';

    ?>

    <div
        class="
            storefleet-notice
            <?php echo esc_attr($class); ?>
        "
    >
        <?php

        echo esc_html(
            $item['message']
        );

        ?>
    </div>

    <?php
}