<?php
/**
 * Plugin Name: WP Profitshare
 * Plugin URI: https://www.profitshare.ro
 * Description: Converts all your direct links into affiliate links in order for you to earn commissions through Profitshare.
 * Version: 1.4.5
 * Author: Conversion
 * Author URI: https://www.conversion.ro
 * License: GPL2
 */
defined('ABSPATH') || exit;
define('PS_VERSION', '1.4.5');

require_once( 'includes/functions.php' );
require_once( 'includes/class-conversions.php' );
require_once( 'includes/class-campaigns.php' );
require_once( 'includes/class-history-links.php' );
require_once( 'includes/class-keywords-list.php' );
require_once( 'includes/class-tag-images-list.php' );

register_activation_hook(__FILE__, 'ps_init_settings');
register_deactivation_hook(__FILE__, 'ps_remove_settings');

add_action('admin_init', 'ps_check_update');
add_action('admin_enqueue_scripts', 'ps_enqueue_admin_assets');
add_action('wp_enqueue_scripts', 'ps_enqueue_assets');
add_action('wp_footer', 'ps_footer_js', 1);
add_action('admin_menu', 'ps_add_menus');
add_action('save_post', 'ps_auto_convert_posts');
add_action('comment_post', 'ps_auto_convert_comments');
add_filter('the_content', 'ps_filter_links');
add_filter('comment_text', 'ps_filter_links');
add_action('wp_dashboard_setup', 'ps_dashboard_widget');
add_action('post_submitbox_misc_actions', 'ps_limit_shorten_links');
add_action('wp_ajax_ps_replace_links_batch', 'ps_replace_links_batch');
add_shortcode('tag_image', 'ps_tag_image_shortcode');

function ps_check_update() {
    $version = get_option('ps_installed_version', '0');
    if (!version_compare($version, PS_VERSION, '=')) {
        ps_init_settings();
    }
}

function ps_enqueue_admin_assets() {
    $screen = get_current_screen();
    wp_enqueue_media();
    wp_enqueue_style('profitshare-admin-style', plugins_url('css/admin.css', __FILE__), array());

    // add assets on certain page
    if (!empty($screen->id) && $screen->id == 'profitshare_page_ps_keywords_settings') {
        wp_enqueue_script('profitshare-admin-script', plugins_url('js/admin.js', __FILE__), array('jquery'));
    } elseif (!empty($screen->id) && $screen->id == 'profitshare_page_ps_tag_image' && isset($_REQUEST['do'])) {
        wp_enqueue_media();
        wp_enqueue_style('admin-tag-image-taggd', plugins_url('css/taggd.css', __FILE__), array());
        wp_enqueue_script('admin-tag-image-taggd', plugins_url('js/jquery.taggd.js', __FILE__), array('jquery'));
        wp_enqueue_script('admin-tag-image', plugins_url('js/admin-tag-image.js', __FILE__), array('jquery'));
    } elseif (!empty($screen->id) && $screen->id == 'profitshare_page_ps_history_links' && isset($_REQUEST['batch'])) {
        wp_enqueue_style('jquery-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.21/themes/base/jquery-ui.css');
        wp_enqueue_style('admin-history-links-batch', plugins_url('css/admin-history-links-batch.css', __FILE__), array());
        wp_enqueue_script('jquery-ui-progressbar', false, array('jquery', 'jquery-ui'));
        wp_enqueue_script('admin-history-links-batch', plugins_url('js/admin-history-links-batch.js', __FILE__), array('jquery', 'jquery-ui-progressbar'));
    }
}

function ps_enqueue_assets() {
    wp_enqueue_style('ps-style', plugins_url('css/public.css', __FILE__), array());
    wp_enqueue_script('ps-script', plugins_url('js/public.js', __FILE__), array('jquery'));
}

function ps_footer_js() {
    global $wpdb, $post;

    $sql_limit = 5000;
    if (!empty($post->ID)) {
        $limit_keyword_links = ps_get_limit_keyword_links($post->ID);

        if ($limit_keyword_links == 'none') {
            return;
        } elseif ($limit_keyword_links > 0) {
            $sql_limit = $limit_keyword_links;
        }
    }

    $table_name = $wpdb->prefix . "ps_keywords";
    $links = $wpdb->get_results("SELECT * FROM $table_name LIMIT $sql_limit");
    if (!empty($links)) {
        ?>
        <script type='text/javascript'>
            jQuery().ready(function(e) {
        <?php
        foreach ($links as $link) {
            $openin = $link->openin == 'new' ? '_blank' : '_self';
            if ($link->tip_display == 'y') {
                $newText = $link->tip_style == 'light' ? "<a class='pslinks ttlight'" : "<a class='pslinks ttdark'";
                $newText .= " href='" . $link->link . "' target='" . $openin . "'>" . $link->keyword . "<span><div class='ttfirst'></div><strong>" . $link->tip_title . "</strong><br />";
                if ($link->tip_image != '') {
                    $newText .= "<img alt='CSS tooltip image' style='float:right; width:90px; margin:0 0 10px 10px;' src='" . $link->tip_image . "'>";
                }
                $newText .= $link->tip_description . "<div class='ttlast'>WP Profitshare 1.4.4</div></span></a>";
            } else {
                $newText = "<a class='pslinks' href='" . $link->link . "' title='" . $link->title . "' target='" . $openin . "'>" . $link->keyword . "</a>";
            }
            ?>
                    jQuery('p').each(function() {
                        var strNewString = jQuery(this).html().replace(/(<?php echo $link->keyword; ?>)(?![^<]*>|[^<>]*<\/)/gm, "<?php echo $newText; ?>");
                        jQuery(this).html(strNewString);
                    });
        <?php } ?>
            });
        </script>
        <?php
    }
}

function ps_add_menus() {
    /**
     * 	@since: 1.0
     * 	Creating Profitshare menu in Dashboard
     * 	With: Plugin Settings, Keyword Settings, Conversions, Link history, Istoric linkuri, Help
	 *  @since: 1.3.2
	 *  contributors, author and editors can also access the WP Profitshare plugin
     */
    add_menu_page('Profitshare', 'Profitshare', 'edit_others_posts', 'ps_account_settings', 'ps_account_settings', 'dashicons-chart-pie', 21);
    add_submenu_page('ps_account_settings', 'Plugin Settings', 'Plugin Settings', 'delete_posts', 'ps_account_settings', 'ps_account_settings');
    $current_user = wp_get_current_user();
    if (get_user_meta($current_user->ID, 'ps_is_api_connected', true)) {
        add_submenu_page('ps_account_settings', 'Keyword Settings', 'Keyword Settings', 'delete_posts', 'ps_keywords_settings', 'ps_keywords_settings');
        add_submenu_page('ps_account_settings', 'Conversions', 'Conversions', 'delete_posts', 'ps_conversions', 'ps_conversions');
        #add_submenu_page('ps_account_settings', 'Campaigns', 'Campaigns', 'delete_posts', 'ps_campaigns', 'ps_campaigns');
        add_submenu_page('ps_account_settings', 'Tag Image', 'Tag Image', 'delete_posts', 'ps_tag_image', 'ps_tag_image');
        add_submenu_page('ps_account_settings', 'Link history', 'Link history', 'delete_posts', 'ps_history_links', 'ps_history_links');
        add_submenu_page('ps_account_settings', 'Help', 'Help', 'delete_posts', 'ps_useful_info', 'ps_useful_info');
    }
}

