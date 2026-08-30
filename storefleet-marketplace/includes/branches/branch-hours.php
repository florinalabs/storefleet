<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Branch Opening Hours
|--------------------------------------------------------------------------
|
| Returns a complete seven-day schedule keyed by ISO day number:
|
| 1 = Monday
| 2 = Tuesday
| 3 = Wednesday
| 4 = Thursday
| 5 = Friday
| 6 = Saturday
| 7 = Sunday
|
*/

function storefleet_get_branch_hours(
    $branch_id
) {
    global $wpdb;

    $branch_id =
        absint(
            $branch_id
        );

    if (!$branch_id) {
        return array();
    }

    $table =
        storefleet_branch_hours_table();

    $rows =
        $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    id,
                    branch_id,
                    day_of_week,
                    is_open,
                    opens_at,
                    closes_at,
                    created_at,
                    updated_at
                FROM {$table}
                WHERE branch_id = %d
                ORDER BY day_of_week ASC
                ",
                $branch_id
            )
        );

    $indexed =
        array();

    foreach (
        $rows as
        $row
    ) {
        $indexed[
            (int) $row->day_of_week
        ] = $row;
    }

    $schedule =
        array();

    for (
        $day = 1;
        $day <= 7;
        $day++
    ) {
        if (
            isset(
                $indexed[$day]
            )
        ) {
            $schedule[$day] =
                $indexed[$day];

            continue;
        }

        $schedule[$day] =
            (object) array(
                'id' =>
                    0,

                'branch_id' =>
                    $branch_id,

                'day_of_week' =>
                    $day,

                'is_open' =>
                    0,

                'opens_at' =>
                    null,

                'closes_at' =>
                    null,

                'created_at' =>
                    null,

                'updated_at' =>
                    null,
            );
    }

    return $schedule;
}


/*
|--------------------------------------------------------------------------
| Normalize Branch Time
|--------------------------------------------------------------------------
|
| Store time values in MySQL TIME format (HH:MM:SS).
|
*/

function storefleet_normalize_branch_time(
    $value
) {
    $value =
        sanitize_text_field(
            (string) $value
        );

    if ($value === '') {
        return null;
    }

    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $value
        )
    ) {
        return null;
    }

    return $value . ':00';
}


/*
|--------------------------------------------------------------------------
| Save Branch Opening Hours
|--------------------------------------------------------------------------
|
| The branch must belong to the supplied merchant.
|
*/

