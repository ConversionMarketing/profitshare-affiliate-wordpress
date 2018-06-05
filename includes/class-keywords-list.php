<?php
/* 	Keywords List
 * 	@ package: wp-profitshare
 * 	@ since: 1.1
 * 	Clasă pentru generarea tabelului cu lista de cuvinte cheie
 */
defined('ABSPATH') || exit;
if (!class_exists('WP_List_Table'))
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Keywords_List extends WP_List_Table {
    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'keyword':
                return $item['keyword'];
            case 'link':
                return $item['link'];
            case 'action':
                return '<a href="' . admin_url('admin.php?page=ps_keywords_settings&do=edit&keyword_id=' . $item['ID']) . '">Edit</a> | <a href="' . admin_url('admin.php?page=ps_keywords_settings&do=delete&keyword_id=' . $item['ID']) . '">Delete</a>';
            default:
                return print_r($item);
        }
    }

    function column_title($item) {
        /**
         * void()
         */
        return;
    }

    function column_title_author($item) {
        /**
         * void()
         */
        return;
    }

    function column_cb($item) {
        /**
         * void()
         */
        return;
    }

    function get_columns() {
        $columns = array(
            'keyword' => 'Keyword',
            'link' => 'Link',
            'action' => 'Action'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'keyword' => array('keyword', true)
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
        $per_page = 10;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();

        // Geting DB info, for listing

        $query = "SELECT * FROM " . $wpdb->prefix . "ps_keywords";
        $data = $wpdb->get_results($query, ARRAY_A);

        function usort_reorder($a, $b) {
            $orderby = (!empty($_REQUEST['orderby']) ) ? $_REQUEST['orderby'] : 'keyword';
            $order = (!empty($_REQUEST['order']) ) ? $_REQUEST['order'] : 'desc';
            $result = strcmp($a[$orderby], $b[$orderby]);
            return ( 'asc' === $order ) ? $result : -$result;
        }

        usort($data, 'usort_reorder');
        $current_page = $this->get_pagenum();
        $total_items = count($data);
        $data = array_slice($data, ( ($current_page - 1) * $per_page), $per_page);
        $this->items = $data;
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
}