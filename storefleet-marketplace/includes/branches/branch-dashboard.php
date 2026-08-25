<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Register Dokan Query Variable
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_query_var_filter',
    function ($query_vars) {

        $query_vars['storefleet-branches'] =
            'storefleet-branches';

        return $query_vars;
    }
);


/*
|--------------------------------------------------------------------------
| Add Branches Menu
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_get_dashboard_nav',
    function ($menus) {

        if (!storefleet_is_merchant_owner()) {
            return $menus;
        }

        $menus['storefleet-branches'] = [
            'title' => __(
                'Branches',
                'storefleet-marketplace'
            ),

            'icon' =>
                '<i class="fas fa-map-marker-alt"></i>',

            'url' =>
                dokan_get_navigation_url(
                    'storefleet-branches'
                ),

            'pos' => 55,
        ];

        return $menus;
    },
    50
);


/*
|--------------------------------------------------------------------------
| Active Branch Navigation
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
                    'storefleet-branches'
                ]
            )
        ) {
            return 'storefleet-branches';
        }

        return $active_menu;
    },
    10,
    3
);


/*
|--------------------------------------------------------------------------
| Render Branches
|--------------------------------------------------------------------------
*/

add_action(
    'dokan_load_custom_template',
    'storefleet_render_branches_dashboard'
);