function storefleet_save_branch_hours(
    $merchant_id,
    $branch_id,
    $schedule
) {
    global $wpdb;

    $merchant_id =
        absint(
            $merchant_id
        );

    $branch_id =
        absint(
            $branch_id
        );

    if (
        !$merchant_id ||
        !$branch_id
    ) {
        return new WP_Error(
            'storefleet_invalid_branch_hours_target',
            'A valid merchant and branch are required.'
        );
    }

    $branch =
        storefleet_get_branch(
            $branch_id
        );

    if (
        !$branch ||
        (int) $branch->merchant_id !==
        $merchant_id
    ) {
        return new WP_Error(
            'storefleet_branch_not_owned',
            'This branch does not belong to the merchant.'
        );
    }

    if (!is_array($schedule)) {
        return new WP_Error(
            'storefleet_invalid_branch_schedule',
            'The branch schedule is invalid.'
        );
    }

    $table =
        storefleet_branch_hours_table();

    $now =
        current_time(
            'mysql'
        );

    for (
        $day = 1;
        $day <= 7;
        $day++
    ) {
        $input =
            isset(
                $schedule[$day]
            ) &&
            is_array(
                $schedule[$day]
            )
                ? $schedule[$day]
                : array();

        $is_open =
            !empty(
                $input['is_open']
            )
                ? 1
                : 0;

        $opens_at =
            null;

        $closes_at =
            null;

        if ($is_open) {
            $opens_at =
                storefleet_normalize_branch_time(
                    $input['opens_at'] ??
                    ''
                );

            $closes_at =
                storefleet_normalize_branch_time(
                    $input['closes_at'] ??
                    ''
                );

            if (
                !$opens_at ||
                !$closes_at
            ) {
                return new WP_Error(
                    'storefleet_invalid_branch_hours',
                    sprintf(
                        'Branch day %d requires valid opening and closing times.',
                        $day
                    )
                );
            }
        }

        $existing_id =
            absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "
                        SELECT id
                        FROM {$table}
                        WHERE branch_id = %d
                        AND day_of_week = %d
                        LIMIT 1
                        ",
                        $branch_id,
                        $day
                    )
                )
            );

        $data =
            array(
                'is_open' =>
                    $is_open,

                'opens_at' =>
                    $opens_at,

                'closes_at' =>
                    $closes_at,

                'updated_at' =>
                    $now,
            );

        $formats =
            array(
                '%d',
                '%s',
                '%s',
                '%s',
            );

        if ($existing_id) {
            $result =
                $wpdb->update(
                    $table,
                    $data,
                    array(
                        'id' =>
                            $existing_id,
                    ),
                    $formats,
                    array(
                        '%d',
                    )
                );
        } else {
            $data['branch_id'] =
                $branch_id;

            $data['day_of_week'] =
                $day;

            $data['created_at'] =
                $now;

            $result =
                $wpdb->insert(
                    $table,
                    $data,
                    array(
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%d',
                        '%s',
                    )
                );
        }

        if ($result === false) {
            return new WP_Error(
                'storefleet_branch_hours_save_failed',
                'The branch opening hours could not be saved.'
            );
        }
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Is Branch Open Now
|--------------------------------------------------------------------------
|
| Uses the WordPress site timezone.
|
| Overnight ranges are supported, for example:
|
| 18:00 -> 02:00
|
*/

function storefleet_branch_is_open_now(
    $branch_id,
    $timestamp = null
) {
    $branch =
        storefleet_get_branch(
            $branch_id
        );

    if (
        !$branch ||
        (int) $branch->is_active !== 1
    ) {
        return false;
    }

    if ($timestamp === null) {
        $now =
            current_datetime();
    } else {
        $now =
            (new DateTimeImmutable(
                '@' . absint($timestamp)
            ))->setTimezone(
                wp_timezone()
            );
    }

    $day =
        (int) $now->format('N');

    $current_time =
        $now->format('H:i:s');

    $schedule =
        storefleet_get_branch_hours(
            $branch_id
        );

    /*
    |--------------------------------------------------------------------------
    | Previous-Day Overnight Window
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Monday 18:00 -> 02:00
    |
    | At 01:00 Tuesday the branch is still open because of Monday's row.
    |
    */

    $previous_day =
        $day === 1
            ? 7
            : $day - 1;

    if (
        !empty(
            $schedule[$previous_day]
        )
    ) {
        $previous =
            $schedule[$previous_day];

        if (
            (int) $previous->is_open === 1 &&
            !empty($previous->opens_at) &&
            !empty($previous->closes_at) &&
            $previous->closes_at <=
            $previous->opens_at &&
            $current_time <
            $previous->closes_at
        ) {
            return true;
        }
    }

    if (
        empty(
            $schedule[$day]
        )
    ) {
        return false;
    }

    $today =
        $schedule[$day];

    if (
        (int) $today->is_open !== 1 ||
        empty($today->opens_at) ||
        empty($today->closes_at)
    ) {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Normal Same-Day Range
    |--------------------------------------------------------------------------
    */

    if (
        $today->closes_at >
        $today->opens_at
    ) {
        return
            $current_time >=
            $today->opens_at
            &&
            $current_time <
            $today->closes_at;
    }

    /*
    |--------------------------------------------------------------------------
    | Overnight Range
    |--------------------------------------------------------------------------
    */

    return
        $current_time >=
        $today->opens_at;
}