function ps_account_settings() {
    /**
     * 	@since: 1.0
     * 	API Settings Page
     * 	Setting API connexion
     */
    ps_check_update();
    $current_user = wp_get_current_user();
    if (isset($_POST['disconnect'])) {
        delete_user_meta($current_user->ID, 'ps_api_user');
        delete_user_meta($current_user->ID, 'ps_api_key');
        delete_user_meta($current_user->ID, 'ps_api_country');
        delete_user_meta($current_user->ID, 'ps_is_api_connected');
        delete_option('ps_last_advertisers_update');
        delete_option('ps_last_conversions_update');
        delete_option('ps_account_balance');
        delete_option('ps_last_check_account_balance');
        delete_option('auto_convert_posts');
        delete_option('auto_convert_pages');
        delete_option('auto_convert_comments');
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . 'ps_advertisers');
        $wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . 'ps_conversions');
    } else if (isset($_POST['connect'])) {
        $api_user = esc_sql($_POST['api_user']);
        $api_key = esc_sql($_POST['api_key']);
        $api_country = esc_sql($_POST['api_country']);
        if (ps_connection_check($api_user, $api_key, $api_country)) {
            /**
             * 	Caching every:
             * 	24 h for advetisers
             * 	6 h for conversions
             */
            ps_update_advertisers_db(true);
            ps_update_conversions(true);
            ps_update_campaigns(true);
            echo '<meta http-equiv="refresh" content="0; url=' . admin_url('admin.php?page=ps_account_settings&ps_status=true') . '">';
        } else {
            #echo '<meta http-equiv="refresh" content="0; url=' . admin_url('admin.php?page=ps_account_settings&ps_status=false') . '">';
        }
    }
    $ps_api_user = get_user_meta($current_user->ID, 'ps_api_user', true);
    $ps_api_key = get_user_meta($current_user->ID, 'ps_api_key', true);
    $ps_api_country = get_user_meta($current_user->ID, 'ps_api_country', true);

    $is_api_connected = get_user_meta($current_user->ID, 'ps_is_api_connected', true);
    if ($is_api_connected) {
        $button_type = 'disconnect';
        #$button = '<input type="submit" name="disconnect" class="" id="ps-red" value="Disconnect" />';
        $disabled = 'disabled="disabled"';
        $country = get_user_meta($current_user->ID, 'ps_api_country', true);
    } else {
        $button_type = 'connect';
        #$button = '<input type="submit" name="connect" class="" id="ps-green" value="Connect" />';
        $disabled = '';
    }

    $alert = array();

    if($_POST['connect']) {
        if ($is_api_connected) {
            $alert['type'] = 'success';
            $alert['text'] = 'Connected: API data is correct and the connection has been successful established';
        } else if (!$is_api_connected) {
            $alert['type'] = 'error';
            $alert['text'] = 'Disconnected: API data is incorrect or connection error occurred.';
        } 
    }
    ?>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/css/bootstrap3/bootstrap-switch.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/js/bootstrap-switch.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
            <div class="row">
                <div class="col-md-10 col-xs-12 col-md-push-1 ps-shadow">
                    <h2 class="ps-h text-center ps-margin">Profitshare module options</h2>
                    <div class="row">
                        <div class="ps-left-content">
                            <?php if(isset($alert) && $alert):?>
                                <div class="ps-full-width ps-alert ps-error ps-full-width ps-<?php echo $alert['type'];?>">
                                    <span><?php echo $alert['text'];?></span>
                                </div>
                            <?php endif;?>
                        </div>
                        <div class="col-md-3 col-xs-12">
                            <form method="post" class="ps-full-width" action="">
                                <div class="ps-from-group">
                                    <label for="api_user">API user</label>
                                    <input id="api_user" class="regular-text ps-input" type="text" name="api_user" value="<?php echo $ps_api_user;?>"  <?php echo $disabled;?>>
                                </div>
                                <div class="ps-from-group">
                                    <label for="api_key">API key</label>
                                    <input id="api_key" class="regular-text ps-input" type="text" name="api_key" value="<?php echo $ps_api_key;?>"  <?php echo $disabled;?>>
                                </div>

                                <div class="ps-from-group">
                                    <label for="api_country">Country</label>
                                    <select id="api_country" class="ps-select" name="api_country" <?php echo $disabled; ?>>
                                        <?php
                                            global $ps_api_config;
                                            foreach ($ps_api_config AS $code => $array) {
                                                if (config('NAME') == $array['NAME']) {
                                                    echo '<option value="' . $code . '" selected="selected">' . $array['NAME'] . '</option>';
                                                } else {
                                                    echo '<option value="' . $code . '">' . $array['NAME'] . '</option>';
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                                <input type="submit" name="<?php echo $button_type;?>" class="ps-new-btn ps-capitalize <?php echo ($button_type == 'connect') ? 'ps-green' : 'ps-red';?>" value="<?php echo $button_type;?>">
                            </form>
                        </div>
        <?php
        if ($is_api_connected) {
            $auto_convert_posts = get_option('auto_convert_posts');
            $auto_convert_pages = get_option('auto_convert_pages');
            $auto_convert_comm = get_option('auto_convert_comments');

            if (isset($_POST['links_in_posts'])) {
                $auto_convert_posts ? $val = 0 : $val = 1;
                update_option('auto_convert_posts', $val);
            }
            if (isset($_POST['links_in_pages'])) {
                $auto_convert_pages ? $val = 0 : $val = 1;
                update_option('auto_convert_pages', $val);
            }
            if (isset($_POST['links_in_comments'])) {
                $auto_convert_comm ? $val = 0 : $val = 1;
                update_option('auto_convert_comments', $val);
            }
            $auto_convert_posts = get_option('auto_convert_posts');
            $auto_convert_pages = get_option('auto_convert_pages');
            $auto_convert_comm = get_option('auto_convert_comments');

            $posts_status = ($auto_convert_posts) ? true : false;
            $pages_status = ($auto_convert_pages) ? true : false;
            $comment_status = ($auto_convert_comm) ? true : false;
            ?>
                        <div class="col-md-4 col-xs-12">
                            <div class="ps-option mt9">
                                <h3 class='ps-h3 ps-no_margin_top'>Profitshare links in posts</h3>
                                <form action="" method="post" style="display: inline;">
                                    <!--<input type="submit" name="links_in_posts" class='ps-option-btn ps-new-btn ps-green ps-inline <?php echo $form_post['css_id']; ?>' value="<?php echo $form_post['input_value']; ?>" />-->
                                    <input type="checkbox" name="links_in_posts" <?php echo ($posts_status) ? 'checked' : '';?> data-on-color="success" data-off-color="danger" data-label-width="30" data-handle-width="40" data-size="small">
                                </form>          
                                <form action="<?php echo admin_url('admin.php'); ?>" method="get" style="display: inline;">
                                    <input type="hidden" name="page" value="ps_history_links" />
                                    <input type="hidden" name="batch" value="posts" />
                                    <input type="submit" name="submit" class='btn btn-primary ps-btn ps-generate' value="Generate" />
                                </form>                
                            </div>

                            <div class="ps-option">
                                <h3 class='ps-h3 ps-no_margin_top'>Profitshare links in pages</h3>
                                <form action="" method="post" style="display: inline;">
                                    <input type="checkbox" name="links_in_pages" <?php echo ($pages_status) ? 'checked' : '';?> data-on-color="success" data-off-color="danger" data-label-width="30" data-handle-width="40" data-size="small">
                                </form>          
                                <form action="<?php echo admin_url('admin.php'); ?>" method="get" style="display: inline;">
                                    <input type="hidden" name="page" value="ps_history_links" />
                                    <input type="hidden" name="batch" value="posts" />
                                    <input type="submit" name="submit" class='btn btn-primary ps-btn ps-generate' value="Generate" />
                                </form>                
                            </div>


                            <div class="ps-option">
                                <h3 class='ps-h3 ps-no_margin_top'>Profitshare links in comments</h3>
                                <form action="" method="post" style="display: inline;">
                                    <input type="checkbox" <?php echo ($comment_status) ? 'checked' : '';?> name="links_in_comments" data-on-color="success" data-off-color="danger" data-label-width="30" data-handle-width="40" data-size="small">
                                </form>          
                                <form action="<?php echo admin_url('admin.php'); ?>" method="get" style="display: inline;">
                                    <input type="hidden" name="page" value="ps_history_links" />
                                    <input type="hidden" name="batch" value="comments" />
                                    <input type="submit" name="submit" class='btn btn-primary ps-btn ps-generate' value="Generate" />
                                </form>                
                            </div>
                        </div>
                        <div class="col-md-4 col-xs-12">
                            <div class="ps-alerts">
                                <div class="ps-alert ps-info-alert">
                                    By clicking on "Generate!" all your direct links from all your posts/ comments published so far will be converted into affiliate links.
                                </div>
                                <div class="ps-alert ps-info-alert">
                                    By clicking on "Enable!" all the links from your future posts/ comments will be automatically converted into Profitshare affiliate links.
                                </div>
                                <div class="ps-alert ps-info-alert ps-danger-alert">
                                    We recommend making a backup of you database before running this functionality.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php
        }
        ?>

    <script>
        $("[name='links_in_comments']").bootstrapSwitch();
        $("[name='links_in_pages']").bootstrapSwitch();
        $("[name='links_in_posts']").bootstrapSwitch();

        $('input[name="links_in_comments"]').on('switchChange.bootstrapSwitch', function(event, state) {
          var current_option = state;
          js_post(location.href, 'links_in_comments');
        });
        $('input[name="links_in_posts"]').on('switchChange.bootstrapSwitch', function(event, state) {
          var current_option = state;
          js_post(location.href, 'links_in_posts');
        });
        $('input[name="links_in_pages"]').on('switchChange.bootstrapSwitch', function(event, state) {
          var current_option = state;
          js_post(location.href, 'links_in_pages');
        });


        function js_post(path, name) {
            var form = $('<form></form>');

            form.attr("method", "post");
            form.attr("action", path);

            var field = $('<input></input>');

            field.attr("type", "hidden");
            field.attr("name", name);

            form.append(field);

            // The form needs to be a part of the document in
            // order for us to be able to submit it.
            $(document.body).append(form);
            form.submit();
        }
    </script>
    </div>
    <?php
}

function ps_keywords_settings() {
    global $wpdb;
    /**
     * 	@since: 1.1
     * 	Keyword settings
     */
    ?>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
        <h2 class="ps-h ps-margin">Keyword Settings</h2>
        <a href="<?php echo admin_url('admin.php?page=ps_keywords_settings&do=add'); ?>" class="btn btn-success">Add keyword</a>
            <?php if (!empty($_REQUEST['do']) && $_REQUEST['do'] != 'delete') { ?><a class="btn btn-primary" href="<?php echo admin_url('admin.php?page=ps_keywords_settings'); ?>">Show keywords list</a><?php } ?>
        <?php
        $show_table = true;
        $errors = array();
        $success = array();
        // ACTIONS
        if (!empty($_REQUEST['do'])) {
            $table_name = $wpdb->prefix . 'ps_keywords';
            switch ($_REQUEST['do']) {
                case 'delete':
                    if (!empty($_REQUEST['keyword_id'])) {
                        $do_query = $wpdb->delete($table_name, array('ID' => $_REQUEST['keyword_id']), array('%d'));
                        if ($do_query) {
                            $success[] = 'Keyword deleted.';
                        } else {
                            $errors[] = 'Keyword delete error.';
                        }
                    }
                    break;
                case 'edit':
                    if (!empty($_REQUEST['keyword_id']) && !empty($_POST)) {
                        if ($_POST['keyword'] == "") {
                            $errors[] = "<strong>Keyword</strong> required.";
                        }
                        if ($_POST['title'] == "") {
                            $errors[] = "<strong>Title</strong> required.";
                        }
                        if ($_POST['link'] == "") {
                            $errors[] = "<strong>Link </strong> required.";
                        }
                        if ($_POST['link'] != "" && !preg_match('#^https?://profitshare\.ro/l/#i', $_POST['link']) && config('PS_HOME') == 'http://profitshare.ro') {
                            $errors[] = "<strong>Your link</strong> must start with " . config('PS_HOME') . "/l/";
                        }
                        if ($_POST['link'] != "" && !preg_match('#^https?://profitshare\.bg/l/#i', $_POST['link']) && config('PS_HOME') == 'http://profitshare.bg') {
                            $errors[] = "<strong>Your link</strong> must start with " . config('PS_HOME') . "/l/";
                        }
                        if ($_POST['openin'] == "") {
                            $errors[] = "<strong>Open link in...</strong> required.";
                        }
                        if ($_POST['tip_display'] == "1") {
                            if ($_POST['tip_title'] == "") {
                                $errors[] = "<strong>Tooltip title</strong> required.";
                            }
                            if ($_POST['tip_description'] == "") {
                                $errors[] = "<strong>Tooltip description</strong> required.";
                            }
                        }
                        if (empty($errors)) {
                            if (!preg_match('#^https?://#', $_POST['link'])) {
                                $_POST['link'] = 'https://' . $_POST['link'];
                            }
                            $wpdb->update(
                                    $table_name, array(
                                'keyword' => $_POST['keyword'],
                                'title' => $_POST['title'],
                                'link' => $_POST['link'],
                                'openin' => $_POST['openin'],
                                'tip_display' => $_POST['tip_display'],
                                'tip_style' => $_POST['tip_style'],
                                'tip_title' => $_POST['tip_title'],
                                'tip_description' => $_POST['tip_description'],
                                'tip_image' => $_POST['tip_image']
                                    ), array('ID' => $_POST['ID']), array(
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s'
                                    ), array(
                                '%d'
                                    )
                            );
                            $success[] = 'Keyword saved.';
                        }
                    }
                    $show_table = false;
                    break;
                case 'add':
                    if (!empty($_POST)) {
                        if ($_POST['keyword'] == "") {
                            $errors[] = "<strong>Keyword</strong> required.";
                        }
                        if ($_POST['title'] == "") {
                            $errors[] = "<strong>Title</strong> required.";
                        }
                        if ($_POST['link'] == "") {
                            $errors[] = "<strong>Link</strong> required.";
                        }
                        if ($_POST['openin'] == "") {
                            $errors[] = "<strong>Open link in...</strong> required.";
                        }
                        if ($_POST['tip_display'] == "1") {
                            if ($_POST['tip_title'] == "") {
                                $errors[] = "<strong>Tooltip title</strong> required.";
                            }
                            if ($_POST['tip_description'] == "") {
                                $errors[] = "<strong>Tooltip description</strong> required.";
                            }
                        }
                        if (empty($errors)) {
                            if (!preg_match('#^https?://#', $_POST['link'])) {
                                $_POST['link'] = 'https://' . $_POST['link'];
                            }
                            $wpdb->insert(
                                    $table_name, array(
                                'keyword' => $_POST['keyword'],
                                'title' => $_POST['title'],
                                'link' => $_POST['link'],
                                'openin' => $_POST['openin'],
                                'tip_display' => $_POST['tip_display'],
                                'tip_style' => $_POST['tip_style'],
                                'tip_title' => $_POST['tip_title'],
                                'tip_description' => $_POST['tip_description'],
                                'tip_image' => $_POST['tip_image']
                                    ), array(
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s'
                                    )
                            );
                            $success[] = 'Keyword saved.';
                        }
                    }
                    $show_table = false;
                    break;
            }
        }
        if (!empty($success) && is_array($success)) {
            ?>
            <div class="errors-list">
                <?php foreach ($success as $msg) { ?>
                    <div class="alert alert-success">
                        <p><?php echo $msg; ?></p>
                    </div>
                <?php } ?>
            </div>
            <?php
        }
        if (!empty($errors) && is_array($errors)) {
            ?>
            <div class="errors-list">
                <?php foreach ($errors as $msg) { ?>
                    <div class="alert alert-danger">
                        <p><?php echo $msg; ?></p>
                    </div>
                <?php } ?>
            </div>
            <?php
        }
        // VIEWS
        if (!empty($_REQUEST['do'])) {
            switch ($_REQUEST['do']) {
                case 'edit':
                    if (!empty($_REQUEST['keyword_id'])) {
                        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ID = %d", $_REQUEST['keyword_id']), ARRAY_A);
                        ?>
                        <form method="post">
                            <table class="form-table ps-form-list">
                                <tbody>
                                    <tr valign="top">
                                        <th scope="row"><label for="keyword">Keyword</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="keyword" value="<?php echo!empty($_POST['keyword']) ? $_POST['keyword'] : $row['keyword']; ?>" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="title">Title</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="title" value="<?php echo!empty($_POST['title']) ? $_POST['title'] : $row['title']; ?>" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="link">Link</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="link" value="<?php echo!empty($_POST['link']) ? $_POST['link'] : $row['link']; ?>" />
                                        </td>
                                    </tr>  
                                    <tr valign="top">
                                        <th scope="row"><label for="openin">Open link in</label></th>
                                        <td>
                                            <select name="openin" class="ps-select">
                                                <option value="new"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'new') || $row['openin'] == 'new' ? ' SELECTED' : ''; ?>>New window or tab (_blank) </option>
                                                <option value="current"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'current') || $row['openin'] == 'current' ? ' SELECTED' : ''; ?>>Same window or tab (_none)</option>
                                            </select>
                                        </td>
                                    </tr>  
                                    <tr valign="top" class="ps-checkbox">
                                        <th scope="row"><label for="tip_display">Tooltip</label></th>
                                        <td>
                                            <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'y') || $row['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Yes</label>
                                            <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'n') || $row['tip_display'] == 'n' ? ' checked="checked"' : ''; ?>>No</label>
                                        </td>
                                    </tr>    
                                    <tr valign="top" class="tip_display_1 hide_display ps-checkbox">
                                        <th scope="row"><label for="tip_style">Design tooltip</label></th>
                                        <td>
                                            <label><input name="tip_style" type="radio" value="light"<?php echo (!empty($_POST['tip_style']) && $_POST['tip_style'] == 'light') || $row['tip_style'] == 'light' ? ' checked="checked"' : ''; ?>>Light</label>
                                            <label><input name="tip_style" type="radio" value="dark"<?php echo (!empty($_POST['tip_style']) && $_POST['tip_style'] == 'dark') || $row['tip_style'] == 'dark' ? ' checked="checked"' : ''; ?>>Dark</label>
                                        </td>
                                    </tr>   
                                    <tr valign="top" class="tip_display_1 hide_display">
                                        <th scope="row"><label for="tip_title">Tooltip title</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="tip_title" value="<?php echo!empty($_POST['tip_title']) ? $_POST['tip_title'] : $row['tip_title']; ?>" />
                                        </td>
                                    </tr>      
                                    <tr valign="top" class="tip_display_1 hide_display">
                                        <th scope="row"><label for="tip_description">Tooltip description</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="tip_description" value="<?php echo!empty($_POST['tip_description']) ? $_POST['tip_description'] : $row['tip_description']; ?>" />
                                        </td>
                                    </tr>  
                                    <tr valign="top" class="tip_display_1 hide_display ps-checkbox">
                                        <th scope="row"><label for="tip_image">Tooltip image</label></th>
                                        <td>
                                            <input class="upload_image_input" type="text" name="tip_image" value="<?php echo!empty($_POST['tip_image']) ? $_POST['tip_image'] : $row['tip_image']; ?>" id="upload_image_1" />
                                            <input class="button-primary upload_image_button" data-id="1" type="button" value="Select Image" />
                                        </td>
                                    </tr>                                          
                                    <tr valign="top">
                                        <th scope="row">
                                            <input type="hidden" value="<?php echo $row['ID']; ?>" name="ID">
                                            <?php submit_button(); ?>
                                        </th>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                        <?php
                    }
                    break;
                case 'add':
                    ?>
                    <form method="post">
                        <table class="form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><label for="keyword">Keyword</label></th>
                                    <td>
                                        <input class="large-text ps-input" type="text" name="keyword" value="<?php echo!empty($_POST['keyword']) ? $_POST['keyword'] : ''; ?>" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="title">Title</label></th>
                                    <td>
                                        <input class="large-text ps-input" type="text" name="title" value="<?php echo!empty($_POST['title']) ? $_POST['title'] : ''; ?>" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="link">Link</label></th>
                                    <td>
                                        <input class="large-text ps-input" type="text" name="link" value="<?php echo!empty($_POST['link']) ? $_POST['link'] : ''; ?>" />
                                    </td>
                                </tr>  
                                <tr valign="top">
                                    <th scope="row"><label for="openin">Open link in</label></th>
                                    <td>
                                        <select name="openin" class="ps-select">
                                            <option value="new"<?php echo!empty($_POST['openin']) && $_POST['openin'] == 'new' ? ' SELECTED' : ''; ?>>New window or tab (_blank)</option>
                                            <option value="current"<?php echo!empty($_POST['openin']) && $_POST['openin'] == 'current' ? ' SELECTED' : ''; ?>>Same window or tab (_none)</option>
                                        </select>
                                    </td>
                                </tr>  
                                <tr valign="top">
                                    <th scope="row"><label for="tip_display">Tooltip</label></th>
                                    <td>
                                        <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo!empty($_POST['tip_display']) && $_POST['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Yes</label>
                                        <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo!empty($_POST['tip_display']) && $_POST['tip_display'] == 'n' ? ' checked="checked"' : ''; ?><?php echo empty($_POST) ? ' checked="checked"' : ''; ?>>No</label>
                                    </td>
                                </tr>    
                                <tr valign="top" class="tip_display_1 hide_display">
                                    <th scope="row"><label for="tip_style">Design tooltip</label></th>
                                    <td>
                                        <label><input name="tip_style" type="radio" value="light"<?php echo!empty($_POST['tip_style']) && $_POST['tip_style'] == 'light' ? ' checked="checked"' : ''; ?>>Light</label>
                                        <label><input name="tip_style" type="radio" value="dark"<?php echo!empty($_POST['tip_style']) && $_POST['tip_style'] == 'dark' ? ' checked="checked"' : ''; ?><?php echo empty($_POST) ? ' checked="checked"' : ''; ?>>Dark</label>
                                    </td>
                                </tr>   
                                <tr valign="top" class="tip_display_1 hide_display">
                                    <th scope="row"><label for="tip_title">Tooltip title</label></th>
                                    <td>
                                        <input class="large-text ps-input" type="text" name="tip_title" value="<?php echo!empty($_POST['tip_title']) ? $_POST['tip_title'] : ''; ?>" />
                                    </td>
                                </tr>      
                                <tr valign="top" class="tip_display_1 hide_display">
                                    <th scope="row"><label for="tip_description">Tooltip description</label></th>
                                    <td>
                                        <input class="large-text ps-input" type="text" name="tip_description" value="<?php echo!empty($_POST['tip_description']) ? $_POST['tip_description'] : ''; ?>" />
                                    </td>
                                </tr>  
                                <tr valign="top" class="tip_display_1 hide_display">
                                    <th scope="row"><label for="tip_image">Tooltip image</label></th>
                                    <td>
                                        <input class="upload_image_input" type="text" name="tip_image" value="<?php echo!empty($_POST['tip_image']) ? $_POST['tip_image'] : ''; ?>" id="upload_image_1" />
                                        <input class="button-primary upload_image_button" data-id="1" type="button" value="Select Image" />
                                    </td>
                                </tr>                                          
                                <tr valign="top">
                                    <th scope="row"><?php submit_button(); ?></th>
                                    <td></td>
                                </tr>                                     
                            </tbody>
                        </table>
                    </form>

                    <?php
                    break;
            }
        }
        /**
         * Show table only when needed
         */
        if (!empty($show_table)) {
            $keywords = new Keywords_List();
            $keywords->prepare_items();
            $keywords->display();
        }
        ?>
    </div>
    <?php
}