function storefleet_render_branches_dashboard(
    $query_vars
) {
    if (
        empty($query_vars) ||
        !array_key_exists(
            'storefleet-branches',
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


    $branches =
        storefleet_get_merchant_branches(
            $merchant_id
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
                    'Branches',
                    'storefleet-marketplace'
                );
                ?>
            </h1>

            <p>
                Manage your store locations,
                pickup addresses and branch contacts.
            </p>

        </div>


        <?php
        storefleet_branch_notice(
            $notice
        );
        ?>


        <div class="storefleet-card">

            <div class="storefleet-card-header">

                <h2>
                    Your Branches
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php if (empty($branches)) : ?>

                    <p>
                        No branches have been created yet.
                    </p>

                <?php else : ?>

                    <table class="storefleet-table">

                        <thead>

                            <tr>
                                <th>Branch</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th>Status</th>
                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($branches as $branch) : ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $branch->name
                                        );
                                        ?>
                                    </strong>

                                </td>


                                <td>

                                    <?php
                                    echo esc_html(
                                        storefleet_get_branch_address(
                                            $branch
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo esc_html(
                                        $branch->contact_name
                                    );
                                    ?>

                                    <?php
                                    if (
                                        !empty(
                                            $branch->contact_phone
                                        )
                                    ) :
                                    ?>

                                        <br>

                                        <?php
                                        echo esc_html(
                                            $branch->contact_phone
                                        );
                                        ?>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php
                                    if (
                                        (int)
                                        $branch->is_active
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
                    Add Branch
                </h2>

            </div>


            <div class="storefleet-card-body">

                <?php
                storefleet_render_branch_form();
                ?>

            </div>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| Branch Form
|--------------------------------------------------------------------------
*/

function storefleet_render_branch_form()
{
    ?>

    <form method="post">

        <?php

        wp_nonce_field(
            'storefleet_create_branch',
            'storefleet_branch_nonce'
        );

        ?>

        <input
            type="hidden"
            name="storefleet_branch_action"
            value="create"
        >


        <div class="storefleet-form-grid">


            <div class="storefleet-form-group">

                <label for="branch_name">
                    Branch Name *
                </label>

                <input
                    id="branch_name"
                    type="text"
                    name="branch_name"
                    class="storefleet-form-control"
                    placeholder="Makati Branch"
                    required
                >

            </div>


            <div class="storefleet-form-group">

                <label for="contact_name">
                    Pickup Contact Name
                </label>

                <input
                    id="contact_name"
                    type="text"
                    name="contact_name"
                    class="storefleet-form-control"
                >

            </div>


            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label for="address_line_1">
                    Address Line 1 *
                </label>

                <input
                    id="address_line_1"
                    type="text"
                    name="address_line_1"
                    class="storefleet-form-control"
                    placeholder="Building, house number, street"
                    required
                >

            </div>


            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label for="address_line_2">
                    Address Line 2
                </label>

                <input
                    id="address_line_2"
                    type="text"
                    name="address_line_2"
                    class="storefleet-form-control"
                    placeholder="Barangay, subdivision, unit or floor"
                >

            </div>


            <div class="storefleet-form-group">

                <label for="city">
                    City / Municipality *
                </label>

                <input
                    id="city"
                    type="text"
                    name="city"
                    class="storefleet-form-control"
                    placeholder="Makati City"
                    required
                >

            </div>


            <div class="storefleet-form-group">

                <label for="state">
                    Province / State *
                </label>

                <input
                    id="state"
                    type="text"
                    name="state"
                    class="storefleet-form-control"
                    placeholder="Metro Manila"
                    required
                >

            </div>


            <div class="storefleet-form-group">

                <label for="postcode">
                    Postal Code
                </label>

                <input
                    id="postcode"
                    type="text"
                    name="postcode"
                    class="storefleet-form-control"
                    placeholder="1210"
                >

            </div>


            <div class="storefleet-form-group">

                <label for="contact_phone">
                    Pickup Contact Phone
                </label>

                <input
                    id="contact_phone"
                    type="text"
                    name="contact_phone"
                    class="storefleet-form-control"
                    placeholder="+63 917 123 4567"
                >

            </div>


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
                            margin-bottom: 4px;
                        "
                    >
                        Automatic Location
                    </strong>


                    <p
                        style="
                            margin: 0;
                            color: #6b7280;
                            font-size: 13px;
                            line-height: 1.5;
                        "
                    >
                        StoreFleet automatically determines
                        the branch latitude and longitude
                        from the address above.
                    </p>

                </div>

            </div>


            <div
                class="
                    storefleet-form-group
                    storefleet-form-group-full
                "
            >

                <label>

                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        checked
                    >

                    Active Branch

                </label>

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
                        Add Branch
                    </button>

                </div>

            </div>

        </div>

    </form>

    <?php
}


/*
|--------------------------------------------------------------------------
| Notices
|--------------------------------------------------------------------------
*/

function storefleet_branch_notice($notice)
{
    /*
    |--------------------------------------------------------------------------
    | Branch Created
    |--------------------------------------------------------------------------
    */

    if ($notice === 'branch-created') {

        ?>

        <div
            class="
                storefleet-notice
                storefleet-notice-success
            "
        >
            Branch created successfully.
            StoreFleet automatically saved
            its delivery coordinates.
        </div>

        <?php

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Missing Name
    |--------------------------------------------------------------------------
    */

    if ($notice === 'missing-name') {

        ?>

        <div
            class="
                storefleet-notice
                storefleet-notice-error
            "
        >
            Branch name is required.
        </div>

        <?php

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Missing Address
    |--------------------------------------------------------------------------
    */

    if ($notice === 'missing-address') {

        ?>

        <div
            class="
                storefleet-notice
                storefleet-notice-error
            "
        >
            Street address, city, and province
            are required.
        </div>

        <?php

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Geocoding Failed
    |--------------------------------------------------------------------------
    */

    if ($notice === 'geocode-failed') {

        ?>

        <div
            class="
                storefleet-notice
                storefleet-notice-error
            "
        >
            We could not locate this branch address.
            Please check the street, city, province,
            and postal code and try again.
        </div>

        <?php

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Branch Save Error
    |--------------------------------------------------------------------------
    */

    if ($notice === 'branch-error') {

        ?>

        <div
            class="
                storefleet-notice
                storefleet-notice-error
            "
        >
            The branch could not be saved.
        </div>

        <?php

        return;
    }
}