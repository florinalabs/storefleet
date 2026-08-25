<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Add Delivery To Merchant Dokan Navigation
|--------------------------------------------------------------------------
|
| Staff navigation continues to come from:
|
| dokan-staff-navigation.php
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
    | Logged In
    |--------------------------------------------------------------------------
    */

    if (!is_user_logged_in()) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Or Staff
    |--------------------------------------------------------------------------
    */

    $is_merchant =
        storefleet_delivery_is_merchant_owner();

    $is_staff =
        storefleet_delivery_is_staff();

    if (
        !$is_merchant
        &&
        !$is_staff
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Dokan React Dashboard
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
    | StoreFleet Authorization
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
    | Inline Delivery Styles
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
    margin-bottom: 20px;
    padding: 14px 18px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
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
    margin: 0;
}

.storefleet-delivery-branch-select {
    min-width: 220px;
    min-height: 38px;
    padding: 7px 34px 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
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
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 700;
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
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.storefleet-delivery-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
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

.storefleet-delivery-stat-label {
    margin-bottom: 4px;
    color: #6b7280;
    font-size: 13px;
}

.storefleet-delivery-stat-value {
    color: #111827;
    font-size: 25px;
    font-weight: 700;
}

.storefleet-delivery-panel {
    overflow: hidden;
    background: #fff;
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
}

.storefleet-delivery-control {
    width: 100%;
    min-height: 42px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #fff;
    color: #374151;
}

.storefleet-delivery-control:disabled {
    background: #f9fafb;
    color: #9ca3af;
}

.storefleet-delivery-search-wrap {
    position: relative;
}

.storefleet-delivery-search {
    padding-left: 12px;
}

.storefleet-delivery-button {
    min-height: 42px;
    padding: 0 17px;
    border: 0;
    border-radius: 7px;
    background: #7047eb;
    color: #fff;
    font-weight: 600;
}

.storefleet-delivery-button:disabled {
    opacity: .55;
}

.storefleet-delivery-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.storefleet-delivery-table th {
    padding: 13px 18px;
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
    color: #4b5563;
    font-size: 12px;
    text-align: left;
    text-transform: uppercase;
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
}

.storefleet-delivery-empty p {
    max-width: 460px;
    margin: 0;
    color: #6b7280;
}

.storefleet-delivery-footer {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 13px;
}

.storefleet-delivery-branch-label {
    font-weight: 600;
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

    .storefleet-delivery-header,
    .storefleet-delivery-merchant-branch-context {
        flex-direction: column;
    }

    .storefleet-delivery-branch-select {
        width: 100%;
    }

    .storefleet-delivery-stats,
    .storefleet-delivery-toolbar {
        grid-template-columns: 1fr;
    }
}

CSS;

    wp_add_inline_style(
        'dokan-react-frontend',
        $styles
    );


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Permission Payload
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
    | React Dashboard
    |--------------------------------------------------------------------------
    */

    $script = <<<'JS'
(function () {
    'use strict';

    if (
        !window.wp ||
        !window.wp.hooks ||
        !window.wp.element ||
        !window.storefleetDokanDeliveryPermissions
    ) {
        return;
    }

    var permissions =
        window.storefleetDokanDeliveryPermissions;

    var createElement =
        window.wp.element.createElement;


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
                null,

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
            Array.isArray(
                permissions.branches
            )
                ? permissions.branches
                : [];

        var branchName =
            permissions.branchName ||
            'No active branch';

        var control;

        if (branches.length <= 1) {
            control =
                createElement(
                    'strong',
                    null,
                    branchName
                );
        } else {
            control =
                createElement(
                    'form',
                    {
                        method:
                            'post',

                        className:
                            'storefleet-delivery-branch-switch-form'
                    },

                    createElement(
                        'input',
                        {
                            type:
                                'hidden',

                            name:
                                'storefleet_delivery_action',

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
                                String(
                                    permissions.branchId || ''
                                ),

                            onChange:
                                function (event) {
                                    event.currentTarget.form.submit();
                                }
                        },

                        branches.map(
                            function (branch) {
                                return createElement(
                                    'option',
                                    {
                                        key:
                                            branch.id,

                                        value:
                                            String(
                                                branch.id
                                            )
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

            control
        );
    }


    function StoreFleetDelivery() {
        var branchName =
            permissions.branchName ||
            'Selected branch';

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

            merchantBranchSelector(),

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

            createElement(
                'div',
                {
                    className:
                        'storefleet-delivery-panel'
                },

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
                                    true
                            },

                            createElement(
                                'option',
                                null,
                                'All Statuses'
                            )
                        ),

                        createElement(
                            'select',
                            {
                                className:
                                    'storefleet-delivery-control',

                                disabled:
                                    true
                            },

                            createElement(
                                'option',
                                null,
                                'All Riders'
                            )
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
                        ),

                        permissions.manage
                            ? createElement(
                                'button',
                                {
                                    type:
                                        'button',

                                    className:
                                        'storefleet-delivery-button',

                                    disabled:
                                        true
                                },
                                '+ Assign Delivery'
                            )
                            : null
                    )
                ),

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
                ),

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
    | Register Dokan Route
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
                            route
                            &&
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