function ps_campaigns() {
    global $wpdb;
    ?>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
        <h2 class="ps-h">Advertisers campaigns</h2>
    </div>
    
    <!-- generate campaign url -->
    <?php
        ps_update_campaigns();
        if(isset($_GET['generate_campaign_url']) && $_GET['generate_campaign_url']) {
            $campaign_id = $_GET['generate_campaign_url'];

            // get campaign data
            $campaign_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "ps_campaigns WHERE ID = %d", $campaign_id), ARRAY_A);

            if($campaign_data) {
                // check for url
                $affiliate_url = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "ps_shorted_links WHERE link = %s", $campaign_data['url']), ARRAY_A);
                // create url
                if(!$affiliate_url) {
                    $ps_shorten_link = ps_shorten_link('WP Profitshare - Campaign'.$campaign_data['name'], $campaign_data['url']);
                    $wpdb->update($wpdb->prefix . "ps_campaigns", array('ps_url' => $ps_shorten_link['shorted']), array('ID' => $campaign_data['ID'])); 
                }
            }
        }
    ?>
    <!-- end of generate campaign url -->

    <!-- current campaign view-->
    <?php
        if(isset($_GET['campaign_id'])) {
            
            $campaign_id = $_GET['campaign_id'];
            // get data
            $campaign_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "ps_campaigns WHERE ID = %d", $campaign_id), ARRAY_A);

            if($campaign_data) {
                $campaign_data['banners'] = json_decode($campaign_data['banners'], true);

                ?>
                <div class="campaign_details">
                    <div class="campaign_name">
                        <b>Campaign name:</b> <span><?php echo $campaign_data['name'];?></span>
                    </div>
                    <div class="advertiser_name">
                        <b>Advertiser:</b> <span><?php echo $campaign_data['advertiser_id'];?></span>
                    </div>
                    <div class="campaign_url">
                        <b>Campaign URL:</b> <span><?php echo $campaign_data['url'];?></span>
                    </div>
                    <?php if(isset($campaign_data['ps_url']) && $campaign_data['ps_url']):?>
                        <div class="ps_url">
                            <b>PS URL:</b> <span><?php echo $campaign_data['ps_url'];?></span>
                        </div>
                    <?php endif;?>
                    <div class="status">
                        <b>Status:</b> <span><?php echo $campaign_data['status'];?></span>
                    </div>
                    <div class="start_date">
                        <b>Start date:</b> <span><?php echo $campaign_data['start_date'];?></span>
                    </div>
                    <div class="end_date">
                        <b>End date:</b> <span><?php echo $campaign_data['end_date'];?></span>
                    </div>
                    <?php if(isset($campaign_data['banners']) && $campaign_data['banners']):?>
                        <div class="banners">
                            <b>Campaign banners:</b> 
                            <div class="banners_list">
                                <?php foreach($campaign_data['banners'] as $banner):?>
                                    <?php if(isset($banner['width']) && isset($banner['height']) && $banner['width'] && $banner['height']):?>
                                        <div class="campaign_banner_button">
                                            <a href="<?php echo $banner['src']?>" target="_blank" class="btn btn-primary ps-view-banner-btn"><?php echo $banner['width'].'x'.$banner['height'];?></a>
                                        </div>
                                    <?php endif;?>
                                <?php endforeach;?>
                            </div>
                        </div>
                    <?php endif;?>
                </div>
                <?php      
            }
        }

    ?>    
    
    <!-- end of current campaign view -->

    <?php
        $campaigns = new Campaigns();
        // set country
        $current_user = wp_get_current_user();
        $campaigns->set_api_country(get_user_meta($current_user->ID, 'ps_api_country', true));
        $campaigns->prepare_items();            
        $campaigns->display();
    ?>
    <style>
        .modal-dialog {
            width: fit-content;
        }
    </style>
    <script>
        jQuery(document).ready( function($) {
            $(".generate-url").click( function() {
                var campaign_id = $(this).attr('campaign_id');

                if(campaign_id == 'undefined') {
                    return false;
                }

                if(campaign_id != ''){
                    document.location.href = location.href + '&generate_campaign_url='+campaign_id;
                    return true;
                }

                document.location.href = removeParam('generate_campaign_url', location.href);
            });
        });
        function copy_to_clipboard(text) {
            // create temp input
            var $temp_input = $("<input>");
            $("body").append($temp_input);

            // add input value and select
            $temp_input.val(text).select();

            // copy to clipboard
            document.execCommand("copy");
            
            // remove temp element
            $temp_input.remove();

            return true;
        }


        $('.copy-btn').on('click', function(){
            var copy_content = $(this).attr('copy-content');

            if(copy_content == 'undefined') {
                return false;
            }

            if(copy_to_clipboard(copy_content)) {
                alert('Successfully copied to clipboard!');
            }

        });

        $('.ps-select-all').focus(function(){
            $(this).select();
        });

        $('#ps-advertisers-filter').live('change', function(){
            var advertiser_filter = $(this).val();

            if(advertiser_filter != ''){
                document.location.href = location.href + '&advertisers-filter='+advertiser_filter;
                return true;
            }

            document.location.href = removeParam('advertisers-filter', location.href);
        });

        $('#status-filter').live('change', function(){
            var status_filter = $(this).val();

            if(status_filter != ''){
                document.location.href = location.href + '&status-filter='+status_filter;
                return true;
            }

            document.location.href = removeParam('status-filter', location.href);
        });

        $('.ps-banners-btn').on('click', function(){
            // get campaign id
            var campaign_id = $(this).attr('id');
            
            if(campaign_id == 'undefined') {
                return true;
            }

            window.location.href = "admin.php?page=ps_campaigns&campaign_id="+campaign_id;
        });

    </script>    


    <?php
}

