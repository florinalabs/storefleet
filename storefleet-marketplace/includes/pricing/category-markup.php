<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Pricing Page
|--------------------------------------------------------------------------
*/

function storefleet_pricing_page()
{
    ?>
    <div class="wrap">

        <h1>StoreFleet Pricing</h1>

        <p>
            Product selling prices will be calculated primarily
            from WooCommerce product category markup.
        </p>

        <p>
            Merchants control their vendor/base price.
            StoreFleet controls markup and customer selling price.
        </p>

        <p>
            <a
                class="button button-primary"
                href="<?php echo esc_url(
                    admin_url(
                        'edit-tags.php?taxonomy=product_cat&post_type=product'
                    )
                ); ?>"
            >
                Manage Category Markup
            </a>
        </p>

    </div>
    <?php
}


/*
|--------------------------------------------------------------------------
| Add Category Markup Field
|--------------------------------------------------------------------------
*/

add_action(
    'product_cat_add_form_fields',
    function () {
        ?>

        <div class="form-field">

            <label for="storefleet_markup">
                StoreFleet Markup (%)
            </label>

            <input
                type="number"
                name="storefleet_markup"
                id="storefleet_markup"
                min="0"
                max="1000"
                step="0.01"
            >

            <p class="description">
                Percentage StoreFleet adds to the merchant
                vendor/base price.
            </p>

        </div>

        <?php
    }
);


/*
|--------------------------------------------------------------------------
| Edit Category Markup Field
|--------------------------------------------------------------------------
*/

add_action(
    'product_cat_edit_form_fields',
    function ($term) {

        $markup = get_term_meta(
            $term->term_id,
            'storefleet_markup',
            true
        );

        ?>

        <tr class="form-field">

            <th scope="row">

                <label for="storefleet_markup">
                    StoreFleet Markup (%)
                </label>

            </th>

            <td>

                <input
                    type="number"
                    name="storefleet_markup"
                    id="storefleet_markup"
                    value="<?php echo esc_attr($markup); ?>"
                    min="0"
                    max="1000"
                    step="0.01"
                >

                <p class="description">
                    Percentage StoreFleet adds to the merchant
                    vendor/base price.
                </p>

            </td>

        </tr>

        <?php
    }
);


/*
|--------------------------------------------------------------------------
| Save Category Markup
|--------------------------------------------------------------------------
*/

function storefleet_save_category_markup($term_id)
{
    if (
        !current_user_can('manage_woocommerce') &&
        !current_user_can('manage_options')
    ) {
        return;
    }

    if (!isset($_POST['storefleet_markup'])) {
        return;
    }

    $markup = wc_format_decimal(
        wp_unslash(
            $_POST['storefleet_markup']
        )
    );

    update_term_meta(
        $term_id,
        'storefleet_markup',
        $markup
    );
}

add_action(
    'created_product_cat',
    'storefleet_save_category_markup'
);

add_action(
    'edited_product_cat',
    'storefleet_save_category_markup'
);


/*
|--------------------------------------------------------------------------
| Category Column
|--------------------------------------------------------------------------
*/

add_filter(
    'manage_edit-product_cat_columns',
    function ($columns) {

        $columns['storefleet_markup'] =
            'StoreFleet Markup';

        return $columns;
    }
);

add_filter(
    'manage_product_cat_custom_column',
    function (
        $content,
        $column_name,
        $term_id
    ) {

        if (
            $column_name !==
            'storefleet_markup'
        ) {
            return $content;
        }

        $markup = get_term_meta(
            $term_id,
            'storefleet_markup',
            true
        );

        if ($markup === '') {
            return '—';
        }

        return esc_html(
            $markup . '%'
        );
    },
    10,
    3
);