<?php

if (!defined('ABSPATH')) {
    exit;
}

function storefleet_install_roles()
{
    if (!get_role('storefleet_staff')) {
        add_role(
            'storefleet_staff',
            'StoreFleet Staff',
            [
                'read' => true,
            ]
        );
    }
}

function storefleet_get_staff_roles()
{
    return [
        'manager'         => 'Manager',
        'branch_manager'  => 'Branch Manager',
        'cashier'         => 'Cashier',
        'inventory_staff' => 'Inventory Staff',
        'order_staff'     => 'Order Staff',
        'delivery_staff'  => 'Delivery Staff',
    ];
}