function ps_conversions() {
    /**
     * 	@since: 1.0
     * 	Pagina Conversii
     * 	Afişează ultimele conversii preluate prin API
     * 	Se poate scurta un link de la unul dintre advertiserii din baza de date
     */
    ?>
    <?php
        // check for module update
        ps_check_update();

        // update conversions & advertisers
        ps_update_advertisers_db();
        ps_update_conversions();

        // ps url without protocol
        $ps_url = ':'.ps_remove_link_protocol(config('PS_HOME'));

        // get last update
        $data_last_update = date('d.m.Y H:i:s', get_option('ps_last_conversions_update'));
    ?>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/css/bootstrap3/bootstrap-switch.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.4/js/bootstrap-switch.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
        <h2 class="ps-h">Conversions</h2>

        <div class="row">
            <div class="col-md-5 col-xs-12 current_earnings">
                <div class="ps-earnings-content col-md-6 col-xs-12 text-center">
                    <div>Your current earnings are</div>
                    <div class="ps-earnings"><?php echo ps_account_balance(); ?></div>
                </div>
                <div class="col-md-6 col-xs-12">
                    <?php
                        // update conversions
                        if(isset($_POST['update_conversions'])) {
                            ps_update_advertisers_db(true);
                            ps_update_conversions(true);
                    ?>
                        <div class="ps-alert ps-white">
                            Your conversions has been updated with success!
                        </div>
                    <?php
                        }
                    ?>

                    <form method="post" action="">
                        <input type="submit" name="update_conversions" class="ps-new-btn ps-mobile-center" value="Update conversions list" />
                        <?php if($data_last_update):?>
                             <div class="ps-info"><b>Last update:</b> <?php echo $data_last_update;?></div>
                        <?php endif;?>
                    </form>
                </div>
            </div>
            <div class="col-md-6 col-xs-12 generate_links_content">
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <td class="p0">
                                <div class="ps-button-sticky-to-input ps-right">
                                    <input type="text" class="ps-input" name="link" placeholder="https://" id="link" class="regular-text" />
                                    <input type="submit" name="submit_link" class="ps-new-btn ps-green ps-inline" value="Get link" />
                                    <div class="ps_checkbox_big">
                                        <input type="checkbox" data-on-text="HTTP" data-off-text="HTTPS" name="link_type" data-on-color="info" data-off-color="primary" data-label-width="30" data-handle-width="40" data-size="small">
                                    </div>
                                </div>
                                
                            </td>
                        </tr>
                        <tr>
                            <?php
                            // generate link
                            if (isset($_POST['submit_link'])) {
                                $protocol_post = $_POST['link_type'];
                                $url_protocol = 'https';

                                if($protocol_post) {
                                    $url_protocol = 'http';
                                }  

                                $link = esc_sql($_POST['link']);
                                $ps_shorten_link = ps_shorten_link('WP Profitshare', $link);
                                if (!$ps_shorten_link['result']) {?>
                                    <?php if(isset($ps_shorten_link['errors'])): ?>
                                        <div class="ps-alert ps-right ps-error ps-error-big"><span>You are not promoting this advertiser, click <a target="_blank" href="//app.<?php echo ps_remove_link_protocol(config('PS_HOME'), "");?>/affiliate/advertiser-catalog">here</a> to apply.</span></div>
                                    <?php else: ?>
                                        <div class="ps-alert ps-right ps-error ps-error-big"><span>An error occurred or link is not part of Profitshare and can not be generated .</span></div>
                                    <?php endif;?>
                                <?php
                            } else {
                                ?>
                                <td class="td-padding">
                                    </div>
                                    <div class="ps-generated-link ps-right">
                                        <div class="ps-button-sticky-to-input ps-right">
                                            <input onClick="this.setSelectionRange(0, this.value.length)" class="ps-input ps-generated-link-input" type="text" id="generated_link" value="<?php echo $url_protocol.':'.$ps_shorten_link['shorted']; ?>" />
                                            <input type="submit" name="submit_link" class="ps-new-btn ps-green ps-inline" id="copy_link" value="Copy" />
                                        </div>
                                    </div>
                                </td>
                                <?php
                            }
                        }
                        ?>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
        <?php
            $conversions = new Conversions();
            // set country
            $current_user = wp_get_current_user();
            $conversions->set_api_country(get_user_meta($current_user->ID, 'ps_api_country', true));
            $conversions->prepare_items();            
            $conversions->display();
        ?>
    </div>
    <script>
        $('#copy_link').on('click', function(e){
            e.preventDefault();
            var link = $('#generated_link').val();
            copyToClipboard(link);
        });

        function copyToClipboard(text) {
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(text).select();
            document.execCommand("copy");
            $temp.remove();
            alert('Link copied to clipboard!');
        }

        $("[name='link_type']").bootstrapSwitch();

        $('#ps-advertisers-filter').live('change', function(){
            var advertiser_filter = $(this).val();

            if(advertiser_filter != ''){
                document.location.href = location.href + '&advertisers-filter='+advertiser_filter;
                return true;
            }

            document.location.href = removeParam('advertisers-filter', location.href);
        });

        $('#ps-status-filter').live('change', function(){
            var status_filter = $(this).val();

            if(status_filter != ''){
                document.location.href = location.href + '&conversions-status-filter='+status_filter;
                return true;
            }

            document.location.href = removeParam('conversions-status-filter', location.href);
        });

        $('#conversions-min-sum-filter').live('change', function(){
            var min_sum = $(this).val();

            if(min_sum != ''){
                document.location.href = location.href + '&conversions-min-sum-filter='+min_sum;
                return true;
            }

            document.location.href = removeParam('conversions-min-sum-filter', location.href);

        });

        function removeParam(key, sourceURL) {
            var rtn = sourceURL.split("?")[0],
                param,
                params_arr = [],
                queryString = (sourceURL.indexOf("?") !== -1) ? sourceURL.split("?")[1] : "";
            if (queryString !== "") {
                params_arr = queryString.split("&");
                for (var i = params_arr.length - 1; i >= 0; i -= 1) {
                    param = params_arr[i].split("=")[0];
                    if (param === key) {
                        params_arr.splice(i, 1);
                    }
                }
                rtn = rtn + "?" + params_arr.join("&");
            }
            return rtn;
        }
    </script>
    <?php
}

