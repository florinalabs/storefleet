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

        $query_vars['storefleet-staff'] =
            'storefleet-staff';

        return $query_vars;
    }
);


/*
|--------------------------------------------------------------------------
| Add Staff to Dokan Navigation
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


    if (!storefleet_is_merchant_owner()) {
        return;
    }


    $merchant_id =
        storefleet_get_current_merchant_id();


    $staff =
        storefleet_get_merchant_staff(
            $merchant_id
        );


    $branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            true
        );


    $notice =
        isset($_GET['sf_notice'])
            ? sanitize_key(
                wp_unslash(
                    $_GET['sf_notice']
                )
            )
            : '';

    ?>

    <div class="storefleet-dashboard-page">

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
                Add employees, assign roles and control
                which branches they belong to.
            </p>

        </div>


        <?php
        storefleet_staff_notice(
            $notice
        );
        ?>


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
                                <th>Role</th>
                                <th>Branches</th>
                                <th>Status</th>
                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($staff as $member) : ?>

                            <?php

                            $branch_names =
                                storefleet_get_staff_branch_names(
                                    $member->id
                                );

                            ?>

                            <tr>

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


                                <td>

                                    <?php
                                    echo esc_html(
                                        $member->user_login
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo esc_html(
                                        storefleet_get_staff_role_label(
                                            $member->staff_role
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php

                                    if (empty($branch_names)) {

                                        echo '—';

                                    } else {

                                        echo esc_html(
                                            implode(
                                                ', ',
                                                $branch_names
                                            )
                                        );
                                    }

                                    ?>

                                </td>


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
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


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

    <?php
}


/*
|--------------------------------------------------------------------------
| Staff Form
|--------------------------------------------------------------------------
*/

function storefleet_render_staff_form(
    $branches
) {
    $roles =
        storefleet_get_staff_roles();

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


            <div class="storefleet-form-group">

                <label for="first_name">
                    First Name *
                </label>

                <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    class="storefleet-form-control"
                    required
                >

            </div>


            <div class="storefleet-form-group">

                <label for="last_name">
                    Last Name
                </label>

                <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    class="storefleet-form-control"
                >

            </div>


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
                    required
                >

            </div>


            <div class="storefleet-form-group">

                <label for="staff_password">
                    Password *
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


            <div class="storefleet-form-group">

                <label for="staff_role">
                    Staff Role *
                </label>

                <select
                    id="staff_role"
                    name="staff_role"
                    class="storefleet-form-control"
                    required
                >

                    <option value="">
                        Select a role
                    </option>

                    <?php foreach ($roles as $key => $label) : ?>

                        <option
                            value="<?php echo esc_attr($key); ?>"
                        >
                            <?php
                            echo esc_html($label);
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label>
                    Branch Access *
                </label>

                <div class="storefleet-checkbox-list">

                    <?php foreach ($branches as $branch) : ?>

                        <label
                            class="storefleet-checkbox-item"
                        >

                            <input
                                type="checkbox"
                                name="branch_ids[]"
                                value="<?php
                                    echo esc_attr(
                                        $branch->id
                                    );
                                ?>"
                            >

                            <span>
                                <?php
                                echo esc_html(
                                    $branch->name
                                );
                                ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

                <small class="storefleet-help">
                    Select one or more branches this
                    employee is allowed to work in.
                </small>

            </div>


            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <div>

                    <button
                        type="submit"
                        class="storefleet-button"
                    >
                        Add Staff Member
                    </button>

                </div>

            </div>

        </div>

    </form>

    <?php
}


/*
|--------------------------------------------------------------------------
| Staff Notices
|--------------------------------------------------------------------------
*/

function storefleet_staff_notice($notice)
{
    $messages = [
        'staff-created' => [
            'type' =>
                'success',

            'message' =>
                'Staff member created successfully.',
        ],

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

        'weak-password' => [
            'type' =>
                'error',

            'message' =>
                'Password must contain at least 10 characters.',
        ],

        'invalid-role' => [
            'type' =>
                'error',

            'message' =>
                'Select a valid staff role.',
        ],

        'no-branch' => [
            'type' =>
                'error',

            'message' =>
                'Select at least one branch.',
        ],

        'invalid-branch' => [
            'type' =>
                'error',

            'message' =>
                'One of the selected branches is invalid.',
        ],

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
    ];


    if (
        !isset(
            $messages[$notice]
        )
    ) {
        return;
    }


    $item =
        $messages[$notice];


    $class =
        $item['type'] === 'success'
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