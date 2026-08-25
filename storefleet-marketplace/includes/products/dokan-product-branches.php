<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Dokan Product Editor → StoreFleet Branch Availability
|--------------------------------------------------------------------------
|
| Adds StoreFleet branch availability fields to Dokan 5's modern React
| Product Editor.
|
| Fields:
|
| storefleet_branch_mode
| storefleet_branch_ids
|
| These are UI / request fields only.
|
| Persistence remains:
|
| _storefleet_branch_mode
|
| +
|
| wp_storefleet_product_branches
|
| through:
|
| storefleet_set_product_branches()
|
*/


/*
|--------------------------------------------------------------------------
| Field IDs
|--------------------------------------------------------------------------
*/

function storefleet_dokan_product_branch_mode_field_id()
{
    return
        'storefleet_branch_mode';
}


function storefleet_dokan_product_branch_ids_field_id()
{
    return
        'storefleet_branch_ids';
}


function storefleet_dokan_product_branch_section_id()
{
    return
        'storefleet_branch_availability';
}


/*
|--------------------------------------------------------------------------
| Normalize Submitted Mode
|--------------------------------------------------------------------------
*/

function storefleet_dokan_normalize_submitted_branch_mode(
    $mode
) {
    if (
        is_array(
            $mode
        )
    ) {
        if (
            count(
                $mode
            ) !== 1
        ) {
            return '';
        }

        $mode =
            reset(
                $mode
            );
    }

    $mode =
        sanitize_key(
            (string) $mode
        );

    if (
        !in_array(
            $mode,
            [
                'all',
                'selected',
            ],
            true
        )
    ) {
        return '';
    }

    return
        $mode;
}


/*
|--------------------------------------------------------------------------
| Normalize Submitted Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_dokan_normalize_submitted_branch_ids(
    $branch_ids
) {
    $normalized =
        [];

    foreach (
        (array) $branch_ids
        as $branch_id
    ) {
        /*
        |--------------------------------------------------------------------------
        | Support Select Option Objects
        |--------------------------------------------------------------------------
        |
        | Defensive support for:
        |
        | [
        |   {
        |     value: 1,
        |     label: "Makati"
        |   }
        | ]
        |
        */

        if (
            is_array(
                $branch_id
            )
            &&
            isset(
                $branch_id['value']
            )
        ) {
            $branch_id =
                $branch_id['value'];
        }

        $branch_id =
            absint(
                $branch_id
            );

        if (!$branch_id) {
            continue;
        }

        $normalized[] =
            $branch_id;
    }

    return
        array_values(
            array_unique(
                $normalized
            )
        );
}


/*
|--------------------------------------------------------------------------
| Get Manageable Branch IDs
|--------------------------------------------------------------------------
*/

function storefleet_dokan_get_manageable_product_branch_ids(
    $product_id
) {
    if (
        !function_exists(
            'storefleet_get_current_user_manageable_product_branches'
        )
    ) {
        return [];
    }

    $branches =
        storefleet_get_current_user_manageable_product_branches(
            $product_id
        );

    if (
        empty(
            $branches
        )
    ) {
        return [];
    }

    $branch_ids =
        [];

    foreach (
        $branches as $branch
    ) {
        $branch_id =
            absint(
                $branch->id
                ?? 0
            );

        if ($branch_id) {
            $branch_ids[] =
                $branch_id;
        }
    }

    return
        array_values(
            array_unique(
                $branch_ids
            )
        );
}


/*
|--------------------------------------------------------------------------
| Current User Can Manage Entire Existing Configuration
|--------------------------------------------------------------------------
|
| Merchant owner / full manager:
|
|     yes
|
| Limited staff:
|
|     Existing "all" configuration is read-only.
|
|     Existing "selected" configuration is editable only when EVERY currently
|     selected branch falls inside the staff member's products.branches.manage
|     scope.
|
| This prevents a Makati-only staff member from accidentally deleting an
| existing BGC assignment they are not authorized to manage.
|
*/