function ps_tag_image() {
    global $wpdb;
    /**
     * 	@since: 1.1
     *  Tag Image
     */
    ?>

    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
        <h2 class="ps-h ps-margin">Tag Image</h2>
        <a href="<?php echo admin_url('admin.php?page=ps_tag_image&do=add'); ?>" class="btn btn-success">Add image</a>
            <?php if (!empty($_REQUEST['do']) && $_REQUEST['do'] != 'delete') { ?><a class="btn btn-primary" href="<?php echo admin_url('admin.php?page=ps_tag_image'); ?>">Show images list</a><?php } ?>
        <?php
        $show_table = true;
        $errors = array();
        $success = array();
        // ACTIONS
        if (!empty($_REQUEST['do'])) {
            $table_name = $wpdb->prefix . 'ps_tag_images';
            switch ($_REQUEST['do']) {
                case 'delete':
                    if (!empty($_REQUEST['tag_id'])) {
                        $do_query = $wpdb->delete($table_name, array('ID' => $_REQUEST['tag_id']), array('%d'));
                        if ($do_query) {
                            $success[] = 'Image deleted.';
                        } else {
                            $errors[] = 'Image delete error.';
                        }
                    }
                    break;
                case 'edit':
                    if (!empty($_REQUEST['tag_id']) && !empty($_POST)) {
                        $_POST = array_map('trim', $_POST);

                        if ($_POST['title'] == "") {
                            $errors[] = "<strong>Title</strong> required.";
                        }
                        if ($_POST['image'] == "") {
                            $errors[] = "<strong>Image</strong> required.";
                        }
                        if (empty($errors)) {
                            $wpdb->update(
                                    $table_name, array(
                                'title' => $_POST['title'],
                                'image' => $_POST['image'],
                                'tags' => $_POST['tags'],
                                    ), array('ID' => $_POST['ID']), array(
                                '%s',
                                '%s',
                                '%s'
                                    ), array(
                                '%d'
                                    )
                            );
                            $success[] = 'Tag Image saved.';
                        }
                    }
                    $show_table = false;
                    break;
                case 'add':
                    if (!empty($_POST)) {
                        $_POST = array_map('trim', $_POST);

                        if ($_POST['title'] == "") {
                            $errors[] = "<strong>Title</strong> required.";
                        }
                        if ($_POST['image'] == "") {
                            $errors[] = "<strong>Image</strong> required.";
                        }
                        if (empty($errors)) {
                            $wpdb->insert(
                                    $table_name, array(
                                'title' => $_POST['title'],
                                'image' => $_POST['image'],
                                'tags' => $_POST['tags'],
                                'insert_date' => date('Y-m-d H:i:s')
                                    ), array(
                                '%s',
                                '%s',
                                '%s',
                                '%s'
                                    )
                            );
                            $success[] = 'Tag Image saved.';
                        }
                    }
                    $show_table = false;
                    break;
            }
        }
        if (!empty($success) && is_array($success)) {
            ?>
            <div id="message" class="updated fade">
                <?php foreach ($success as $msg) { ?>
                    <p><?php echo $msg; ?></p>
                <?php } ?>
            </div>
            <?php
        }
        if (!empty($errors) && is_array($errors)) {
            ?>
            <div id="message" class="error fade">
                <?php foreach ($errors as $msg) { ?>
                    <p><?php echo $msg; ?></p>
                <?php } ?>
            </div>
            <?php
        }
        // VIEWS
        if (!empty($_REQUEST['do'])) {
            switch ($_REQUEST['do']) {
                case 'edit':
                    if (!empty($_REQUEST['tag_id'])) {
                        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ID = %d", $_REQUEST['tag_id']), ARRAY_A);
                        ?>
                        <form method="post">
                            <table class="form-table">
                                <tbody>
                                    <tr valign="top">
                                        <th scope="row"><label for="title">Title</label></th>
                                        <td>
                                            <input class="large-text ps-input" type="text" name="title" value="<?php echo!empty($_POST['title']) ? esc_attr($_POST['title']) : esc_attr($row['title']); ?>" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="image">Image</label></th>
                                        <td>
                                            <input id="upload-img" type="button" class="button" value="Upload"/> <input id="uploaded-img-clear" type="button" class="button" value="Delete tags"/>
                                            <br /><br />
                                            <img id="uploaded-img" width="700px" style="display:none;margin-top:20px;" />
                                            <input type="hidden" name="image" id="uploaded-img-src" value="<?php echo!empty($_POST['image']) ? esc_attr($_POST['image']) : esc_attr($row['image']); ?>" />
                                            <input type="hidden" name="tags" id="uploaded-img-tags" value="<?php echo!empty($_POST['tags']) ? esc_attr(stripslashes($_POST['tags'])) : esc_attr(stripslashes($row['tags'])); ?>" />
                                        </td>
                                    </tr>                                            
                                    <tr valign="top">
                                        <th scope="row">
                                            <input type="hidden" value="<?php echo esc_attr($row['ID']); ?>" name="ID">
                                            <?php submit_button(); ?>
                                        </th>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                        <?php
                    }
                    break;
                case 'add':
                    ?>
                    <form method="post">
                        <table class="form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><label for="title">Title</label></th>
                                    <td>
                                        <input class="large-text" type="text" name="title" class="ps-input" value="<?php echo!empty($_POST['title']) ? esc_attr($_POST['title']) : ''; ?>" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="image">Image</label></th>
                                    <td>
                                        <input id="upload-img" type="button" class="button" value="Upload"/> <input id="uploaded-img-clear" type="button" class="button" value="Delete tags"/>
                                        <br /><br />
                                        <img id="uploaded-img" width="700px" style="display:none;margin-top:20px;" />
                                        <input type="hidden" name="image" id="uploaded-img-src" value="<?php echo!empty($_POST['image']) ? esc_attr($_POST['image']) : ''; ?>" />
                                        <input type="hidden" name="tags" id="uploaded-img-tags" value="<?php echo!empty($_POST['tags']) ? esc_attr(stripslashes($_POST['tags'])) : ''; ?>" />
                                    </td>
                                </tr>                                
                                <tr valign="top">
                                    <th scope="row"><?php submit_button(); ?></th>
                                    <td></td>
                                </tr>                                     
                            </tbody>
                        </table>
                    </form>

                    <?php
                    break;
            }
        }
        /**
         * Show table only when needed
         */
        if (!empty($show_table)) {
            $keywords = new Tag_Images_List();
            $keywords->prepare_items();
            $keywords->display();
        }
        ?>
    </div>
    <?php
}

