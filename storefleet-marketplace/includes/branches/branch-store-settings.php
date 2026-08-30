<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Dokan Store Settings: Branch Opening Hours
|--------------------------------------------------------------------------
|
| Merchants edit opening hours from:
|
| Dashboard -> Settings -> Store
|
| The data remains branch-specific in StoreFleet.
|
*/

/*
|--------------------------------------------------------------------------
| Render Hooks
|--------------------------------------------------------------------------
|
| Dokan 5.0.16 exposes these hooks in the legacy Store settings form.
|
| - after_store_email renders StoreFleet hours before Dokan's old schedule
| - form_bottom remains as a fallback
|
*/

add_action(
    'dokan_settings_after_store_email',
    'storefleet_render_dokan_branch_opening_hours',
    20,
    2
);

add_action(
    'dokan_settings_form_bottom',
    'storefleet_render_dokan_branch_opening_hours',
    20,
    2
);


/*
|--------------------------------------------------------------------------
| Resolve Merchant ID
|--------------------------------------------------------------------------
|
| Prefer the logged-in WordPress vendor ID. Keep fallbacks for differences
| in what Dokan passes into settings hooks.
|
*/

function storefleet_store_settings_merchant_id(
    $current_user = null
) {
    $merchant_id =
        absint(
            get_current_user_id()
        );

    if ($merchant_id) {
        return $merchant_id;
    }

    if (
        is_object(
            $current_user
        ) &&
        !empty(
            $current_user->ID
        )
    ) {
        return absint(
            $current_user->ID
        );
    }

    if (
        is_numeric(
            $current_user
        )
    ) {
        return absint(
            $current_user
        );
    }

    return 0;
}


/*
|--------------------------------------------------------------------------
| Render Opening Hours
|--------------------------------------------------------------------------
*/

