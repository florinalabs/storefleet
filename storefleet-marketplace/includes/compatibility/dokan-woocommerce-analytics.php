<?php

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| StoreFleet: Dokan / WooCommerce Customer Analytics Compatibility
|--------------------------------------------------------------------------
|
| Dokan modifies WooCommerce customer analytics so customer reports depend
| on wp_dokan_order_stats.
|
| This causes problems for newly registered customers who have not placed
| an order yet:
|
| - Customers can disappear from WooCommerce -> Customers.
| - Customer summary statistics can incorrectly show zero customers.
| - Dokan can replace native WooCommerce customer aggregate expressions.
| - Dokan dashboard date ranges can exclude activity occurring later on the
|   final day of the selected period.
|
| StoreFleet restores WooCommerce's native customer analytics behavior and
| normalizes Dokan dashboard date boundaries while leaving unrelated Dokan
| analytics untouched.
|
*/


/*
|--------------------------------------------------------------------------
| Remove Dokan JOIN Clauses
|--------------------------------------------------------------------------
*/

function storefleet_remove_dokan_customer_join(
    $clauses
) {
    if (!is_array($clauses)) {
        return $clauses;
    }

    foreach ($clauses as $key => $clause) {
        if (
            is_string($clause) &&
            stripos($clause, 'dokan_order_stats') !== false
        ) {
            unset($clauses[$key]);
        }
    }

    return array_values($clauses);
}


/*
|--------------------------------------------------------------------------
| Remove Dokan WHERE Clauses
|--------------------------------------------------------------------------
*/

function storefleet_remove_dokan_customer_where(
    $clauses
) {
    if (!is_array($clauses)) {
        return $clauses;
    }

    foreach ($clauses as $key => $clause) {
        if (
            is_string($clause) &&
            stripos($clause, 'dokan_order_stats') !== false
        ) {
            unset($clauses[$key]);
        }
    }

    return array_values($clauses);
}


/*
|--------------------------------------------------------------------------
| Restore Native WooCommerce Customer Columns
|--------------------------------------------------------------------------
|
| Dokan hooks into:
|
| woocommerce_admin_report_columns
|
| at priority 20 and replaces:
|
| - orders_count
| - total_spend
| - avg_order_value
|
| with expressions referencing dokan_order_stats.
|
| StoreFleet runs afterward and restores WooCommerce's native expressions.
|
*/

function storefleet_restore_customer_report_columns(
    $columns,
    $context,
    $wc_table_name
) {
    if ('customers' !== $context) {
        return $columns;
    }

    $orders_count =
        'SUM( CASE WHEN parent_id = 0 THEN 1 ELSE 0 END )';

    $total_spend =
        'SUM( total_sales )';

    $columns['orders_count'] =
        "{$orders_count} as orders_count";

    $columns['total_spend'] =
        "{$total_spend} as total_spend";

    $columns['avg_order_value'] =
        "CASE WHEN {$orders_count} = 0 " .
        "THEN NULL " .
        "ELSE {$total_spend} / {$orders_count} " .
        "END AS avg_order_value";

    return $columns;
}


/*
|--------------------------------------------------------------------------
| Allow Registered Customers With Zero Orders
|--------------------------------------------------------------------------
|
| WooCommerce can supply default order_after and order_before values for the
| Customers report.
|
| Those values filter against wc_order_stats.date_created.
|
| A newly registered customer has no order_stats row, so the customer can be
| excluded even though the customer exists in wc_customer_lookup.
|
| Only supply empty values when the query did not explicitly request an
| order date range.
|
*/

function storefleet_customer_query_include_zero_order_customers(
    $query_args
) {
    if (!is_array($query_args)) {
        return $query_args;
    }

    if (!array_key_exists('order_after', $query_args)) {
        $query_args['order_after'] = '';
    }

    if (!array_key_exists('order_before', $query_args)) {
        $query_args['order_before'] = '';
    }

    return $query_args;
}


/*
|--------------------------------------------------------------------------
| Restore Native WooCommerce Customer Stats SELECT
|--------------------------------------------------------------------------
|
| Dokan has a separate Customers\Stats\QueryFilter.
|
| It modifies:
|
| woocommerce_analytics_clauses_select_customers_stats_subquery
|
| and rewrites:
|
| - total_spend
| - orders_count
| - avg_order_value
|
| so they depend on dokan_order_stats.
|
| Restore WooCommerce's native customer-stat calculations.
|
*/