function ps_tag_image_shortcode($atts) {
    global $wpdb;
    if (!is_singular(array('post', 'page'))) {
        return;
    }

    $atts = shortcode_atts(array(
        'id' => null,
        'width' => 700,
        'height' => '',
            ), $atts, 'tag_image');

    $atts['width'] = intval($atts['width']);

    $table_name = $wpdb->prefix . 'ps_tag_images';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ID = %d", $atts['id']), ARRAY_A);

    if (!empty($row)) {
        wp_enqueue_style('tag-image-taggd', plugins_url('css/taggd.css', __FILE__), array(), null);
        wp_enqueue_script('tag-image-taggd', plugins_url('js/jquery.taggd.js', __FILE__), array('jquery'), null, true);

        ob_start();
        ?>
        <div class="ps_tag_image" data-tags="<?php echo esc_attr(stripslashes($row['tags'])); ?>">
            <img width="<?php echo $atts['width']; ?>" height="<?php echo $atts['height']; ?>" src="<?php echo $row['image']; ?>" />
        </div>
        <?php
        $output = ob_get_clean();

        return $output;
    }
}

function ps_history_links() {
    if (isset($_GET['batch']) && in_array($_GET['batch'], array('posts', 'pages', 'comments'))) {
        ps_history_links_batch();
    } else {
        ps_history_links_list();
    }
}