function storefleet_dokan_user_can_manage_entire_branch_configuration(
    $product_id,
    $is_new_product = false
) {
    $product_id =
        absint(
            $product_id
        );

    if (!$product_id) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Merchant Owner
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_is_merchant_owner'
        )
        &&
        storefleet_is_merchant_owner()
    ) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | New Product
    |--------------------------------------------------------------------------
    |
    | Staff may create the product inside one or more branches they are
    | actually authorized to manage.
    |
    */

    if ($is_new_product) {
        return
            !empty(
                storefleet_dokan_get_manageable_product_branch_ids(
                    $product_id
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Existing All-Branches Product
    |--------------------------------------------------------------------------
    |
    | Only users authorized to set "all" may change this configuration.
    |
    */

    $mode =
        storefleet_get_product_branch_mode(
            $product_id
        );

    if ($mode === 'all') {
        return
            function_exists(
                'storefleet_current_user_can_set_product_all_branches'
            )
            &&
            storefleet_current_user_can_set_product_all_branches(
                $product_id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Existing Selected Product
    |--------------------------------------------------------------------------
    */

    $selected_branch_ids =
        storefleet_get_product_branch_ids(
            $product_id
        );

    if (
        empty(
            $selected_branch_ids
        )
    ) {
        return false;
    }

    foreach (
        $selected_branch_ids
        as $branch_id
    ) {
        if (
            !storefleet_current_user_can_manage_product_branch(
                $product_id,
                $branch_id
            )
        ) {
            return false;
        }
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Default Branch For New Staff Product
|--------------------------------------------------------------------------
*/

function storefleet_dokan_get_default_new_product_branch_id(
    $product_id
) {
    $manageable_branch_ids =
        storefleet_dokan_get_manageable_product_branch_ids(
            $product_id
        );

    if (
        empty(
            $manageable_branch_ids
        )
    ) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Current StoreFleet Branch First
    |--------------------------------------------------------------------------
    */

    if (
        function_exists(
            'storefleet_get_current_staff_branch_id'
        )
    ) {
        $current_branch_id =
            absint(
                storefleet_get_current_staff_branch_id()
            );

        if (
            $current_branch_id
            &&
            in_array(
                $current_branch_id,
                $manageable_branch_ids,
                true
            )
        ) {
            return
                $current_branch_id;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Fallback To First Authorized Branch
    |--------------------------------------------------------------------------
    */

    return
        absint(
            reset(
                $manageable_branch_ids
            )
        );
}


/*
|--------------------------------------------------------------------------
| Add Branch Availability To Dokan Product Editor
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_product_editor_args',
    'storefleet_add_dokan_product_branch_editor_fields',
    20,
    3
);


function storefleet_add_dokan_product_branch_editor_fields(
    $args,
    $product_id,
    $product
) {
    $product_id =
        absint(
            $product_id
        );

    if (
        !$product_id
        ||
        !is_array(
            $args
        )
    ) {
        return
            $args;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Product Backend Required
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_get_product_branch_mode'
        )
        ||
        !function_exists(
            'storefleet_get_product_branch_ids'
        )
        ||
        !function_exists(
            'storefleet_current_user_can_access_product_merchant'
        )
    ) {
        return
            $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Merchant Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
        return
            $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Editor Arrays
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $args['form_items']
        )
        ||
        !is_array(
            $args['form_items']
        )
    ) {
        $args['form_items'] =
            [];
    }

    if (
        !isset(
            $args['form_layouts']
        )
        ||
        !is_array(
            $args['form_layouts']
        )
    ) {
        $args['form_layouts'] =
            [];
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Injection
    |--------------------------------------------------------------------------
    */

    foreach (
        $args['form_items']
        as $existing_item
    ) {
        if (
            (
                $existing_item['id']
                ?? ''
            )
            ===
            storefleet_dokan_product_branch_section_id()
        ) {
            return
                $args;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    $is_new_product =
        !empty(
            $args['is_new_product']
        );

    $mode =
        storefleet_get_product_branch_mode(
            $product_id
        );

    $selected_branch_ids =
        storefleet_get_product_branch_ids(
            $product_id
        );

    $manageable_branches =
        function_exists(
            'storefleet_get_current_user_manageable_product_branches'
        )
            ? storefleet_get_current_user_manageable_product_branches(
                $product_id
            )
            : [];

    $can_set_all =
        function_exists(
            'storefleet_current_user_can_set_product_all_branches'
        )
        &&
        storefleet_current_user_can_set_product_all_branches(
            $product_id
        );

    $can_manage_configuration =
        storefleet_dokan_user_can_manage_entire_branch_configuration(
            $product_id,
            $is_new_product
        );


    /*
    |--------------------------------------------------------------------------
    | Staff New Product Default
    |--------------------------------------------------------------------------
    |
    | Merchant owner may default to All Branches.
    |
    | Scoped staff who cannot choose All Branches start with their current
    | StoreFleet branch selected.
    |
    */

    if (
        $is_new_product
        &&
        !$can_set_all
    ) {
        $mode =
            'selected';

        $default_branch_id =
            storefleet_dokan_get_default_new_product_branch_id(
                $product_id
            );

        $selected_branch_ids =
            $default_branch_id
                ? [
                    $default_branch_id,
                ]
                : [];
    }


    /*
    |--------------------------------------------------------------------------
    | Section
    |--------------------------------------------------------------------------
    */

    $args['form_items'][] =
        [
            'type' =>
                'section',

            'id' =>
                storefleet_dokan_product_branch_section_id(),

            'label' =>
                __(
                    'Branch Availability',
                    'storefleet-marketplace'
                ),

            'description' =>
                __(
                    'Choose which StoreFleet branches sell this product.',
                    'storefleet-marketplace'
                ),
        ];


    /*
    |--------------------------------------------------------------------------
    | Read-Only Configuration
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Product = All Branches
    |
    | Inventory Staff = Makati only
    |
    | That employee must not convert the product into a Makati-only product
    | because doing so would remove BGC without authorization.
    |
    */

    if (
        !$can_manage_configuration
        &&
        !$is_new_product
    ) {
        $args['form_items'][] =
            [
                'type' =>
                    'field',

                'id' =>
                    storefleet_dokan_product_branch_mode_field_id(),

                'label' =>
                    __(
                        'Availability',
                        'storefleet-marketplace'
                    ),

                'variant' =>
                    'radio',

                'section_id' =>
                    storefleet_dokan_product_branch_section_id(),

                'value' =>
                    $mode,

                'options' =>
                    [
                        [
                            'value' =>
                                $mode,

                            'label' =>
                                $mode === 'all'
                                    ? __(
                                        'All Branches',
                                        'storefleet-marketplace'
                                    )
                                    : __(
                                        'Selected Branches',
                                        'storefleet-marketplace'
                                    ),
                        ],
                    ],

                'disabled' =>
                    true,

                'description' =>
                    __(
                        'This product has branch assignments outside your authorized branch-management scope.',
                        'storefleet-marketplace'
                    ),
            ];

        $args['form_layouts'][] =
            [
                'id' =>
                    storefleet_dokan_product_branch_section_id(),

                'parent_id' =>
                    'primary_column',

                'priority' =>
                    30,

                'layout' =>
                    [
                        'type' =>
                            'card',

                        'withHeader' =>
                            true,

                        'isCollapsible' =>
                            false,
                    ],

                'children' =>
                    [
                        storefleet_dokan_product_branch_mode_field_id(),
                    ],
            ];

        return
            $args;
    }


    /*
    |--------------------------------------------------------------------------
    | Availability Options
    |--------------------------------------------------------------------------
    */

    $mode_options =
        [];

    if ($can_set_all) {
        $mode_options[] =
            [
                'value' =>
                    'all',

                'label' =>
                    __(
                        'All Branches',
                        'storefleet-marketplace'
                    ),
            ];
    }

    if (
        !empty(
            $manageable_branches
        )
    ) {
        $mode_options[] =
            [
                'value' =>
                    'selected',

                'label' =>
                    __(
                        'Selected Branches',
                        'storefleet-marketplace'
                    ),
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | Mode Field
    |--------------------------------------------------------------------------
    */

    $args['form_items'][] =
        [
            'type' =>
                'field',

            'id' =>
                storefleet_dokan_product_branch_mode_field_id(),

            'label' =>
                __(
                    'Availability',
                    'storefleet-marketplace'
                ),

            'variant' =>
                'radio',

            'section_id' =>
                storefleet_dokan_product_branch_section_id(),

            'value' =>
                $mode,

            'options' =>
                $mode_options,

            'required' =>
                true,

            'description' =>
                __(
                    'Use All Branches for merchant-wide availability, or select specific StoreFleet branches.',
                    'storefleet-marketplace'
                ),
        ];


    /*
    |--------------------------------------------------------------------------
    | Branch Options
    |--------------------------------------------------------------------------
    */

    $branch_options =
        [];

    foreach (
        $manageable_branches
        as $branch
    ) {
        $branch_id =
            absint(
                $branch->id
                ?? 0
            );

        if (!$branch_id) {
            continue;
        }

        $branch_options[] =
            [
                'value' =>
                    $branch_id,

                'label' =>
                    sanitize_text_field(
                        $branch->name
                        ?? (
                            'Branch #' .
                            $branch_id
                        )
                    ),
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branches Field
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $branch_options
        )
    ) {
        $args['form_items'][] =
            [
                'type' =>
                    'field',

                'id' =>
                    storefleet_dokan_product_branch_ids_field_id(),

                'label' =>
                    __(
                        'Branches',
                        'storefleet-marketplace'
                    ),

                'variant' =>
                    'multiselect',

                'section_id' =>
                    storefleet_dokan_product_branch_section_id(),

                'value' =>
                    array_values(
                        array_map(
                            'absint',
                            $selected_branch_ids
                        )
                    ),

                'options' =>
                    $branch_options,

                'required' =>
                    true,

                'placeholder' =>
                    __(
                        'Select branches',
                        'storefleet-marketplace'
                    ),

                'dependencies' =>
                    [
                        [
                            'key' =>
                                storefleet_dokan_product_branch_mode_field_id(),

                            'comparison' =>
                                '==',

                            'value' =>
                                'selected',
                        ],
                    ],

                'description' =>
                    __(
                        'Only branches you are authorized to manage are available here.',
                        'storefleet-marketplace'
                    ),
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Existing Dokan layout:
    |
    | General
    | Description
    | Branch Availability   ← StoreFleet
    | Inventory
    | Shipping
    |
    */

    $children =
        [
            storefleet_dokan_product_branch_mode_field_id(),
        ];

    if (
        !empty(
            $branch_options
        )
    ) {
        $children[] =
            storefleet_dokan_product_branch_ids_field_id();
    }

    $args['form_layouts'][] =
        [
            'id' =>
                storefleet_dokan_product_branch_section_id(),

            'parent_id' =>
                'primary_column',

            'priority' =>
                30,

            'layout' =>
                [
                    'type' =>
                        'card',

                    'withHeader' =>
                        true,

                    'isCollapsible' =>
                        false,
                ],

            'children' =>
                $children,
        ];

    return
        $args;
}


/*
|--------------------------------------------------------------------------
| Dokan V3 Product REST Request Detection
|--------------------------------------------------------------------------
*/

function storefleet_dokan_product_branch_rest_product_id(
    $request
) {
    if (
        !is_object(
            $request
        )
        ||
        !method_exists(
            $request,
            'get_route'
        )
    ) {
        return 0;
    }

    $route =
        (string) $request->get_route();

    if (
        !preg_match(
            '#^/dokan/v3/products/([0-9]+)$#',
            $route,
            $matches
        )
    ) {
        return 0;
    }

    return
        absint(
            $matches[1]
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/

function storefleet_dokan_product_branch_request_data(
    $request
) {
    if (
        !is_object(
            $request
        )
    ) {
        return [];
    }

    $data =
        method_exists(
            $request,
            'get_json_params'
        )
            ? $request->get_json_params()
            : [];

    if (
        !is_array(
            $data
        )
    ) {
        $data =
            [];
    }

    return
        $data;
}


/*
|--------------------------------------------------------------------------
| Pending Validated REST Payload
|--------------------------------------------------------------------------
*/

function storefleet_dokan_set_pending_product_branch_payload(
    $product_id,
    $payload
) {
    $product_id =
        absint(
            $product_id
        );

    if (!$product_id) {
        return;
    }

    if (
        !isset(
            $GLOBALS[
                'storefleet_dokan_product_branch_payloads'
            ]
        )
        ||
        !is_array(
            $GLOBALS[
                'storefleet_dokan_product_branch_payloads'
            ]
        )
    ) {
        $GLOBALS[
            'storefleet_dokan_product_branch_payloads'
        ] =
            [];
    }

    $GLOBALS[
        'storefleet_dokan_product_branch_payloads'
    ][
        $product_id
    ] =
        $payload;
}


function storefleet_dokan_get_pending_product_branch_payload(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    return
        $GLOBALS[
            'storefleet_dokan_product_branch_payloads'
        ][
            $product_id
        ]
        ?? null;
}


function storefleet_dokan_clear_pending_product_branch_payload(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (!$product_id) {
        return;
    }

    unset(
        $GLOBALS[
            'storefleet_dokan_product_branch_payloads'
        ][
            $product_id
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Auto-Draft Product Detection
|--------------------------------------------------------------------------
|
| Dokan creates an auto-draft product before rendering its React create form.
|
| This matters because an auto-draft with no StoreFleet branch meta should NOT
| allow scoped staff to exploit the backwards-compatible "missing meta = all"
| rule.
|
*/

function storefleet_dokan_product_branch_is_unsaved_auto_draft(
    $product_id
) {
    $product_id =
        absint(
            $product_id
        );

    if (!$product_id) {
        return false;
    }

    if (
        get_post_status(
            $product_id
        ) !== 'auto-draft'
    ) {
        return false;
    }

    return
        !metadata_exists(
            'post',
            $product_id,
            storefleet_product_branch_mode_meta_key()
        );
}


/*
|--------------------------------------------------------------------------
| Validate Product Branch Payload Before Dokan Saves Product
|--------------------------------------------------------------------------
|
| UI hiding is not authorization.
|
| Validation happens BEFORE Dokan/WooCommerce processes the product update.
|
*/

add_filter(
    'rest_pre_dispatch',
    'storefleet_validate_dokan_product_branch_rest_request',
    9,
    3
);


function storefleet_validate_dokan_product_branch_rest_request(
    $result,
    $server,
    $request
) {
    $product_id =
        storefleet_dokan_product_branch_rest_product_id(
            $request
        );

    if (!$product_id) {
        return
            $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Write Requests Only
    |--------------------------------------------------------------------------
    */

    $method =
        strtoupper(
            (string) $request->get_method()
        );

    if (
        !in_array(
            $method,
            [
                'POST',
                'PUT',
                'PATCH',
            ],
            true
        )
    ) {
        return
            $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Request Payload
    |--------------------------------------------------------------------------
    */

    $data =
        storefleet_dokan_product_branch_request_data(
            $request
        );

    $mode_field =
        storefleet_dokan_product_branch_mode_field_id();

    $branches_field =
        storefleet_dokan_product_branch_ids_field_id();

    $has_mode =
        array_key_exists(
            $mode_field,
            $data
        );

    $has_branches =
        array_key_exists(
            $branches_field,
            $data
        );


    /*
    |--------------------------------------------------------------------------
    | No StoreFleet Fields
    |--------------------------------------------------------------------------
    */

    if (
        !$has_mode
        &&
        !$has_branches
    ) {
        return
            $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Merchant Authorization
    |--------------------------------------------------------------------------
    */

    if (
        !function_exists(
            'storefleet_current_user_can_access_product_merchant'
        )
        ||
        !storefleet_current_user_can_access_product_merchant(
            $product_id
        )
    ) {
        return
            new WP_Error(
                'storefleet_product_branch_product_denied',
                __(
                    'You are not authorized to manage branch availability for this product.',
                    'storefleet-marketplace'
                ),
                [
                    'status' =>
                        403,
                ]
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Existing State
    |--------------------------------------------------------------------------
    */

    $existing_mode =
        storefleet_get_product_branch_mode(
            $product_id
        );

    $submitted_mode =
        $has_mode
            ? storefleet_dokan_normalize_submitted_branch_mode(
                $data[
                    $mode_field
                ]
            )
            : $existing_mode;

    if ($submitted_mode === '') {
        return
            new WP_Error(
                'storefleet_invalid_product_branch_mode',
                __(
                    'Invalid product branch availability mode.',
                    'storefleet-marketplace'
                ),
                [
                    'status' =>
                        400,
                ]
            );
    }


    /*
    |--------------------------------------------------------------------------
    | All Branches
    |--------------------------------------------------------------------------
    */

    if ($submitted_mode === 'all') {
        $is_new_auto_draft =
            storefleet_dokan_product_branch_is_unsaved_auto_draft(
                $product_id
            );

        $can_set_all =
            function_exists(
                'storefleet_current_user_can_set_product_all_branches'
            )
            &&
            storefleet_current_user_can_set_product_all_branches(
                $product_id
            );


        /*
        |--------------------------------------------------------------------------
        | Existing All → All
        |--------------------------------------------------------------------------
        |
        | Limited staff may save unrelated product fields without changing the
        | existing branch configuration.
        |
        */

        if (
            $existing_mode === 'all'
            &&
            !$is_new_auto_draft
            &&
            !$can_set_all
        ) {
            storefleet_dokan_set_pending_product_branch_payload(
                $product_id,
                [
                    'mode' =>
                        'all',

                    'branch_ids' =>
                        [],

                    'skip_save' =>
                        true,
                ]
            );

            return
                $result;
        }


        /*
        |--------------------------------------------------------------------------
        | New / Selected → All Requires Full Authorization
        |--------------------------------------------------------------------------
        */

        if (!$can_set_all) {
            return
                new WP_Error(
                    'storefleet_product_all_branches_denied',
                    __(
                        'You are not authorized to make this product available at all branches.',
                        'storefleet-marketplace'
                    ),
                    [
                        'status' =>
                            403,
                    ]
                );
        }

        storefleet_dokan_set_pending_product_branch_payload(
            $product_id,
            [
                'mode' =>
                    'all',

                'branch_ids' =>
                    [],

                'skip_save' =>
                    false,
            ]
        );

        return
            $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Branches
    |--------------------------------------------------------------------------
    */

    if (!$has_branches) {
        /*
        |--------------------------------------------------------------------------
        | Read-Only Existing Configuration
        |--------------------------------------------------------------------------
        |
        | The UI may contain only the disabled mode field.
        |
        | Preserve the current assignment without modification.
        |
        */

        if (
            $existing_mode === 'selected'
            &&
            $submitted_mode === 'selected'
        ) {
            storefleet_dokan_set_pending_product_branch_payload(
                $product_id,
                [
                    'mode' =>
                        'selected',

                    'branch_ids' =>
                        [],

                    'skip_save' =>
                        true,
                ]
            );

            return
                $result;
        }

        return
            new WP_Error(
                'storefleet_product_branches_required',
                __(
                    'Select at least one StoreFleet branch.',
                    'storefleet-marketplace'
                ),
                [
                    'status' =>
                        400,
                ]
            );
    }

    $branch_ids =
        storefleet_dokan_normalize_submitted_branch_ids(
            $data[
                $branches_field
            ]
        );

    if (
        empty(
            $branch_ids
        )
    ) {
        return
            new WP_Error(
                'storefleet_product_branches_required',
                __(
                    'Select at least one StoreFleet branch.',
                    'storefleet-marketplace'
                ),
                [
                    'status' =>
                        400,
                ]
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Every Submitted Branch
    |--------------------------------------------------------------------------
    */

    foreach (
        $branch_ids
        as $branch_id
    ) {
        if (
            !storefleet_current_user_can_manage_product_branch(
                $product_id,
                $branch_id
            )
        ) {
            return
                new WP_Error(
                    'storefleet_product_branch_denied',
                    sprintf(
                        __(
                            'You are not authorized to assign branch #%d to this product.',
                            'storefleet-marketplace'
                        ),
                        $branch_id
                    ),
                    [
                        'status' =>
                            403,
                    ]
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validated Payload
    |--------------------------------------------------------------------------
    */

    storefleet_dokan_set_pending_product_branch_payload(
        $product_id,
        [
            'mode' =>
                'selected',

            'branch_ids' =>
                $branch_ids,

            'skip_save' =>
                false,
        ]
    );

    return
        $result;
}


/*
|--------------------------------------------------------------------------
| Remove StoreFleet Fields From WooCommerce Product Payload
|--------------------------------------------------------------------------
|
| PayloadResolver leaves unknown fields in the resolved array.
|
| StoreFleet fields are not WooCommerce product properties, so remove them
| before WooCommerce receives the resolved product payload.
|
| Their already-validated values are held in the request-local pending payload
| above.
|
*/

add_filter(
    'dokan_product_editor_schema_payload',
    'storefleet_strip_branch_fields_from_dokan_product_payload',
    100
);


function storefleet_strip_branch_fields_from_dokan_product_payload(
    $payload
) {
    if (
        !is_array(
            $payload
        )
    ) {
        return
            $payload;
    }

    unset(
        $payload[
            storefleet_dokan_product_branch_mode_field_id()
        ]
    );

    unset(
        $payload[
            storefleet_dokan_product_branch_ids_field_id()
        ]
    );

    return
        $payload;
}


/*
|--------------------------------------------------------------------------
| Persist Branch Assignment After Successful Dokan Product Save
|--------------------------------------------------------------------------
|
| We validate BEFORE the Dokan callback.
|
| Only after Dokan successfully saves the WooCommerce product do we persist
| StoreFleet branch availability.
|
*/

add_filter(
    'rest_request_after_callbacks',
    'storefleet_save_dokan_product_branch_rest_payload',
    20,
    3
);


function storefleet_save_dokan_product_branch_rest_payload(
    $response,
    $handler,
    $request
) {
    $product_id =
        storefleet_dokan_product_branch_rest_product_id(
            $request
        );

    if (!$product_id) {
        return
            $response;
    }

    $pending =
        storefleet_dokan_get_pending_product_branch_payload(
            $product_id
        );

    if (
        !is_array(
            $pending
        )
    ) {
        return
            $response;
    }


    /*
    |--------------------------------------------------------------------------
    | Clear Request-Local State
    |--------------------------------------------------------------------------
    */

    storefleet_dokan_clear_pending_product_branch_payload(
        $product_id
    );


    /*
    |--------------------------------------------------------------------------
    | Dokan Save Failed
    |--------------------------------------------------------------------------
    */

    if (
        is_wp_error(
            $response
        )
    ) {
        return
            $response;
    }

    if (
        is_object(
            $response
        )
        &&
        method_exists(
            $response,
            'get_status'
        )
        &&
        (int) $response->get_status() >= 400
    ) {
        return
            $response;
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve Read-Only Existing Configuration
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $pending[
                'skip_save'
            ]
        )
    ) {
        return
            $response;
    }


    /*
    |--------------------------------------------------------------------------
    | StoreFleet Persistence
    |--------------------------------------------------------------------------
    */

    $save_result =
        storefleet_set_product_branches(
            $product_id,
            $pending[
                'mode'
            ],
            $pending[
                'branch_ids'
            ]
        );

    if (
        is_wp_error(
            $save_result
        )
    ) {
        /*
        |--------------------------------------------------------------------------
        | Validation Should Have Caught Authorization Errors Already
        |--------------------------------------------------------------------------
        |
        | Reaching this point normally means a persistence/database failure.
        |
        */

        $save_result->add_data(
            [
                'status' =>
                    500,
            ],
            $save_result->get_error_code()
        );

        return
            $save_result;
    }

    return
        $response;
}