function storefleet_render_dokan_branch_opening_hours(
    $current_user,
    $profile_info
) {
    static $rendered =
        false;

    if ($rendered) {
        return;
    }

    $merchant_id =
        storefleet_store_settings_merchant_id(
            $current_user
        );

    if (!$merchant_id) {
        return;
    }

    $rendered =
        true;

    $branches =
        storefleet_get_merchant_branches(
            $merchant_id,
            false
        );

    $days =
        array(
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        );

    wp_nonce_field(
        'storefleet_save_store_branch_hours',
        'storefleet_store_branch_hours_nonce'
    );

    ?>

    <div
        class="
            dokan-form-group
            storefleet-store-hours-section
        "
    >

        <label
            class="
                dokan-w3
                dokan-control-label
            "
        >
            <?php
            echo esc_html__(
                'Branch Opening Hours',
                'storefleet-marketplace'
            );
            ?>
        </label>

        <div class="dokan-w8">

            <div
                class="
                    storefleet-store-hours-intro
                "
            >
                Set the operating hours for each branch.
            </div>

            <?php if (!$branches) : ?>

                <div
                    class="
                        storefleet-store-hours-empty
                    "
                >
                    No branches were found for this merchant.
                    Create a branch first from the Branches menu.
                </div>

            <?php else : ?>

                <?php
                foreach (
                    $branches as
                    $branch
                ) :
                ?>

                    <?php

                    $hours =
                        storefleet_get_branch_hours(
                            $branch->id
                        );

                    $is_open_now =
                        storefleet_branch_is_open_now(
                            $branch->id
                        );

                    ?>

                    <section
                        class="
                            storefleet-hours-card
                            <?php
                            echo
                                (int) $branch->is_active === 1
                                    ? ''
                                    : 'storefleet-hours-card-inactive';
                            ?>
                        "
                    >

                        <div
                            class="
                                storefleet-hours-card-header
                            "
                        >

                            <div>

                                <strong
                                    class="
                                        storefleet-hours-branch-name
                                    "
                                >
                                    <?php
                                    echo esc_html(
                                        $branch->name
                                    );
                                    ?>
                                </strong>

                                <?php
                                if (
                                    (int)
                                    $branch->is_primary === 1
                                ) :
                                ?>

                                    <span
                                        class="
                                            storefleet-hours-badge
                                            storefleet-hours-badge-primary
                                        "
                                    >
                                        Primary
                                    </span>

                                <?php endif; ?>

                                <?php
                                if (
                                    (int)
                                    $branch->is_active !== 1
                                ) :
                                ?>

                                    <span
                                        class="
                                            storefleet-hours-badge
                                            storefleet-hours-badge-inactive
                                        "
                                    >
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </div>

                            <?php
                            if (
                                (int)
                                $branch->is_active === 1
                            ) :
                            ?>

                                <span
                                    class="
                                        storefleet-hours-status
                                        <?php
                                        echo
                                            $is_open_now
                                                ? 'storefleet-hours-status-open'
                                                : 'storefleet-hours-status-closed';
                                        ?>
                                    "
                                >
                                    <?php
                                    echo
                                        $is_open_now
                                            ? 'Open Now'
                                            : 'Closed';
                                    ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div
                            class="
                                storefleet-hours-table
                            "
                        >

                            <div class="storefleet-hours-table-head">
                                <div>Day</div>
                                <div>Availability</div>
                                <div>Opens</div>
                                <div></div>
                                <div>Closes</div>
                            </div>

                            <?php
                            foreach (
                                $days as
                                $day_number =>
                                $day_label
                            ) :
                            ?>

                                <?php

                                $row =
                                    $hours[
                                        $day_number
                                    ];

                                ?>

                                <div
                                    class="
                                        storefleet-hours-row
                                    "
                                >

                                    <div
                                        class="
                                            storefleet-hours-day
                                        "
                                    >
                                        <?php
                                        echo esc_html(
                                            $day_label
                                        );
                                        ?>
                                    </div>


                                    <label
                                        class="
                                            storefleet-hours-open-toggle
                                        "
                                    >

                                        <input
                                            type="checkbox"

                                            name="<?php
                                            echo esc_attr(
                                                sprintf(
                                                    'storefleet_branch_hours[%d][%d][is_open]',
                                                    $branch->id,
                                                    $day_number
                                                )
                                            );
                                            ?>"

                                            value="1"

                                            <?php
                                            checked(
                                                (int)
                                                $row->is_open,
                                                1
                                            );
                                            ?>
                                        >

                                        Open

                                    </label>


                                    <input
                                        type="time"
                                        class="
                                            dokan-form-control
                                            storefleet-hours-time
                                        "

                                        name="<?php
                                        echo esc_attr(
                                            sprintf(
                                                'storefleet_branch_hours[%d][%d][opens_at]',
                                                $branch->id,
                                                $day_number
                                            )
                                        );
                                        ?>"

                                        value="<?php
                                        echo esc_attr(
                                            !empty(
                                                $row->opens_at
                                            )
                                                ? substr(
                                                    $row->opens_at,
                                                    0,
                                                    5
                                                )
                                                : ''
                                        );
                                        ?>"
                                    >


                                    <span
                                        class="
                                            storefleet-hours-to
                                        "
                                    >
                                        to
                                    </span>


                                    <input
                                        type="time"
                                        class="
                                            dokan-form-control
                                            storefleet-hours-time
                                        "

                                        name="<?php
                                        echo esc_attr(
                                            sprintf(
                                                'storefleet_branch_hours[%d][%d][closes_at]',
                                                $branch->id,
                                                $day_number
                                            )
                                        );
                                        ?>"

                                        value="<?php
                                        echo esc_attr(
                                            !empty(
                                                $row->closes_at
                                            )
                                                ? substr(
                                                    $row->closes_at,
                                                    0,
                                                    5
                                                )
                                                : ''
                                        );
                                        ?>"
                                    >

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>


    <style>

        /*
        |--------------------------------------------------------------------------
        | Hide Dokan's Merchant-Wide Store Schedule
        |--------------------------------------------------------------------------
        */

        .dokan-settings-area
        .store-open-close-time,

        .dokan-settings-area
        .store-open-close {
            display: none !important;
        }


        /*
        |--------------------------------------------------------------------------
        | StoreFleet Branch Opening Hours
        |--------------------------------------------------------------------------
        */

        .storefleet-store-hours-section {
            display: block !important;
            width: 100% !important;
            margin: 32px 0 8px !important;
            padding: 0 !important;
            clear: both;
        }

        /*
        | Dokan normally renders form groups as label/content columns.
        | Opening hours are large enough to deserve the full content width.
        */

        .storefleet-store-hours-section
        > .dokan-control-label {
            display: block !important;
            float: none !important;
            width: 100% !important;
            margin: 0 0 6px !important;
            padding: 0 !important;
            text-align: left !important;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.35;
            color: #111827;
        }

        .storefleet-store-hours-section
        > .dokan-w8 {
            display: block !important;
            float: none !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .storefleet-store-hours-intro {
            margin: 0 0 20px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        .storefleet-store-hours-empty {
            padding: 18px 20px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
            color: #4b5563;
        }


        /*
        |--------------------------------------------------------------------------
        | Branch Card
        |--------------------------------------------------------------------------
        */

        .storefleet-hours-card {
            overflow: hidden;
            margin: 0 0 18px;
            padding: 0;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(17, 24, 39, 0.03);
        }

        .storefleet-hours-card-inactive {
            opacity: 0.68;
        }

        .storefleet-hours-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 0;
            padding: 16px 18px;
            border-bottom: 1px solid #eef0f3;
            background: #fafafa;
        }

        .storefleet-hours-branch-name {
            color: #111827;
            font-size: 15px;
            font-weight: 700;
        }

        .storefleet-hours-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 7px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            line-height: 1.4;
            vertical-align: middle;
        }

        .storefleet-hours-badge-primary {
            background: #f0edff;
            color: #5b3fd3;
        }

        .storefleet-hours-badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .storefleet-hours-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
            white-space: nowrap;
        }

        .storefleet-hours-status-open {
            background: #dcfce7;
            color: #166534;
        }

        .storefleet-hours-status-closed {
            background: #fee2e2;
            color: #991b1b;
        }


        /*
        |--------------------------------------------------------------------------
        | Schedule Grid
        |--------------------------------------------------------------------------
        */

        .storefleet-hours-table {
            padding: 8px 18px 12px;
        }

        .storefleet-hours-table-head,
        .storefleet-hours-row {
            display: grid;
            grid-template-columns:
                140px
                120px
                minmax(140px, 1fr)
                32px
                minmax(140px, 1fr);
            gap: 12px;
            align-items: center;
        }

        .storefleet-hours-table-head {
            padding: 10px 0 8px;
            color: #9ca3af;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .storefleet-hours-row {
            min-height: 54px;
            margin: 0;
            padding: 8px 0;
            border-top: 1px solid #f3f4f6;
        }

        .storefleet-hours-day {
            color: #1f2937;
            font-size: 14px;
            font-weight: 650;
        }

        .storefleet-hours-open-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            width: max-content;
            min-width: 86px;
            margin: 0;
            padding: 6px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #ffffff;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
        }

        .storefleet-hours-open-toggle input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #6d4aff;
        }

        .storefleet-hours-time {
            width: 100% !important;
            min-width: 0;
            height: 38px;
            margin: 0 !important;
            border: 1px solid #dfe3e8 !important;
            border-radius: 7px !important;
            background: #ffffff !important;
            box-shadow: none !important;
            font-size: 13px !important;
        }

        .storefleet-hours-time:focus {
            border-color: #8b78e6 !important;
            box-shadow: 0 0 0 2px rgba(109, 74, 255, 0.10) !important;
            outline: none !important;
        }

        .storefleet-hours-to {
            color: #9ca3af;
            font-size: 13px;
            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .storefleet-hours-table-head {
                display: none;
            }

            .storefleet-hours-table {
                padding-top: 4px;
            }

            .storefleet-hours-row {
                grid-template-columns:
                    minmax(110px, 1fr)
                    110px
                    minmax(120px, 1fr)
                    22px
                    minmax(120px, 1fr);
            }
        }

        @media (max-width: 700px) {

            .storefleet-store-hours-section {
                margin-top: 24px !important;
            }

            .storefleet-hours-card-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .storefleet-hours-table {
                padding: 8px 14px 12px;
            }

            .storefleet-hours-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 14px 0;
            }

            .storefleet-hours-day {
                align-self: center;
            }

            .storefleet-hours-open-toggle {
                justify-self: end;
            }

            .storefleet-hours-time {
                grid-column: auto;
            }

            .storefleet-hours-to {
                display: none;
            }
        }

    </style>

    <?php
}


