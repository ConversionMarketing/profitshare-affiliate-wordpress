<?php
/*	History Links
 *	@ package: wp-profitshare
 *	@ since: 1.0
 *	Clasă pentru generarea tabelului cu ultimele linkuri scurtate
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class History_Links extends WP_List_Table {
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'source':
				return $item['source'];
			case 'link':
				return '<a href="' . $item['link'] . '" target="_blank"">' . $item['link'] . '</a>';
            case 'shorted':
				return '<a href="' . $item['shorted'] . '" target="_blank"">' . $item['shorted'] . '</a>';
			case 'date':
				return date( 'd', $item['date'] ) . ' ' . ps_translate_month( date( 'n', $item['date'] ) ) . date( ' Y, H:i', $item['date'] );
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
			'source'	=>	'Source',
			'link'		=>	'Link advertiser',
			'shorted'	=>	'Link profitshare',
			'date'		=>	'Date',
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'source'	=>	array( 'source', true ),
			'link'		=>	array( 'link', true ),
			'shorted'	=>	array( 'shorted', true ),
			'date'		=>	array( 'date', true ),
		);
		return $sortable_columns;
	}

	function process_bulk_action() {
		/**
		 * void()
		 */
		return;
	}

	function prepare_items() {
		global $wpdb;
		$per_page = 25;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();

		// Geting DB info, for listing

		$query = "SELECT * FROM " . $wpdb->prefix . "ps_shorted_links";
		$data = $wpdb->get_results( $query, ARRAY_A );

		function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'date';
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