<?php

/**
 * Plugin Name: StoreFleet Marketplace
 * Description: Core marketplace functionality for StoreFleet.
 * Version: 0.3.0
 * Author: StoreFleet
 */

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

define(
    'STOREFLEET_VERSION',
    '0.3.0'
);

/*
|--------------------------------------------------------------------------
| Database Schema Version
|--------------------------------------------------------------------------
|
| 0.3.0 adds:
|
| wp_storefleet_product_branches
|
| Used for single / multiple product branch availability.
|
*/

define(
    'STOREFLEET_DB_VERSION',
    '0.3.0'
);

define(
    'STOREFLEET_PATH',
    plugin_dir_path(__FILE__)
);

define(
    'STOREFLEET_URL',
    plugin_dir_url(__FILE__)
);


/*
|--------------------------------------------------------------------------
| Core
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/helpers.php';

require_once STOREFLEET_PATH .
    'includes/roles.php';

require_once STOREFLEET_PATH .
    'includes/database.php';


/*
|--------------------------------------------------------------------------
| Assets
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/assets.php';


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/admin/dashboard.php';

require_once STOREFLEET_PATH .
    'includes/admin/menu.php';


/*
|--------------------------------------------------------------------------
| Pricing
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/pricing/category-markup.php';


/*
|--------------------------------------------------------------------------
| Merchants
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/merchants/merchant-helpers.php';


/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/api/merchant-registration.php';

require_once STOREFLEET_PATH .
    'includes/api/customer-auth.php';

require_once STOREFLEET_PATH .
    'includes/api/customer-addresses.php';

require_once STOREFLEET_PATH .
    'includes/api/customer-orders.php';


/*
|--------------------------------------------------------------------------
| Compatibility
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/compatibility/dokan-woocommerce-analytics.php';

require_once STOREFLEET_PATH .
    'includes/compatibility/dokan-product-publishing.php';


/*
|--------------------------------------------------------------------------
| Branches
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/branches/branch-helpers.php';

require_once STOREFLEET_PATH .
    'includes/branches/branch-actions.php';

require_once STOREFLEET_PATH .
    'includes/branches/branch-dashboard.php';


/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/staff/staff-helpers.php';

require_once STOREFLEET_PATH .
    'includes/staff/staff-portal.php';

require_once STOREFLEET_PATH .
    'includes/staff/staff-actions.php';

require_once STOREFLEET_PATH .
    'includes/staff/staff-dashboard.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-integration.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-navigation.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-content.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-branch-context.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-capabilities.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-wepos.php';

require_once STOREFLEET_PATH .
    'includes/staff/dokan-staff-shell.php';


/*
|--------------------------------------------------------------------------
| Delivery
|--------------------------------------------------------------------------
|
| StoreFleet Delivery inside Dokan React dashboard.
|
*/

require_once STOREFLEET_PATH .
    'includes/delivery/delivery-helpers.php';

require_once STOREFLEET_PATH .
    'includes/delivery/delivery-actions.php';

require_once STOREFLEET_PATH .
    'includes/delivery/delivery-dashboard.php';


/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
*/

require_once STOREFLEET_PATH .
    'includes/inventory/branch-inventory.php';


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
|
| Product branch availability:
|
| - all merchant branches
| - one selected branch
| - multiple selected branches
|
*/

require_once STOREFLEET_PATH .
    'includes/products/product-branches.php';

require_once STOREFLEET_PATH .
    'includes/products/dokan-product-branches.php';


/*
|--------------------------------------------------------------------------
| Plugin Activation
|--------------------------------------------------------------------------
*/

function storefleet_activate()
{
    storefleet_install_database();
    storefleet_install_roles();

    flush_rewrite_rules();
}

register_activation_hook(
    __FILE__,
    'storefleet_activate'
);


/*
|--------------------------------------------------------------------------
| Plugin Deactivation
|--------------------------------------------------------------------------
*/

function storefleet_deactivate()
{
    flush_rewrite_rules();
}

register_deactivation_hook(
    __FILE__,
    'storefleet_deactivate'
);


/*
|--------------------------------------------------------------------------
| Database / Role Upgrade Check
|--------------------------------------------------------------------------
|
| Whenever STOREFLEET_DB_VERSION changes, StoreFleet automatically runs
| storefleet_install_database().
|
| dbDelta() will create missing tables and upgrade existing schemas.
|
*/

add_action(
    'plugins_loaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Database Upgrade
        |--------------------------------------------------------------------------
        */

        $installed_version =
            get_option(
                'storefleet_db_version',
                ''
            );

        if (
            $installed_version !==
            STOREFLEET_DB_VERSION
        ) {
            storefleet_install_database();
        }


        /*
        |--------------------------------------------------------------------------
        | WordPress Staff Role
        |--------------------------------------------------------------------------
        */

        if (
            !get_role(
                'storefleet_staff'
            )
        ) {
            storefleet_install_roles();
        }
    }
);