/*
|--------------------------------------------------------------------------
| Save Branch Hours With Dokan Update Settings
|--------------------------------------------------------------------------
*/

add_action(
    'dokan_store_profile_saved',
    'storefleet_save_dokan_branch_opening_hours',
    20,
    3
);


function storefleet_save_dokan_branch_opening_hours(
    $store_id,
    $dokan_settings,
    $previous_settings
) {
    $store_id =
        absint(
            $store_id
        );

    if (!$store_id) {
        return;
    }

    if (
        empty(
            $_POST[
                'storefleet_store_branch_hours_nonce'
            ]
        )
    ) {
        return;
    }

    $nonce =
        sanitize_text_field(
            wp_unslash(
                $_POST[
                    'storefleet_store_branch_hours_nonce'
                ]
            )
        );

    if (
        !wp_verify_nonce(
            $nonce,
            'storefleet_save_store_branch_hours'
        )
    ) {
        return;
    }

    $submitted =
        isset(
            $_POST[
                'storefleet_branch_hours'
            ]
        )
            ? wp_unslash(
                $_POST[
                    'storefleet_branch_hours'
                ]
            )
            : array();

    if (!is_array($submitted)) {
        return;
    }

    foreach (
        $submitted as
        $branch_id =>
        $schedule
    ) {
        $branch_id =
            absint(
                $branch_id
            );

        if (
            !$branch_id ||
            !is_array(
                $schedule
            )
        ) {
            continue;
        }

        storefleet_save_branch_hours(
            $store_id,
            $branch_id,
            $schedule
        );
    }
}


/*
|--------------------------------------------------------------------------
| Disable Dokan's Merchant-Wide Operational Schedule
|--------------------------------------------------------------------------
|
| StoreFleet uses branch-specific hours. Keep Dokan's fields available in
| its plugin/database for compatibility, but do not use them operationally.
|
*/

add_filter(
    'dokan_store_profile_settings_args',
    'storefleet_disable_dokan_global_store_hours',
    20,
    2
);


function storefleet_disable_dokan_global_store_hours(
    $settings,
    $store_id
) {
    if (!is_array($settings)) {
        return $settings;
    }

    $settings[
        'dokan_store_time_enabled'
    ] = 'no';

    $settings[
        'dokan_store_time'
    ] = array();

    return $settings;
}