function ps_history_links_list() {
    /**
     * 	@since: 1.0
     * 	Link history Page
     * 	Show latest generated links
     */
    $history_links = new History_Links();
    $history_links->prepare_items();
    ?>

    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <div class="wrap">
        <div class="ps-logo">
            <a href="<?php echo config('PS_HOME'); ?>">
                <img src="https://profitshare.ro/assets/img/logos/_logo-menu-profitshare.svg" alt="Profitshare">
            </a>
        </div>
        <h2 class="ps-h ps-margin">Link history</h2>
        <?php $history_links->display(); ?>
    </div>
    <?php
}

function ps_history_links_batch() {
    /**
     * 	@since: 1.4
     * 	Link history Batch Page
     * 	Generate short links in batches
     */
    global $wpdb;

    switch ($_GET['batch']) {
        case 'posts':
            $count_items = $wpdb->get_var($wpdb->prepare("SELECT count(ID) FROM {$wpdb->prefix}posts WHERE post_status = %s AND post_type = %s", array('publish', 'post')));
            break;
        case 'pages':
            $count_items = $wpdb->get_var($wpdb->prepare("SELECT count(ID) FROM {$wpdb->prefix}posts WHERE post_status = %s AND post_type = %s", array('publish', 'page')));
            break;
        case 'comments':
            $count_items = $wpdb->get_var("SELECT count(comment_ID) FROM {$wpdb->prefix}comments");
            break;
        default:
            $count_items = 0;
            break;
    }
    ?>
    <div class="wrap">
        <a href="<?php echo config('PS_HOME'); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
        <hr style="border-top: 1px dashed #7f8c8d;">
        <h2>Generate ProfitShare links for <span class="batch_type"><?php echo $_GET['batch']; ?></span></h2>
        <p>Please do not refresh the page until the process is done, otherwise the process will start from the begining.</p>
        <p>Total items to process: <span class="batch_count"><?php echo $count_items; ?></span></p>
        <p>Processed items: <span class="batch_processed">0</span></p>

        <div id="progressbar"><div class="plabel">Loading...</div></div>
        <div id="processed_items"></div>
    </div>
    <?php
}

