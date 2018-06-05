<?php
/** 	Functions
 * 	@ package: wp-profitshare
 * 	@ since: 1.0
 */
$ps_api_config = array(
    'RO' => array('NAME' => 'Romania',
        'API_URL' => 'http://api.profitshare.ro',
        'PS_HOME' => 'http://profitshare.ro',
        'CURRENCY' => 'RON',
    ),
    'BG' => array('NAME' => 'Bulgaria',
        'API_URL' => 'http://api.profitshare.bg',
        'PS_HOME' => 'http://profitshare.bg',
        'CURRENCY' => 'LEV',
    )
);

function config($param) {
    /**
     * 	@since: 1.2
     * 	Get values of config matrix from the user country
     */
    global $ps_api_config;
    $current_user = wp_get_current_user();
    $country = get_user_meta($current_user->ID, 'ps_api_country', true);
    return $ps_api_config[$country][$param];
}

function ps_init_settings() {
    /**
     * 	@since: 1.0
     * 	Creating tables
     */
    global $wpdb;

    $queries = array();
    $wpdb->query("DROP TABLE " . $wpdb->prefix . "ps_conversions");

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_advertisers (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
		`advertiser_id` mediumint(5) unsigned NOT NULL,
		`name` char(250) NOT NULL,
		`link` char(50) NOT NULL,
		UNIQUE KEY (`advertiser_id`),
		UNIQUE KEY (`name`),
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_conversions (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
        `order_number` varchar(32) NOT NULL,
		`order_date` char(20) NOT NULL,
		`items_commision` char(255) NOT NULL,
		`order_status` char(8) NOT NULL,
		`advertiser_id` mediumint(5) unsigned NOT NULL,
        `order_last_update` DATETIME NOT NULL,
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_keywords (
                `ID` mediumint(9) NOT NULL auto_increment,
                `keyword` varchar(255) NOT NULL default '',
                `title` varchar(255) NOT NULL default '',
                `link` varchar(255) NOT NULL default '',
                `openin` varchar(55) NOT NULL default '',
                `tip_display` enum('y','n') DEFAULT NULL,
                `tip_style` varchar(55) default NULL,
                `tip_description` text,
                `tip_title` varchar(255) default NULL,
                `tip_image` varchar(255) default NULL,
                PRIMARY KEY (`ID`)
		)CHARSET=utf8;";

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_shorted_links (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
		`source` char(100) NOT NULL,
		`link` text NOT NULL,
		`shorted` char(50) NOT NULL,
		`date` int(10) NOT NULL,
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_tag_images (
                `ID` mediumint(9) NOT NULL AUTO_INCREMENT,
                `title` varchar(150) NOT NULL,
                `image` varchar(255) NOT NULL,
                `tags` longtext NOT NULL,
                `insert_date` datetime NOT NULL,
                PRIMARY KEY (`ID`)
        )CHARSET=utf8;";

    $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_campaigns (
                `ID` mediumint(9) NOT NULL AUTO_INCREMENT,
                `campaign_id` mediumint(9) NOT NULL,
                `advertiser_id` mediumint(9) NOT NULL,
                `name` varchar(150) NOT NULL,
                `url` varchar(255) NOT NULL,
                `ps_url` varchar(255) NOT NULL,
                `banners` longtext NOT NULL,
                `status` enum('enabled', 'disabled') default 'enabled',
                `start_date` DATETIME,
                `end_date` DATETIME,
                `created_at` DATETIME,
                `updated_at` DATETIME,
                PRIMARY KEY (`ID`)
        )CHARSET=utf8;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    foreach ($queries as $query) {
        dbDelta($query);
    }

    // update db
    if(get_user_meta($current_user->ID, 'ps_is_api_connected', true)) {
        ps_update_advertisers_db(true);
        ps_update_conversions(true);
        ps_update_campaigns(true);
    }

    // Set PS Version
    update_option('ps_installed_version', PS_VERSION);
}

function ps_remove_settings() {
    /**
     * 	@since: 1.0
     * 	Cleaning DB after uninstall
     * 	Table *_ps_shorted_links remains for future installs
     * 	Table *_ps_keywords remains for future installs
     */
    global $wpdb;
    $current_user = wp_get_current_user();
    delete_user_meta($current_user->ID, 'ps_api_user');
    delete_user_meta($current_user->ID, 'ps_api_key');
    delete_user_meta($current_user->ID, 'ps_is_api_connected');
    delete_option('ps_last_advertisers_update');
    delete_option('ps_last_conversions_update');
    delete_option('ps_account_balance');
    delete_option('ps_last_check_account_balance');
    delete_option('auto_convert_posts');
    delete_option('auto_convert_pages');
    delete_option('auto_convert_comments');
    $wpdb->query("DROP TABLE " . $wpdb->prefix . "ps_advertisers");
    $wpdb->query("DROP TABLE " . $wpdb->prefix . "ps_conversions");
}

function ps_connection_check($api_user, $api_key, $api_country) {
    /**
     * 	@since: 1.0
     * 	Check API connexion through cURL
     * 	@param:		(string)	$api_user
     * 				(string)	$api_key
     * 				(string)	$api_country
     * 	@return:	(bool)
     */
    $current_user = wp_get_current_user();
    update_user_meta($current_user->ID, 'ps_api_user', $api_user);
    update_user_meta($current_user->ID, 'ps_api_key', $api_key);
    update_user_meta($current_user->ID, 'ps_api_country', $api_country);
    $json = ps_api_connect('affiliate-campaigns', 'GET', array(), 'page=1');
    if (false !== $json) {
        update_user_meta($current_user->ID, 'ps_is_api_connected', 1);
        ps_update_advertisers_db();
        return true;
    } else {
        delete_user_meta($current_user->ID, 'ps_api_user');
        delete_user_meta($current_user->ID, 'ps_api_key');
        delete_user_meta($current_user->ID, 'ps_api_country');
        return false;
    }
}

function ps_account_balance() {
    /**
     * 	@since: 1.0
     * 	Get current ballance
     * 	Update every 60 minutes
     */
    if (get_option('ps_last_check_account_balance') + 60 * 60 < time()) {
        $json = ps_api_connect('affiliate-info', 'GET', array());
        $total = number_format($json['result']['current_affiliate_earnings'], 2);
        update_option('ps_account_balance', $total);
        update_option('ps_last_check_account_balance', time() + 60 * 60);
        return $total;
    } else {
        global $ps_api_config;
        $current_user = wp_get_current_user();
        $country = get_user_meta($current_user->ID, 'ps_api_country', true);
        return get_option('ps_account_balance') . ' ' . config('CURRENCY');
    }
}

function ps_update_campaigns($force_update = false) {
    global $wpdb;

    $current_user = wp_get_current_user();

    if ((get_option('ps_last_campaigns_update') + 60 * 60 * 3 < time() && get_user_meta($current_user->ID, 'ps_is_api_connected', true)) || $force_update) {
        update_option('ps_last_campaigns_update', time() + 60 * 60 * 3);

        $json = ps_api_connect('affiliate-campaigns', 'GET', array(), 'page=1');
        $active_campaigns = [];

        if(!isset($json['result']) || !isset($json['result']['paginator']['totalPages'])) {
            return [];
        }

        $total_pages = $json['result']['paginator']['totalPages'];
        $current_page = 1;

        while($current_page < $total_pages) {
            $json = ps_api_connect('affiliate-campaigns', 'GET', array(), 'page='.$current_page);

            if(!isset($json['result']['campaigns']) || !$json['result']['campaigns']) {
                break;
            }

            foreach($json['result']['campaigns'] as $campaign) {
                $active_campaigns[] = $campaign;
            }

            $current_page++;
        }

        $current_campaigns = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ps_campaigns WHERE status='enabled'", ARRAY_A);
        $campaigns_list = array();
        $ps_campaign_list = array();

        foreach($current_campaigns as $campaign) {
            $campaigns_list[$campaign['campaign_id']] = $campaign;
        }

        foreach($active_campaigns as $campaign) {
            $ps_campaign_list[$campaign['id']] = $campaign;
        }

        // disable inactive campaigns
        foreach($campaigns_list as $key => $campaign) {
            if(!array_key_exists($key, $ps_campaign_list)) {
                $wpdb->update($wpdb->prefix . "ps_campaigns", array('status' => 'disabled'), array('ID' => $campaign['ID'])); 
            }
        }
        
        if (!empty($active_campaigns)) {
            foreach($ps_campaign_list as $key => $campaign) {
                if(array_key_exists($key, $campaigns_list)) {
                    continue;
                }

                $insert_data = array(
                    'campaign_id' => $campaign['id'],
                    'advertiser_id' => $campaign['advertiser_id'],
                    'name' => $campaign['name'],
                    'url'  => $campaign['url'],
                    'ps_url'  => '',
                    'name' => $campaign['name'],
                    'banners' => json_encode($campaign['banners']),
                    'start_date' => $campaign['startDate'],
                    'end_date' => $campaign['endDate'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                );

               $wpdb->insert($wpdb->prefix . "ps_campaigns", $insert_data); 
            }
        }
    }
}

function ps_update_conversions($force_update = false) {
    date_default_timezone_set('Europe/Bucharest');
    /**
     * 	@since: 1.0
     * 	Get PS conversions and chaching into DB
     * 	Update every 6 hours
     */
    global $wpdb;

    $current_user = wp_get_current_user();
    if ((get_option('ps_last_conversions_update') + 60 * 60 * 6 < time() && get_user_meta($current_user->ID, 'ps_is_api_connected', true)) || $force_update) {
        update_option('ps_last_conversions_update', time());
        $json = ps_api_connect('affiliate-commissions', 'GET', array(), 'page=1');
        $data = $json['result']['commissions'];

        $max_pages = 4;
        $total_pages = $json['result']['total_pages'];

        if($total_pages < $max_pages) {
            $max_pages = $total_pages;
        }

        $conversions = array();

        $current_page = 1;

        while($current_page <= $max_pages) {
            $json = ps_api_connect('affiliate-commissions', 'GET', array(), 'page='.$current_page);
            if(!isset($json['result']['commissions']) || !$json['result']['commissions']) {
                break;
            }

            foreach($json['result']['commissions'] as $conversion) {
                $conversions[] = $conversion;
            }

            $current_page++;
        }

        $wpdb->query("TRUNCATE " . $wpdb->prefix . "ps_conversions"); // Cleaning table 
        if (!empty($conversions)) 
            foreach($conversions as $conversion) {
                $insert_data = array(
                    'order_date' => $conversion['order_date'],
                    'order_number' => $conversion['order_number'],
                    'items_commision' => $conversion['items_commision'],
                    'order_status' => $conversion['order_status'],
                    'advertiser_id' => $conversion['advertiser_id'],
                    'order_last_update' => $conversion['order_updated']
                );
                //$format_data = array('%s', '%s', '%s', '%u');
                $wpdb->insert($wpdb->prefix . "ps_conversions", $insert_data); // Se introduc noile valori ale conversiilor
            }
    }
}

function ps_update_advertisers_db($force_update = false) {
    date_default_timezone_set('Europe/Bucharest');
    /**
     * 	@since: 1.0
     * 	Get advertisers list and caching
     * 	Update every 24 hours
     */
    global $wpdb;
    $current_user = wp_get_current_user();

    if ((get_option('ps_last_advertisers_update') + 60 * 60 * 24 < time() || true  && get_user_meta($current_user->ID, 'ps_is_api_connected', true)) || $force_update) {
        update_option('ps_last_advertisers_update', time());
        $json = ps_api_connect('affiliate-advertisers', 'GET');

        foreach ($json['result'] as $res) {
            $adv_id = (int) $res['id'];

            $check = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . $adv_id . "'", OBJECT);
            if (!$check) {

                $insert_data = array(
                    'advertiser_id' => $res['id'],
                    'name' => $res['name'],
                    'link' => ps_clear_url($res['url'])
                );
                $try = $wpdb->insert($wpdb->prefix . "ps_advertisers", $insert_data);
            }
        }
    }
}

function ps_replace_links($where = 'posts') {
    /**
     *     @since: 1.0
     *     Replace all advertisers links from blog
     *     Returns (with functions: replace_links_post() and shorten_link()) how many links have been replaced
     *     @return:    (int)    $shorted_links
     */
    global $wpdb;
    if ($where == 'posts') {
        $item_ids = $wpdb->get_results("SELECT ID FROM " . $wpdb->prefix . "posts WHERE `post_status`='publish' AND `post_type`='post' ORDER BY ID DESC", OBJECT);
    } elseif ($where == 'pages') {
        $item_ids = $wpdb->get_results("SELECT ID FROM " . $wpdb->prefix . "posts WHERE `post_status`='publish' AND `post_type`='page' ORDER BY ID DESC", OBJECT);
    } else {
        $item_ids = $wpdb->get_results("SELECT comment_ID FROM " . $wpdb->prefix . "comments ORDER BY comment_ID DESC", OBJECT);
    }

    $shorted_links = 0;
    foreach ($item_ids as $item)
        if (in_array($where, array('posts', 'pages'))) {
            if (false !== ($total = ps_replace_links_post($item->ID) ))
                $shorted_links += $total;
        } else {
            if (false !== ($total = ps_replace_links_comment($item->comment_ID) ))
                $shorted_links += $total;
        }
    return $shorted_links;
}

function ps_replace_links_batch() {
    global $wpdb;

    try {
        if (empty($_POST['type'])) {
            throw new Exception('Batch type cannot be empty!');
        }

        $last_item = isset($_POST['last']) ? intval($_POST['last']) : 0;
        $limit = 5;

        switch ($_POST['type']) {
            case 'posts':
                $items = $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE ID > %d AND post_status = %s AND post_type = %s ORDER BY ID ASC LIMIT %d", array($last_item, 'publish', 'post', $limit)), OBJECT);
                break;
            case 'pages':
                $items = $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE ID > %d AND post_status = %s AND post_type = %s ORDER BY ID ASC LIMIT %d", array($last_item, 'publish', 'page', $limit)), OBJECT);
                break;
            case 'comments':
                $items = $wpdb->get_results($wpdb->prepare("SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_ID > %d ORDER BY comment_ID ASC LIMIT %d", array($last_item, $limit)), OBJECT);
                break;
            default:
                $items = array();
                break;
        }

        $items_processed = array();
        foreach ($items as $item) {
            if (in_array($_POST['type'], array('posts', 'pages'))) {
                ps_replace_links_post($item->ID);
                $items_processed[] = $item->ID;
                $last_item = $item->ID;
            } else {
                ps_replace_links_comment($item->comment_ID);
                $items_processed[] = $item->comment_ID;
                $last_item = $item->comment_ID;
            }
        }

        wp_send_json_success(array('processed' => $items_processed, 'last' => $last_item));
    } catch (Exception $ex) {
        wp_send_json_error(array('message' => $ex->getMessage()));
    }
}

function ps_get_limit_shorten_links($postid) {
    $limit_shorten_links = get_post_meta($postid, '_ps_limit_shorten_links', true);

    if (empty($limit_shorten_links)) {
        $limit_shorten_links = -1;
    }

    return $limit_shorten_links;
}

function ps_get_limit_keyword_links($postid) {
    $limit_keyword_links = get_post_meta($postid, '_ps_limit_keyword_links', true);

    if (empty($limit_keyword_links)) {
        $limit_keyword_links = -1;
    }

    return $limit_keyword_links;
}

function ps_replace_links_post($postid) {
    /**
     * 	@since: 1.0
     * 	Replace all links from a post and returns how many links have been replaced
     * 	@param:		(Object)	$post
     * 	@return:	(int)		$total_links
     */
    global $wpdb;
    $post = get_post($postid);
    $content = $post->post_content;
    $title = $post->post_title;
    $limit_shorten_links = ps_get_limit_shorten_links($postid);
    $total_links = 0;

    if (!empty($content) && !empty($title)) {
        $links = ps_get_html_links($content);
        $count_links = count($links);

        if ($limit_shorten_links == 'none') {
            $count_links = 0;
        } elseif ($limit_shorten_links > 0) {
            $count_links = $limit_shorten_links > $count_links ? $count_links : $limit_shorten_links;
        }

        for ($i = 0; $i < $count_links; $i++) {
            $shorten_link = ps_shorten_link($title, $links[$i]['url']);
            if ($shorten_link['shorten']) {
                $total_links++;
            }
        }
    }

    return $total_links;
}

function ps_auto_convert_posts($postid) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return false;
    if (!current_user_can('edit_page', $postid))
        return false;
    if (empty($postid) || !in_array(get_post_type(), array('post', 'page')))
        return false;

    if (isset($_POST['ps_limit_shorten_links'])) {
        $limit_shorten_links = sanitize_text_field($_POST['ps_limit_shorten_links']) ? sanitize_text_field($_POST['ps_limit_shorten_links']) : -1;
        update_post_meta($postid, '_ps_limit_shorten_links', $limit_shorten_links);
    }

    if (isset($_POST['ps_limit_keyword_links'])) {
        $limit_keywords_links = sanitize_text_field($_POST['ps_limit_keyword_links']);
        update_post_meta($postid, '_ps_limit_keyword_links', $limit_keywords_links);
    }

    if (get_post_type() == 'post') {
        $opt_name = 'auto_convert_posts';
    } else {
        $opt_name = 'auto_convert_pages';
    }

    get_option($opt_name) ? ps_replace_links_post($postid) : 0;
}

function ps_replace_links_comment($comment_id) {
    /**
     * 	@since: 1.1
     * 	Replace all comments links from a post and returns how many links have been replaced
     * 	@param:		(Object)	$post
     * 	@return:	(int)		$total_links
     */
    global $wpdb;
    $content = get_comment_text($comment_id);
    $title = 'Comment #' . $comment_id;
    $total_links = 0;
    if (!empty($content) && !empty($title)) {
        $links = ps_get_html_links(make_clickable($content));
        $count_links = count($links);
        for ($i = 0; $i < $count_links; $i++) {
            $shorten_link = ps_shorten_link($title, $links[$i]['url']);
            if ($shorten_link['shorten'])
                $total_links++;
        }
    }
    return $total_links;
}

function ps_auto_convert_comments($comment_id) {
    get_option('auto_convert_comments') ? ps_replace_links_comment($comment_id) : 0;
}

function ps_get_html_links($content) {
    /**
     * 	@since: 1.1
     * 	Extracting all links from post content and placing them into a vector
     * 	Function returns the vector with links
     * 	The function have been upgraded starting with version 1.1 by using DOMDocument() class
     * 	@param:		(string)	$text;
     * 	@return:	(array)		$links
     */
    global $wpdb;
    $DOMDoc = new DOMDocument();
    @$DOMDoc->loadHTML($content);
    $links = array();
    foreach ($DOMDoc->getElementsByTagName('a') as $link) {
        $check_advertiser = $wpdb->get_results("SELECT COUNT(*) as count FROM " . $wpdb->prefix . "ps_advertisers WHERE link='" . ps_clear_url($link->getAttribute('href')) . "'", OBJECT);
        if ($check_advertiser[0]->count || strpos($link->getAttribute('href'), 'm.'))
            $links[] = array(
                'url' => $link->getAttribute('href'),
                'text' => $link->nodeValue
            );
    }
    return $links;
}

function ps_shorten_link($source, $link) {
    /**
     * 	@since: 1.0
     * 	Dacă link-ul primit prin parametrul $link, nu a fost scurtat, îl scurtează
     * 	Dacă link-ul primit prin parametrul $link a fost deja scurtat, funcția returnează link-ul scurtat respectiv
     * 	Funcția returnează un vector:
     * 		shorted	=>	link-ul scurtat
     * 		shorten	=>	1 dacă s-a scurtat link-ul primit prin parametrul $link, 0 altfel
     * 		result	=>	1 dacă acțiunea s-a efectuat cu succes, 0 altfel
     * 	Introduce informaţiile în baza de date în tabelul cu linkuri (acelaşi tabel ce menţine istoria linkurilor scurtate)
     * 	@param:		(string)	$source
     * 				(string)	$link
     * 	@return:	(array)		$result
     */
    global $wpdb;
    $result = array(
        'shorted' => '',
        'shorten' => 0,
        'result' => 0
    );
    $check_link = $wpdb->get_results("SELECT shorted FROM " . $wpdb->prefix . "ps_shorted_links WHERE link='" . $link . "'", OBJECT);

    if (empty($link) || strpos($link, config('PS_HOME') . '/l/')) {
        $result['result'] = 0;
    } else if (!empty($check_link[0]->shorted)) {
        $result['shorted'] = $check_link[0]->shorted;
        $result['result'] = 1;
    } else {
        $json = ps_api_connect('affiliate-links', 'POST', array(
            'name' => $source,
            'url' => $link
                )
        );

        if (!empty($json) && isset($json['result'][0]['ps_url'])) {
            $shorted = $json['result'][0]['ps_url'];
            if (isset($shorted)) {
                $shorted = ps_remove_link_protocol($shorted);
                $insert_data = array(
                    'source' => $source,
                    'link' => $link,
                    'shorted' => $shorted,
                    'date' => time()
                );

                $return = $wpdb->insert($wpdb->prefix . "ps_shorted_links", $insert_data);
                $result['shorted'] = $shorted;
                $result['shorten'] = $result['shorten'] + 1;
                $result['result'] = $return;
            }
        }
    }
    return $result;
}

function ps_remove_link_protocol($link) {
    // remove https protocol
    $link = str_replace('https://', '//', $link);

    // remove http protocol
    $link = str_replace('http://', '//', $link);

    return $link;
}

function ps_clear_url($url) {
    /**
     * 	@since: 1.0
     * 	Cleaning URL trough function parameters C
     * 	utility: Secure comparing for links between the post content and advertisers links.
     * 	@param:		(string)	$url
     * 	@return:	(string)	$url
     */
    $url = parse_url($url);
    $url = str_replace('www.', '', $url['host']);
    return $url;
}

function ps_filter_links($content) {
    /**
     * 	@since: 1.1
     * 	Changing long links from post content with the short ones. 
     * 	The function is referring to the filter for 'the_content' and 'comment_text'
     * 	@param:		(string)	$content
     * 	@return:	(string)	$content
     */
    global $wpdb, $post;
    $links = ps_get_html_links($content);
    $content = str_replace('&amp;', '&', $content);
    
    $count_links = count($links);

    // set limit
    $limit_shorten_links = ps_get_limit_shorten_links($post->ID);

    if ($limit_shorten_links == 'none') {
        $count_links = 0;
    } elseif ($limit_shorten_links > 0) {
        $count_links = $limit_shorten_links > $count_links ? $count_links : $limit_shorten_links;
    }

    // generate array
    $shorted_links = array();
    $shorted_links_no = 0;
    foreach ($links as $link) {
        if ($shorted_links_no == $count_links) {
            break;
        }

        $shorted = $wpdb->get_results($wpdb->prepare("SELECT shorted FROM {$wpdb->prefix}ps_shorted_links WHERE link = %s", $link['url']));

        if (isset($shorted[0]->shorted)) {
            $shorted_links[$link['url']] = $shorted[0]->shorted;
            #$shorted_links['#href=[\"\']' . $link['url'] . '[\"\']#'] = 'href="' . $shorted[0]->shorted . '"';
            $shorted_links_no++;
        }
    }

    // replace in content
    if (sizeof($shorted_links)) {
        foreach($shorted_links as $url => $ps_link) {
            $content = str_replace($url, $ps_link, $content);
        }
        #$content = preg_replace(array_keys($shorted_links), $shorted_links, $content);
    }

    return $content;
}

function ps_translate_month($month) {
    /**
     * 	@since: 1.0
     * 	Translate months of the year in english
     * 	The function returns english months
     * 	@param:		(int)		$month
     * 	@return:	(string)
     */
    switch ($month) {
        case 1: return 'January';
            break;
        case 2: return 'February';
            break;
        case 3: return 'March';
            break;
        case 4: return 'April';
            break;
        case 5: return 'May';
            break;
        case 6: return 'June';
            break;
        case 7: return 'July';
            break;
        case 8: return 'August';
            break;
        case 9: return 'September';
            break;
        case 10: return 'October';
            break;
        case 11: return 'November';
            break;
        default: return 'December';
    }
}

function ps_api_connect($path, $method = 'GET', $post_data = array(), $query_string = "") {
    /**
     * 	@since: 1.0
     * 	Connexion trough cURL for Profitshare API
     * 	@param:		(string)		$path
     * 				(string)		$method
     * 				(array)			$post_data
     * 				(string)		$query_string
     * 	@return:	(bool|string)	FALSE|$content
     */
    if (is_callable('curl_init')) {
        $current_user = wp_get_current_user();
        $ps_api_user = get_user_meta($current_user->ID, 'ps_api_user', true);
        $ps_api_key = get_user_meta($current_user->ID, 'ps_api_key', true);
        $ps_api_country = get_user_meta($current_user->ID, 'ps_api_country', true);
        global $ps_api_config;
        $api_url = $ps_api_config[$ps_api_country]['API_URL'];
        $curl_init = curl_init();

        curl_setopt($curl_init, CURLOPT_HEADER, false);
        curl_setopt($curl_init, CURLOPT_URL, $api_url . "/" . $path . "/?" . $query_string);
        curl_setopt($curl_init, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl_init, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_init, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_init, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($curl_init, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        if ('POST' == $method) {
            curl_setopt($curl_init, CURLOPT_POST, true);
            curl_setopt($curl_init, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        $auth_data = array(
            'api_user' => $ps_api_user,
            'api_key' => $ps_api_key
        );
        $date = gmdate('D, d M Y H:i:s T', time());
        $signature_string = $method . $path . '/?' . $query_string . '/' . $auth_data['api_user'] . $date;
        $auth = hash_hmac('sha1', $signature_string, $auth_data['api_key']);
        $extra_headers = array(
            "Date: {$date}",
            "X-PS-Client: {$auth_data['api_user']}",
            "X-PS-Accept: json",
            "X-PS-Auth: {$auth}"
        );
        curl_setopt($curl_init, CURLOPT_HTTPHEADER, $extra_headers);
        $content = curl_exec($curl_init);

        if (!curl_errno($curl_init)) {
            $info = curl_getinfo($curl_init);
            if ($info['http_code'] != 200) {
                curl_close($curl_init);
                return false;
            }
        } else {
            curl_close($curl_init);
            return false;
        }
        return json_decode($content, true);
    } else
        return false;
}

function ps_dashboard_widget() {
    /**
     * 	@since: 1.3
     * 	Add widget to dashboard
     * 	Add the widget with current earnings info
     */
    $is_api_connected = get_user_meta(wp_get_current_user()->ID, 'ps_is_api_connected', true);
    if ($is_api_connected)
        wp_add_dashboard_widget(
                'ps_dashboard_widget', 'WP Profitshare', 'ps_dashboard_widget_content'
        );
}

function ps_dashboard_widget_content() {
    /**
     * 	@since: 1.3
     * 	Widget content of WP Profitshare
     * 	Show the current earnings in the widget from dashboard
     */
    ?>
    <div class="main">
        <h3>Current earnings: <font style="color: #006D3E;"><?php echo ps_account_balance(); ?></font></h3>
    </div>
    <?php
}

function ps_limit_shorten_links() {
    /**
     * 	@since: 1.3
     * 	Post submit box misc action
     * 	
     */
    global $post;
    $shorten_links_select = ps_get_limit_shorten_links($post->ID);
    $keyword_links_select = ps_get_limit_keyword_links($post->ID);
    $list = array(
        '-1' => 'Unlimited',
        'none' => '0 Links', // because http://wordpress.stackexchange.com/questions/96510/update-post-meta-not-saving-when-value-is-zero
        '1' => '1 Link',
        '3' => '3 Links',
        '5' => '5 Links',
        '10' => '10 Links'
    );
    ?>
    <div class="misc-pub-section misc-pub-limit-shorten-links">
        <div class="wp-menu-image dashicons-before dashicons-chart-pie">
            <label for="ps_limit_shorten_links">
                <abbr title="Limit to short links in this article">Limit shorten links:</abbr>
            </label>
            <span id="limit-shorten-links-display" style="font-weight:600;color:#444;"><?php echo $list[$shorten_links_select]; ?></span>
            <a class="edit-limit-shorten-links hide-if-no-js" href="#shorten_links_select"><span aria-hidden="true">Edit</span></a>
            <div id="shorten_links_select_wrap" class="hide-if-js" style="display:none;">
                <select id="shorten_links_select" name="ps_limit_shorten_links_select" style="width:60%;">
    <?php foreach ($list as $k => $v) { ?>
                        <option value="<?php echo $k; ?>"<?php selected($k, $shorten_links_select); ?>><?php echo $v ?></option>
                    <?php } ?>
                </select>
                <input type="hidden" id="limit_shorten_links" name="ps_limit_shorten_links" value="<?php echo!empty($shorten_links_select) ? esc_attr($shorten_links_select) : ''; ?>" />
                <a class="save-shorten_links_select hide-if-no-js button" href="#shorten_links_select">OK</a>
                <a class="cancel-shorten_links_select hide-if-no-js button-cancel" href="#shorten_links_select">Cancel</a>
            </div>
        </div>
    </div>
    <div class="misc-pub-section misc-pub-limit-keyword-links">
        <div class="wp-menu-image dashicons-before dashicons-chart-pie">
            <label for="ps_limit_keyword_links">
                <abbr title="Limit to keyword links in this article">Limit keyword links:</abbr>
            </label>
            <span id="limit-keyword-links-display" style="font-weight:600;color:#444;"><?php echo $list[$keyword_links_select]; ?></span>
            <a class="edit-limit-keyword-links hide-if-no-js" href="#keywords_links_select"><span aria-hidden="true">Edit</span></a>
            <div id="keyword_links_select_wrap" class="hide-if-js" style="display:none;">
                <select id="keyword_links_select" name="ps_limit_keyword_links_select" style="width:60%;">
    <?php foreach ($list as $k => $v) { ?>
                        <option value="<?php echo $k; ?>"<?php selected($k, $keyword_links_select); ?>><?php echo $v ?></option>
                    <?php } ?>
                </select>
                <input type="hidden" id="limit_keyword_links" name="ps_limit_keyword_links" value="<?php echo!empty($keyword_links_select) ? esc_attr($keyword_links_select) : ''; ?>" />
                <a class="save-keyword_links_select hide-if-no-js button" href="#keyword_links_select">OK</a>
                <a class="cancel-keyword_links_select hide-if-no-js button-cancel" href="#keyword_links_select">Cancel</a>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            /**
             * Shorten limit
             */
            $('.edit-limit-shorten-links').on('click', function(e) {
                e.preventDefault();

                $('#shorten_links_select_wrap').show();
            });

            $('.save-shorten_links_select').on('click', function(e) {
                e.preventDefault();

                $('#limit_shorten_links').val($('#shorten_links_select').val());
                $('#limit-shorten-links-display').text($('#shorten_links_select option:selected').text());
                $('#shorten_links_select_wrap').hide();
            });

            $('.cancel-shorten_links_select').on('click', function(e) {
                e.preventDefault();

                $('#shorten_links_select_wrap').hide();
            });

            /**
             * Keyword limit
             */
            $('.edit-limit-keyword-links').on('click', function(e) {
                e.preventDefault();

                $('#keyword_links_select_wrap').show();
            });

            $('.save-keyword_links_select').on('click', function(e) {
                e.preventDefault();

                $('#limit_keyword_links').val($('#keyword_links_select').val());
                $('#limit-keyword-links-display').text($('#keyword_links_select option:selected').text());
                $('#keyword_links_select_wrap').hide();
            });

            $('.cancel-keyword_links_select').on('click', function(e) {
                e.preventDefault();

                $('#keyword_links_select_wrap').hide();
            });
        });
    </script>
    <?php
}
?>