<?php
defined( 'ABSPATH' ) || exit;

/*
* Plugin Name: MSP Product Sync
* Version: 0.21
*/

class Sync{
    /**
     * In construct, we make the relevant changes to the $flags array
     * Then, once we obtain the CSV, match an ID to a sku. We finally loop through our
     * $flags array checked if enabled and calling approprate callback if needed.
     */
    public $flags = array(
            'stock' => array(
                'enabled' => false,
                'callback' => 'msp_update_stock'
            ),
            'dims' => array(
                'enabled' => false,
                'callback' => 'msp_update_dims'
            ),
            'price' => array(
                'enabled' => false,
                'modifier' => 0,
                'callback' => '',
                'map_our_cost' => false // An extra checkbox for mapping our cost and making determinations.
            ),
    );

    public $column_mappings = array(
        'portwest' => array(
            'sku' => 1,
            'stock' => 8,
            'price' => 3,
            'next_delivery' => 9,
            'length' => 10,
            'width' => 11,
            'height' => 12,
            'weight' => 14
        ),
        'helly_hansen' => array(
            'sku' => 16,
            'stock' => 7,
            'price' => 10,
            'next_delivery' => 9
        ),
    );

    public $vendor;
    public $url;
    public $dry_run;


    function __construct(){
        // Add to WP dashboard - May require changing screen options (top right on dashboard)
        add_action( 'wp_dashboard_setup', array( $this, 'sync_add_dashboard_widgets' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );
        add_action( 'wp_ajax_msp_admin_sync_vendor', array( $this, 'process' ) );
        add_action( 'admin_post_msp_admin_sync_vendor', array( $this, 'process' ) );
    }

    public function admin_js(){
        wp_enqueue_script('sync-admin', plugins_url( 'admin.js', __FILE__ ));
    }

    public function sync_add_dashboard_widgets(){
        /**
         * Setup dashboard widget
         */
        wp_add_dashboard_widget(
            'msp_add_update_stock',
            'Update Vendors Stock',
            array('Sync', 'widget')
        );
    
        global $wp_meta_boxes;
        $normal_dash = $wp_meta_boxes['dashboard']['normal']['core'];
        $custom_dash = array( 'msp_add_update_stock' => $normal_dash['msp_add_update_stock'] );
        unset( $normal_dash['msp_add_update_stock'] );
        $sorted_dash = array_merge( $custom_dash, $normal_dash );
        $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dash;
    }

    public static function widget(){
     /**
     * Wordpress dashboard widget
     */
    $today = date('m-d-y');
    $user = wp_get_current_user();
    $last_sync = get_option( 'msp_helly_hansen_last_sync' );
    ?>
    <form id="msp_add_update_stock_form" method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
        <?php if( $last_sync != $today ) : ?>
            <h1><span style="color: red;"><?php echo $user->user_firstname ?></span>, I need your help...😬💗</h1>
            <h2 style="color: red;">HELLY HANSEN <b><i>NEEDS</i></b> TO BE SYNCED</h2>
            <h4><b>Last Sync: <?php echo $last_sync?></b></h4>
            <h4><b>Check Inventory Link:</b><a href="https://ng.ivendix.com/login/brands?jwt=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdXBhbGlhcyI6bnVsbCwic2lkIjpudWxsLCJ1aWQiOm51bGwsImNtdWlkIjo0NjY5MzAsInV1dSI6IkJFMjc1MzI4LUFGODYtNEFCOC1CQzIwLTYwQUI4NEQwQTJCQSIsImN1cmlkIjpudWxsLCJjaWQiOm51bGwsInR5cGUiOm51bGwsImlzcyI6InN0cy5jZW50ZXJzdG9uZXRlY2guY29tL3Nlc3Npb24iLCJzdWIiOjQ2NjkzMCwiZW1haWwiOiJncmVnQGl3YW50d29ya3dlYXIuY29tIiwibmFtZSI6IkdyZWcgQmFzdGlhbmVsbGkiLCJsYW5nIjoiZW4tVVMiLCJsaWQiOjEsInVuIjoiZ3JlZ2Jhc3QxOTk0IiwiZHMiOiJjc3RtYXN0ZXIiLCJVc2VyX1VVIjoiQkUyNzUzMjgtQUY4Ni00QUI4LUJDMjAtNjBBQjg0RDBBMkJBIiwiUHJvZmlsZV9JRCI6NDY2OTMwLCJWZXJ0aWNhbE5ldF9GbGciOm51bGwsImNmZHMiOm51bGwsImNhdCI6InJ6IiwicmV0IjoiciIsInN0b3JlIjoiciIsIm9yZCI6ImNydWQiLCJzdXAiOiJyIiwicHJkIjoiciIsImludiI6InIiLCJ1c3IiOiJydSIsInJlZyI6InIiLCJhcyI6ImNyIiwiaWF0IjoxNTc0Njk3MTg1LCJleHAiOjE1NzQ3MTE1ODV9.dpbNoENp7Uxd85p2Bi7u082EFFyiFkZWtIiF9WKNsNs&Domain=ivendix&VerticalNet_Flg=true" target="_blank">  Check Inventory</a></h4>

            <h1>How do I Sync HELLY HANSEN?</h1>
        <iframe width="400" height="200" src="https://www.youtube.com/embed/zH1hkzSxOLs" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        <?php endif; ?>

        <hr>

        <p>
            <label>Vendor: </label>
            <select name="vendor" >
                <option value="helly_hansen">Helly Hansen</option>
                <option value="portwest">Portwest</option>
                <option value="radians">Radians</option>
            </select>
        </p>

        <p>
            <label>Url: </label>
            <input type="url" name="url" placeholder="link to a CSV file or w/e" required/>
        </p>

        <p>
            <label><b>Actions:</b> </label><br>
            <input name="actions[stock]" id="stock" type="checkbox" value="1" checked />Stock<br>
            <input name="actions[dims]" id="dims" type="checkbox" value="1" />Weight/dimensions<br>
            <input name="actions[price]" id="price" type="checkbox" value="1" />Price
            <input type="number" id="price-modifier" name="actions[price-modifier]" style="width: 50%" placeholder="0.6 = 40% markup"><br>
            <input type="checkbox" id="dry_run" name="actions[dry_run]" value="1">DRY RUN (<b>nothing changes</b> while checked)
        </p>

        

        <span class="feedback" style="font-weight: 600; font-color: red; font-size: 18px; "></span>
        <input type="hidden" name="action" value="msp_admin_sync_vendor" />
        <button id="submit_update_vendor" type="submit" class="button button-primary" style="margin-top: 1rem;">Submit Vendor!</button>
    </form>
    <?php
    }

    public function process(){
    /**
     * This function puts together the data based on prebuilt rules.
     */

        foreach( $_POST['actions'] as $k => $v ){
            if( $v == '1' ){
                $this->flags[$k]['enabled'] = true;
            }
        }

        $this->dry_run = ( isset( $_POST['actions']['dry_run'] ) );
        $this->vendor = $_POST['vendor'];
        $this->url = $_POST['url'];

        // do this at the end
        update_option('msp_'. $this->vendor .'_last_sync', date('m-d-y'));

        $this->sync_with_data();
        wp_die();
    }

    private function sync_with_data(){
        /**
         * Loops through csv, looks for an ID (variation & simple products) with matching SKU
         * and updates accordingly.
         * @param array $vendor - The vendor, data source, and column information
         */

        $start = microtime(true);

        $count = 0;

        $data = wp_remote_get( $this->url )['body'];


        if( ! empty( $data ) ){
            foreach( $this->msp_csv_to_array( $data ) as $item ){
                if( isset( $item[ $this->get_index_of('sku') ] ) ){
                    $id = $this->msp_get_product_id_by_sku( $item[ $this->get_index_of('sku') ] );
                    if( ! empty( $id ) ){

                        // Loop throught flags, each flag will have a callback to a specific function
                        // Perform each function checked off.

                        foreach( $this->flags as $k => $v ){
                            $callback = $v['callback'];

                            if( $v['enabled'] && ! empty( $v['callback'] ) ){
                                // OPTIMIZE: Optimize $item by looping through $column mappings and only grabbing
                                // relevant columns of information.
                                $this->$callback( $id, $item );
                            }
                        }
                        // Doesn't particularly mean the product was actually updated.
                        $count++;
                    }
                }

            }
        } else {
            echo '$data is empty';
        }

        // CONVERT to generate_report(); function
        $time_elapsed_secs = microtime(true) - $start;
        $is_dry_run = ( $this->dry_run ) ? 'Yes' : "No";

        echo '<h2>Report</h2>';
        echo "Dry run:" . $is_dry_run . '<br>';
        echo 'Products Updated: ' . $count . '.<br>';
        echo 'Time Elasped:     ' . number_format( $time_elapsed_secs, 2 ) . ' seconds.<br>';
    }

    private function msp_get_product_id_by_sku( $sku = false ) {
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

    private function msp_csv_to_array( $data ){
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
    
    private function msp_update_stock( $id, $item ){
        /**
         * Prepares an array to pass to update based on stock quantity
         * @param int $id
         * @param array $item - The row data FROM the csv file pertaining to a specific variation
         */
    
         // NEVER EVER USE WC_PRODUCT object, update post meta!

         $stock = $item[ $this->get_index_of('stock') ];
         $next_delivery = $item[ $this->get_index_of('next_delivery') ];

        $updates = array(
            '_manage_stock' => 'yes',
            '_stock' => $stock,
        );
    
        if( $stock > 0 ){
            $updates['_stock_status'] = 'instock';
            $updates['_backorders'] = 'no';
        } else {    
            if( ! empty( $next_delivery ) && $this->vendor != 'helly_hansen' ){
                $updates['_stock_status'] = 'onbackorder';
                $updates['_backorders'] = 'notify';
                $updates['msp_sync_next_delivery'] = $next_delivery;
            } 
        }

        $this->update( $id, $updates );
    }

    private function get_index_of( $key ){
        /**
         * Convienience function for returning the index number based on vendor and key
         * @param string $key
         * @return int
         */
        return $this->column_mappings[$this->vendor][$key];
    }

    private function msp_update_dims( $id, $item ){
        /**
         * Converts Length, Width, Height from one unit to another. Example portwest
         * LxWxH from cm to inches. or KG to LB.
         * @param int $id
         * @param array $item - The row data FROM the csv file pertaining to a specific variation
         */
        $CM_TO_IN = 0.3937008;
        $KG_TO_LB = 2.204623;
        $updates = array();

        if( $this->vendor == 'portwest' ){
            $updates = array(
                '_length'=>  round( ($item[ $this->get_index_of('length') ] / 2 * $CM_TO_IN), 1 ),
                '_width'=>  round( ($item[ $this->get_index_of('width') ] / 2 * $CM_TO_IN), 1 ),
                '_height'=>  round( ($item[ $this->get_index_of('height') ] / 2 * $CM_TO_IN), 1 ),
                '_weight'=>  round( ($item[ $this->get_index_of('weight') ] * $KG_TO_LB ), 1),
            );
        } else {
            echo 'Dims / weights only setup for portwest';
        }

        $this->update( $id, $updates );
    }


    private function update( $id, $updates ){
        /**
         * Convienience function for incorporating dry runs into user feedback.
         */
        $str = "ID: $id |";
        foreach( $updates as $meta_key => $meta_value ){
            $str .= sprintf( " %s => %s | ",$meta_key, $meta_value );
            if( false == $this->dry_run ){
                update_post_meta( $id, $meta_key, $meta_value );
            }
        }
        echo $str . '<br>';

    }

} /** End sync class */

new Sync();

add_filter( 'woocommerce_get_availability_text', 'msp_get_availability', 100, 2 );

function msp_get_availability( $text, $_product ){
    /**
     * Determine how to format date based on vendor
     */

    $next_delivery = get_post_meta($_product->get_id(), 'msp_sync_next_delivery', true);
    $new_date = preg_replace("/(\d+)\D+(\d+)\D+(\d+)/","$2/$1/$3", $next_delivery);

    if ( $_product->managing_stock() && $_product->is_on_backorder( 1 ) && ! empty( $new_date ) ) {
        $text = "On backorder, item estimated to ship on or before  <strong>$new_date*</strong>";
    }

    return $text;
}