function ps_useful_info() {
    /**
     * 	@since: 1.0
     * 	Help page
     * 	Contains F.A.Q.
     */
    ?>
    <div class="wrap">
        <a href="<?php echo config('PS_HOME'); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>

        <hr style="border-top: 1px dashed #7f8c8d;">
        <h2>Frequently Asked Questions</h2><br />
        <strong>What is Profitshare?</strong>
        <ul>
            <li>Profitshare is an affiliate marketing network, that is, a performance driven marketing tool.</li>
        </ul>
        <strong>What is an advertiser?</strong>
        <ul>
            <li>The advertiser is the one that wants to have its products or services advertised on the internet in order to obtain clients and increase its revenue.</li>
        </ul>
        <strong>What is an affiliate?</strong>
        <ul>
            <li>The affiliate is the one that advertises products and services offered by advertisers, through agreed methods, and earns a percentage of each sale that is made as a result of this. </li>
        </ul>
        <strong>Who is the client?</strong>
        <ul>
            <li>The client is the one that reaches advertisers' websites thanks to affiliate promotion. This client may then perform a pre-established action on the advertiser's website like: making a purchase, subscribing to a newsletter, signing up for an account etc.</li>
            <li>As <strong>an affiliate</strong> you gain access to a wide range of brands and varied products and services that can satisfy any kind of advertising projects, using efficient affiliate marketing tools. As <strong>an advertiser</strong>, the biggest advantage is acquiring an extensive community of ambassadors for your products or services from the affiliate marketing network</li>
        </ul>
        <strong>What is Profitshare for affiliates?</strong>
        <ul>
            <li>The <strong>Profitshare affiliate plugin</strong> is a tool that helps you grow your conversion rate and gain money online. Profitshare for affiliates lets you see your conversion history, facilitates generating affiliate links automatically or manually whenever you publish a new post. You can also watch your current earnings directly from your WordPress interface and you can replace al your existing links with affiliate links so that you can earn money with all your previous posts.</li>
        </ul>
    </div>
    <?php
}
?>