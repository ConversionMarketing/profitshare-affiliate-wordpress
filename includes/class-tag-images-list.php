<?php

/* 	Keywords List
 * 	@ package: wp-profitshare
 * 	@ since: 1.3
 * 	Clasă pentru generarea tabelului cu lista de imagini tag-uite
 */
defined('ABSPATH') || exit;
if (!class_exists('WP_List_Table'))
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Tag_Images_List extends WP_List_Table {

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'img_title':
                return $item['title'];
                break;
            case 'img_src':
                if (!empty($item['image'])) {
                    return sprintf('<img src="%s" width="100" />', $item['image']);
                } else {
                    return '-';
                }
                break;
            case 'shortcode_embed':
                return sprintf('<input type="text" value="%s" onClick="this.select();" style="width:%s" />', esc_attr('[tag_image id="' . $item['ID'] . '" width="700"]'), esc_attr('70%'));
                break;
            case 'date':
                return date('Y-m-d H:i', strtotime($item['insert_date']));
                break;
            case 'action':
                return '<a href="' . admin_url('admin.php?page=ps_tag_image&do=edit&tag_id=' . $item['ID']) . '">Edit</a> | <a href="' . admin_url('admin.php?page=ps_tag_image&do=delete&tag_id=' . $item['ID']) . '">Delete</a>';
                break;
            default:
                return print_r($item);
                break;
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
            'img_title' => 'Title',
            'img_src' => 'Image',
            'shortcode_embed' => 'Shortcode',
            'date' => 'Date',
            'action' => 'Action'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'title' => array('title', true)
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

        $query = "SELECT * FROM " . $wpdb->prefix . "ps_tag_images";
        $data = $wpdb->get_results($query, ARRAY_A);

        function usort_reorder($a, $b) {
            $orderby = (!empty($_REQUEST['orderby']) ) ? $_REQUEST['orderby'] : 'ID';
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