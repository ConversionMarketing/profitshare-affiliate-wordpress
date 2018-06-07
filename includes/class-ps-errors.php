<?php
/*	History Links
 *	@ package: wp-profitshare
 *	@ since: 1.4.7
 *	Clasă pentru gestionarea erorilor profitshare
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class PS_Errors extends WP_List_Table {
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    const STATUS_LIST = array(self::STATUS_ACTIVE, self::STATUS_INACTIVE);

    const STATUS_TEXTS = array(
        self::STATUS_ACTIVE => 'Unread',
        self::STATUS_INACTIVE => 'Read'
    );

    const STATUS_ACTIVE_COLOR = "#16c016";
    const STATUS_INACTIVE_COLOR = "#cdcccc";

    function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'status':
                $color = $item[$column_name] === self::STATUS_ACTIVE ? self::STATUS_ACTIVE_COLOR : self::STATUS_INACTIVE_COLOR;
                return $this->add_color_to_text(self::STATUS_TEXTS[$item[$column_name]], $color);
            break;
            default:
                return $item[$column_name];
        }
    }

    function get_columns() {
        $columns = array(
            'ID'	=>	'Id',
            'message'		=>	'Message',
            'status'	=>	'Status',
            'updated_at'		=>	'Date'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'ID'	=>	array( 'ID', true ),
            'message'		=>	array( 'message', true ),
            'status'	=>	array( 'status', true ),
            'created_at'		=>	array( 'created_at', true ),
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->process_bulk_action();

        // Geting DB info, for listing

        $query = "SELECT * FROM " . $wpdb->prefix . "ps_errors";

        if($_GET['status']){
            $query = $query . " where status='" . $_GET['status']."'";
        }

        $data = $wpdb->get_results( $query, ARRAY_A );

        function usort_reorder( $a, $b ) {
            $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'date';
            $order = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
            $result = strcmp( $a[ $orderby ], $b[ $orderby ] );
            return ( 'asc' === $order ) ? $result : -$result;
        }

        $this->update_error(0);

        usort( $data, 'usort_reorder' );
        $this->items = $data;
    }

    public function extra_tablenav($position) {
        if($position == 'bottom') {
            return;
        } ?>
            <div class="alignleft actions bulkactions ps-filters">
                    <span class="ps-filter-title">Filter by</span>
                    <select name="conversions-status-filter" class="ps-select" id="ps-status-filter" parameter_name="status">
                        <option value="">All</option>
                        <?php
                        foreach(self::STATUS_LIST as $status){
                            $selected = '';
                            if( $_GET['status'] == $status){
                                $selected = ' selected = "selected"';
                            } ?>
                            <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo self::STATUS_TEXTS[$status]; ?></option>
                        <?php } ?>
                    </select>
            </div>
<?php
    }

    public function update_error($id, $status = self::STATUS_INACTIVE) {
        global $wpdb;

        $oldStatus = $status == self::STATUS_INACTIVE ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $query = "UPDATE ".$wpdb->prefix."ps_errors SET status='%s', updated_at = NOW() WHERE status='%s'";
        $prepared_query = $wpdb->prepare($query, $status, $oldStatus);

        if($id) {
            $query .= " AND id = %d";
            $prepared_query = $wpdb->prepare($query, $status, $oldStatus, $id);
        }

        $wpdb->query($prepared_query);
    }

    public function get_errors($status = self::STATUS_ACTIVE) {
        global $wpdb;

        $query = "SELECT * FROM " . $wpdb->prefix . "ps_errors WHERE status = '".$status."'";
        return $wpdb->get_results( $query, ARRAY_A );
    }

    private function add_color_to_text($text, $color) {
        return "<span style='color:$color;font-weight:600;'>$text</span>";
    }
}
?>