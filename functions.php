<?php 
/*
* Plugin Name: MSP Product Sync
*/

add_action( 'wp_dashboard_setup', 'sync_add_dashboard_widgets' );
add_action( 'admin_enqueue_scripts', 'sync_enqueue_scripts' );
add_action( 'wp_ajax_msp_admin_sync_vendor', 'msp_admin_sync_vendor' );
// add_action( 'admin_post_msp_admin_sync_vendor', 'msp_admin_sync_vendor' );

function sync_enqueue_scripts( $hook ){
    wp_enqueue_script('sync-admin', plugins_url( 'admin.js', __FILE__ ));
}

function sync_add_dashboard_widgets(){
    /**
     * Setup dashboard widget
     */
    wp_add_dashboard_widget(
        'msp_add_update_stock',
        'Update Vendors Stock',
        'msp_add_update_stock_widget'
    );

    global $wp_meta_boxes;
    $normal_dash = $wp_meta_boxes['dashboard']['normal']['core'];
    $custom_dash = array( 'msp_add_update_stock' => $normal_dash['msp_add_update_stock'] );
    unset( $normal_dash['msp_add_update_stock'] );
    $sorted_dash = array_merge( $custom_dash, $normal_dash );
    $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dash;
}


function msp_add_update_stock_widget(){
    /**
     * Form for getting stock data from specified vendors
     */
    $today = date('m-d-y');
    $last_sync = get_option( 'msp_helly_hansen_last_sync' );
    ?>
    <form id="msp_add_update_stock_form" method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
        <?php if( $last_sync != $today ) : ?>
            <h1 style="color: red;">HELLY HANSEN NEEDS TO BE SYNCED <?php echo $last_sync ?></h1>
        <?php endif; ?>

        <p>
            <label>Vendor: </label>
            <select name="vendor" >
                <option value="helly_hansen">Helly Hansen</option>
                <option value="portwest">Portwest</option>
            </select>
        </p>

        <p>
            <label>Url: </label>
            <input type="url" name="url" required/>
        </p>

        <h1>How do I Sync HELLY HANSEN?</h1>
        <iframe width="400" height="200" src="https://www.youtube.com/embed/zH1hkzSxOLs" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

        <span class="feedback" style="font-weight: 600; font-color: red; font-size: 18px; "></span>
        <input type="hidden" name="action" value="msp_admin_sync_vendor" />
        <button id="submit_update_vendor" type="button" class="button button-primary" style="margin-top: 1rem;">Submit Vendor!</button>
    </form>
    <?php
}

function msp_admin_sync_vendor(){
    /**
     * This function puts together the data based on prebuilt rules.
     */

    ob_start();
    $data = array(
        'name' => $_POST['vendor'],
        'src'    => $_POST['url'],
        'sku_index' => ( $_POST['vendor'] == 'portwest' ) ? 1 : 16,
        'stock_index' => ( $_POST['vendor'] == 'portwest' ) ? 8 : 7,
        'price_index' => ( $_POST['vendor'] == 'portwest' ) ? 3 : 10,
        'next_delivery' => 9
    );

    // Admin feedback as to manual syncs (Specifically helly hansen)
    update_option('msp_'. $_POST['vendor'] .'_last_sync', date('m-d-y'));

    msp_get_data_and_sync( $data );
    $html = ob_get_clean();
    echo $html;
    wp_die();
}

function msp_get_data_and_sync( $vendor ){
    /**
     * Loops through csv, looks for an ID (variation & simple products) with matching SKU
     * and updates accordingly.
     * @param array $vendor - The vendor, data source, and column information
     */
    $start = microtime(true);

    $count = 0;

    $data = wp_remote_get( $vendor['src'] )['body'];

    if( ! empty( $data ) ){
        foreach( msp_csv_to_array( $data ) as $item ){
            // sku_index and stock_index are the position of the data in the array,
            if( isset( $item[ $vendor['sku_index'] ] ) && isset( $item[ $vendor[ 'stock_index'] ] ) ){
                if( ! empty( $item[ $vendor['sku_index'] ] ) ){
                    $id = msp_get_product_id_by_sku( $item[ $vendor['sku_index'] ] );
                    if( ! empty( $id ) ){
                        msp_update_stock( $id, $item[ $vendor['stock_index'] ], $item[ $vendor['next_delivery'] ] );
                        $count++;
                    }
                }
            }
        }
    } else {
        echo '$data is empty';
    }

    $time_elapsed_secs = microtime(true) - $start;

    echo '<h2>Report</h2>';
    echo 'Products Updated: ' . $count . '.<br>';
    echo 'Time Elasped: ' . number_format( $time_elapsed_secs, 2 ) . ' seconds.<br>';
}

function msp_get_product_id_by_sku( $sku = false ) {
    /**
     * Looks in the DB for a product whith a matching SKU
     * @param string $sku
     * @param int $product_id
     */

    if( ! $sku ) return null;

    global $wpdb;
    $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
    return $product_id;
}

function msp_csv_to_array( $data ){
    /**
     * Converts a CSV to an array
     */
    $rows = explode("\n", $data);
    $s = array();

    foreach($rows as $row) {
        $s[] = str_getcsv($row);
    }

    return $s;
}

function msp_update_stock( $id, $stock, $next_delivery){
    /**
     * Checks the ID, sets stock information and puts product on back order if at 0 but has $next_delivery
     * @param int $id
     * @param int $stock - Stock of item
     * @param string $next_delivery - The date the manufacturer expects to get more product.
     */

     // NEVER EVER USE WC_PRODUCT object, update post meta!
    update_post_meta( $id, '_manage_stock', 'yes' );
    update_post_meta( $id, '_stock', $stock );

    if( $stock > 0 ){
        update_post_meta( $id, '_stock_status', 'instock' );
    } else {
        update_post_meta( $id, '_stock_status', 'onbackorder' );
        update_post_meta( $id, '_backorders', 'notify' );
        update_post_meta( $id, 'msp_sync_next_delivery', $next_delivery );
    }
}

add_filter( 'woocommerce_get_availability_text', 'msp_get_availability', 100, 2 );
function msp_get_availability( $text, $_product ){
    /**
     * Determine how to format date based on vendor
     */

    $next_delivery = get_post_meta($_product->get_id(), 'msp_sync_next_delivery', true);
    $new_date = preg_replace("/(\d+)\D+(\d+)\D+(\d+)/","$2/$1/$3", $next_delivery);

    if ( $_product->managing_stock() && $_product->is_on_backorder( 1 ) && ! empty( $new_date ) ) {
        $text = "On backorder, item estimated to ship <strong>$new_date*</strong>";
    }

    return $text;
}