<?php
/*	Conversions
 *	@ package: wp-profitshare
 *	@ since: 1.0
 *	Clasă pentru generarea tabelului cu ultimele conversii
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Conversions extends WP_List_Table {
	private $ps_country = 'RO';
	private $ps_currency = 'RON';

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'order_date':
				$strtotime = strtotime( $item['order_date'] );
				return date( 'd', $strtotime ) . ' ' . ps_translate_month( date( 'n', $strtotime ) ) . date( ' Y, H:i', $strtotime );
			case 'items_commision':
				$conversions = explode("|", $item['items_commision']);
				return number_format(array_sum($conversions), 2) . ' ' . config( 'CURRENCY' );
            case 'order_status':
				return ( 'approved' == $item['order_status'] )?"<span class='ps_approved'>Approved</span>":(( 'pending' == $item['order_status'] )?"<span class='ps_pending'>Pending</span>":"<span class='ps_canceled'>Canceled</span>");
			case 'advertiser_id':
				global $wpdb;
				$advertiser_name = $wpdb->get_results( "SELECT name FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . $item['advertiser_id'] . "'", OBJECT );
				return $advertiser_name[0]->name;
			case 'order_last_update':
				$last_update = '-';
				if($item['order_last_update'] != '0000-00-00 00:00:00') {
					$last_update = '<b>'.$item['order_last_update'].'</b>';
				}
				return $last_update;
			case 'order_number':
				$order_number = '-';
				if($item['order_number']) {
					$order_number = $item['order_number'];
				}
				return $order_number;
			default:
				return print_r( $item );
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
			'order_number' 		=>  'Order number',
			'items_commision'	=>	'Commission value',
			'order_status'		=>	'Status',
			'advertiser_id'		=>	'Advertiser',
			'order_date'		=>	'Conversion date',
			'order_last_update'	=>  'Last update',
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'order_date'		=>	array( 'order_date', true ),
			'items_commision'	=>	array( 'items_commision', true ),
			'order_status'		=>	array( 'order_status', true ),
			'advertiser_id'		=>	array( 'advertiser_id', true ),
			'order_last_update' 	=>  array( 'order_last_update', true ),
			'order_number' 	=>  array( 'order_number', true )
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
			        $get_advertisers_name_sql = "SELECT name, psconv.advertiser_id FROM " . $wpdb->prefix . "ps_conversions as psconv LEFT JOIN " . $wpdb->prefix . "ps_advertisers using(advertiser_id) GROUP BY name";
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
					$conversions_status_list = [
						'approved',
						'pending',
						'canceled'
					];
				?>

				<?php if($conversions_status_list):?>
					<select name="conversions-status-filter" class="ps-select" id="ps-status-filter" parameter_name="conversions-status-filter">
		                <option value="">Status</option>
		                <?php
			                foreach($conversions_status_list as $conv_status){
			                	if(!$conv_status) {
			                		continue;
			                	}

			                    $selected = '';
			                    if( $_GET['conversions-status-filter'] == $conv_status){
			                        $selected = ' selected = "selected"';   
			                    } ?>
			                	<option value="<?php echo $conv_status; ?>" <?php echo $selected; ?>><?php echo $conv_status; ?></option>
			                <?php } ?>
					</select>
				<?php endif;?>
				<!-- end of status filter -->

		    	<!-- status filter -->
				<?php
					$conversions_min_sum = [
						1,
						5,
						10,
						15,
						20,
						25,
						50,
						75,
						100,
						150,
						300,
						600,
						1000
					];
				?>

				<?php if($conversions_min_sum):?>
					<select name="conversions-min-sum-filter" class="ps-select" id="conversions-min-sum-filter">
		                <option value="">Min conversion value</option>
		                <?php
			                foreach($conversions_min_sum as $min_sum){
			                	if(!$min_sum) {
			                		continue;
			                	}

			                    $selected = '';
			                    if( $_GET['conversions-min-sum-filter'] == $min_sum){
			                        $selected = ' selected = "selected"';   
			                    } ?>
			                	<option value="<?php echo $min_sum; ?>" <?php echo $selected; ?>><?php echo $min_sum.' '.$this->ps_currency; ?></option>
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

		// Se obţin informaţiile din baza de date, pentru listare

		$query = "SELECT * FROM " . $wpdb->prefix . "ps_conversions";

		// add filter
		$query_has_where = false;

		if($_GET['advertisers-filter']){
			$query_has_where = true;
            $query = $query . ' where advertiser_id=' . $_GET['advertisers-filter'];   
        }

        if($_GET['conversions-status-filter']){
            $query = $query . (($query_has_where) ? " and" : " where") . " order_status='" . $_GET['conversions-status-filter']."'";  
            $query_has_where = true; 
        }

        if($_GET['conversions-min-sum-filter']){
            $query = $query . (($query_has_where) ? " and" : " where") . " items_commision >= " . $_GET['conversions-min-sum-filter'];  
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