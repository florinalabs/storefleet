<?php

if (!defined('ABSPATH')) {
    exit;
}

function storefleet_branches_table()
{
    global $wpdb;

    return $wpdb->prefix . 'storefleet_branches';
}

function storefleet_branch_hours_table()
{
    global $wpdb;

    return $wpdb->prefix . 'storefleet_branch_hours';
}

function storefleet_staff_table()
{
    global $wpdb;

    return $wpdb->prefix . 'storefleet_staff';
}

function storefleet_staff_branches_table()
{
    global $wpdb;

    return $wpdb->prefix . 'storefleet_staff_branches';
}

function storefleet_branch_inventory_table()
{
    global $wpdb;

    return $wpdb->prefix . 'storefleet_branch_inventory';
}