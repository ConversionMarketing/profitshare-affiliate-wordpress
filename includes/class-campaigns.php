<?php
/*	Campaigns
 *	@ package: wp-profitshare
 *	@ since: 1.0
 *	Clasă pentru preluarea datelor pentru campaniile profitshare
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Campaigns extends WP_List_Table {
	private $ps_country = 'RO';
	private $ps_currency = 'RON';

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'ps_url':
			case 'url':
				$html = "<input type='text' value='".$item[$column_name]."' class='ps-select-all'>";
				if(!$item[$column_name]) {
					$html = "<button class='btn btn-primary generate-url' campaign_id='".$item['ID']."'>Generate URL</button>";
				}
				return $html;
			case 'advertiser_id':
				global $wpdb;
				$advertiser_name = $wpdb->get_results( "SELECT name FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . $item['advertiser_id'] . "'", OBJECT );
				return $advertiser_name[0]->name;
			case 'ID':
				$view_banners_button = "<button class='btn-sm ps-btn btn btn-primary ps-banners-btn' id='".$item[$column_name]."'>View banners</button>";
				return $item[$column_name].' '.$view_banners_button;

			default:
				return $item[$column_name];
		}
	}

	function column_title( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function column_title_author( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function column_cb( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function get_columns() {
		$columns = array(
			'ID'				=>	'ID',
			'name'				=>	'Campaign name',
			'advertiser_id'		=>	'Advertiser',
			'url' 				=>  'URL',
			'ps_url'			=> 	'PS URL',
			'status'			=> 	'Status',
			'start_date'		=>  'Start date',
			'end_date'			=>  'End date',
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'ID'		=>	array( 'id', true ),
			'name'	=>	array( 'name', true ),
			'advertiser_id'		=>	array( 'advertiser_id', true ),
			'start_date'		=>	array( 'start_date', true ),
			'end_date'		=>	array( 'end_date', true ),
		);
		return $sortable_columns;
	}

	function process_bulk_action() {
		/**
		 * void()
		 */
		return;
	}

	function extra_tablenav($which) {
	    global $wpdb, $testiURL, $tablename, $tablet;
	    if ( $which == "top" ){
	        
	        ?>
	        <div class="alignleft actions bulkactions ps-filters">
	        	<span class="ps-filter-title">Filter by</span>
	        	<!-- advertisers filter -->
		        <?php
			        $get_advertisers_name_sql = "SELECT " . $wpdb->prefix . "ps_advertisers.name, pscampaign.advertiser_id FROM " . $wpdb->prefix . "ps_campaigns as pscampaign LEFT JOIN " . $wpdb->prefix . "ps_advertisers using(advertiser_id) GROUP BY " . $wpdb->prefix . "ps_advertisers.name";

			        $advertisers = $wpdb->get_results($get_advertisers_name_sql, ARRAY_A);
		        ?>
		        <?php if($advertisers):?>
					<select name="advertisers-filter" class="ps-select" id="ps-advertisers-filter">
		                <option value="">Advertiser name</option>
		                <?php
			                foreach($advertisers as $advertiser){
			                	if(!$advertiser || !isset($advertiser['name'])) {
			                		continue;
			                	}

			                    $selected = '';

			                    if( $_GET['advertisers-filter'] == $advertiser['advertiser_id']){
			                        $selected = ' selected = "selected"';   
			                    } ?>
				
			                	<option value="<?php echo $advertiser['advertiser_id']; ?>" <?php echo $selected; ?>><?php echo $advertiser['name']; ?></option>
			                <?php } ?>
					</select>
		    	<?php endif;?>
		    	<!-- end of advertisers filter -->

		    	<!-- status filter -->
				<?php
					$status_list = [
						'enabled',
						'disabled'
					];
				?>

				<?php if($status_list):?>
					<select name="conversions-status-filter" class="ps-select" id="status-filter">
		                <option value="">Status</option>
		                <?php
			                foreach($status_list as $status){
			                	if(!$status) {
			                		continue;
			                	}

			                    $selected = '';
			                    if( $_GET['status-filter'] == $status){
			                        $selected = ' selected = "selected"';   
			                    } ?>
			                	<option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
			                <?php } ?>
					</select>
				<?php endif;?>
				<!-- end of status filter -->
	    	</div>
	    <?php
	    }
	    if ( $which == "bottom" ){
	        //The code that goes after the table is there

	    }
	}

	function set_api_country($country) {
		if($this->ps_country != $country) {
			$this->ps_country = $country;

			if($country == 'BG') {
				$this->ps_currency = 'BGN';
			}
		}
	}

	function prepare_items() {
		global $wpdb;
		$per_page = 25;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();

		$query = "SELECT * FROM " . $wpdb->prefix . "ps_campaigns";

		// add filter
		$query_has_where = false;

		if($_GET['advertisers-filter']){
			$query_has_where = true;
            $query = $query . ' where advertiser_id=' . $_GET['advertisers-filter'];   
        }

        if($_GET['status-filter']){
            $query = $query . (($query_has_where) ? " and" : " where") . " status='" . $_GET['status-filter']."'";  
            $query_has_where = true; 
        }

		$data = $wpdb->get_results( $query, ARRAY_A );

		function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'order_date';
			$order = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
			$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
			return ( 'asc' === $order ) ? $result : -$result;
		}

		usort( $data, 'usort_reorder' );
		$current_page = $this->get_pagenum();
		$total_items = count( $data );
		$data = array_slice( $data, ( ($current_page-1) * $per_page ), $per_page );
		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}
?>