function storefleet_restore_customer_stats_select(
    $clauses
) {
    if (!is_array($clauses)) {
        return $clauses;
    }

    foreach ($clauses as $key => $field) {
        if (!is_string($field)) {
            continue;
        }

        $parts = explode(
            ' as ',
            strtolower($field)
        );

        $alias = trim(
            str_replace(
                ',',
                '',
                $parts[1] ?? ''
            )
        );

        $has_comma =
            str_ends_with(
                trim($field),
                ','
            );

        switch ($alias) {

            case 'total_spend':
                $field =
                    'SUM( total_sales ) AS total_spend';
                break;

            case 'orders_count':
                $field =
                    'SUM( CASE WHEN parent_id = 0 THEN 1 END ) AS orders_count';
                break;

            case 'avg_order_value':

                $orders_count =
                    'SUM( CASE WHEN parent_id = 0 THEN 1 ELSE 0 END )';

                $total_spend =
                    'SUM( total_sales )';

                $field =
                    "CASE WHEN {$orders_count} = 0 " .
                    "THEN NULL " .
                    "ELSE {$total_spend} / {$orders_count} " .
                    "END AS avg_order_value";

                break;

            default:
                continue 2;
        }

        if ($has_comma) {
            $field .= ',';
        }

        $clauses[$key] =
            $field;
    }

    return $clauses;
}


/*
|--------------------------------------------------------------------------
| Fix Dokan Admin Dashboard Date Range
|--------------------------------------------------------------------------
|
| Dokan's admin dashboard REST controller generates date-only boundaries:
|
| current_month_start  = YYYY-MM-DD
| current_month_end    = YYYY-MM-DD
| previous_month_start = YYYY-MM-DD
| previous_month_end   = YYYY-MM-DD
|
| When those values are compared against DATETIME columns, MariaDB treats
| the end date as midnight:
|
| YYYY-MM-DD 00:00:00
|
| This means events occurring later on the final day can be excluded.
|
| Example:
|
| Dashboard end:
| 2026-08-23
|
| Customer registration:
| 2026-08-23 05:46:16
|
| Without normalization, that registration is outside the effective range.
|
| StoreFleet normalizes:
|
| start dates -> 00:00:00
| end dates   -> 23:59:59
|
*/

function storefleet_fix_dokan_dashboard_date_range(
    $date_range
) {
    if (!is_array($date_range)) {
        return $date_range;
    }

    $start_keys = array(
        'current_month_start',
        'previous_month_start',
    );

    $end_keys = array(
        'current_month_end',
        'previous_month_end',
    );

    foreach ($start_keys as $key) {
        if (
            !empty($date_range[$key]) &&
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date_range[$key]
            )
        ) {
            $date_range[$key] .= ' 00:00:00';
        }
    }

    foreach ($end_keys as $key) {
        if (
            !empty($date_range[$key]) &&
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date_range[$key]
            )
        ) {
            $date_range[$key] .= ' 23:59:59';
        }
    }

    return $date_range;
}


/*
|--------------------------------------------------------------------------
| WooCommerce Customers List Compatibility
|--------------------------------------------------------------------------
*/

add_filter(
    'woocommerce_analytics_clauses_join_customers_subquery',
    'storefleet_remove_dokan_customer_join',
    9999,
    1
);

add_filter(
    'woocommerce_analytics_clauses_where_customers_subquery',
    'storefleet_remove_dokan_customer_where',
    9999,
    1
);

add_filter(
    'woocommerce_admin_report_columns',
    'storefleet_restore_customer_report_columns',
    9999,
    3
);

add_filter(
    'woocommerce_analytics_customers_query_args',
    'storefleet_customer_query_include_zero_order_customers',
    9999,
    1
);


/*
|--------------------------------------------------------------------------
| WooCommerce Customers Statistics Compatibility
|--------------------------------------------------------------------------
*/

add_filter(
    'woocommerce_analytics_clauses_join_customers_stats_subquery',
    'storefleet_remove_dokan_customer_join',
    9999,
    1
);

add_filter(
    'woocommerce_analytics_clauses_where_customers_stats_subquery',
    'storefleet_remove_dokan_customer_where',
    9999,
    1
);

add_filter(
    'woocommerce_analytics_clauses_select_customers_stats_subquery',
    'storefleet_restore_customer_stats_select',
    9999,
    1
);


/*
|--------------------------------------------------------------------------
| Dokan Admin Dashboard Compatibility
|--------------------------------------------------------------------------
*/

add_filter(
    'dokan_rest_admin_dashboard_date_range',
    'storefleet_fix_dokan_dashboard_date_range',
    9999,
    1
);