<?php

if (!defined('ABSPATH')) {
    exit;
}

function storefleet_admin_dashboard()
{
    ?>
    <div class="wrap">

        <h1>StoreFleet</h1>

        <p>
            StoreFleet marketplace development environment.
        </p>

        <table
            class="widefat striped"
            style="max-width:900px"
        >

            <thead>
                <tr>
                    <th>Module</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

                <tr>
                    <td>WooCommerce</td>
                    <td>Active</td>
                </tr>

                <tr>
                    <td>Category Pricing</td>
                    <td>Development</td>
                </tr>

                <tr>
                    <td>Merchants</td>
                    <td>Development</td>
                </tr>

                <tr>
                    <td>Branches</td>
                    <td>Database Ready</td>
                </tr>

                <tr>
                    <td>Staff</td>
                    <td>Database Ready</td>
                </tr>

                <tr>
                    <td>Branch Inventory</td>
                    <td>Database Ready</td>
                </tr>

                <tr>
                    <td>Campaigns</td>
                    <td>Pending</td>
                </tr>

                <tr>
                    <td>POS</td>
                    <td>Development</td>
                </tr>

                <tr>
                    <td>Xendit</td>
                    <td>Pending</td>
                </tr>

                <tr>
                    <td>Lalamove</td>
                    <td>Pending</td>
                </tr>

                <tr>
                    <td>n8n</td>
                    <td>Pending</td>
                </tr>

            </tbody>

        </table>

    </div>
    <?php
}