<?php

// Don't load this file directly
if(!defined('ABSPATH')) { die(); }

class Better_Links_Tracking {
	const TYPE_LINK_CLICK = 'better-link-click';

	const LINK_CLICK_META_NAME = 'better-links-click-meta';

	const LINK_CLICK_META_COUNT = 'better-links-clicks-count-meta';
	const LINK_CLICK_META_IP_NAME = 'better-links-click-ip-meta';
	const LINK_CLICK_META_POST_NAME = 'better-links-click-post-meta';
	const LINK_CLICK_META_REFERER_NAME = 'better-links-click-referer-meta';


	public static function init() {
		self::_add_actions();
		self::_add_filters();
	}

	private static function _add_actions() {
		if(is_admin()) {
			add_action('better_links_add_link_meta_boxes', array(__CLASS__, 'add_link_meta_boxes'));
			add_action('better_links_process_tools_action_purge', array(__CLASS__, 'process_tools_actions_purge'));
			add_action('better_links_process_tools_action_reset_click_data', array(__CLASS__, 'process_tools_actions_reset_click_data'));
			add_action('better_links_tools_page_bottom', array(__CLASS__, 'display_tools_page'));

			add_action(sprintf('manage_%s_posts_custom_column', Better_Links::TYPE_LINK), array(__CLASS__, 'output_columns'), 10, 2);
		} else {
			add_action('wp_enqueue_scripts', array(__CLASS__, 'wp_enqueue_scripts'));
		}

		add_action('init', array(__CLASS__, 'register'));
		add_action('pre_get_posts', array(__CLASS__, 'pre_get_posts'));

		add_action('better_links_delete_link', array(__CLASS__, 'delete_link'));
		// add_action('better_links_import_pretty_link', array(__CLASS__, 'import_pretty_link'), 11, 2);
		add_action('better_links_save_link_finalize', array(__CLASS__, 'save_link_finalize'), 11, 4);
		add_action('better_links_redirecting', array(__CLASS__, 'track_redirect'));
	}

	private static function _add_filters() {
		if(is_admin()) {
			add_filter('better_links_process_tools_action_reset_click_data_redirect_link', array(__CLASS__, 'process_tools_actions_reset_click_data_redirect_link'), 10, 2);

			add_filter('post_row_actions', array(__CLASS__, 'post_row_actions'), 10, 2);
			add_filter(sprintf('manage_%s_posts_columns', Better_Links::TYPE_LINK), array(__CLASS__, 'add_columns'));
		} else {

		}

		add_filter('better_links_ajax_search_results', array(__CLASS__, 'ajax_search_results'));

		add_filter('better_links_pre_get_link_click_meta', array(__CLASS__, 'pre_get_link_click_meta'), 10, 2);
		add_filter('better_links_pre_set_link_click_meta', array(__CLASS__, 'pre_set_link_click_meta'), 10, 2);
	}

	// AJAX

	public static function ajax_search_results($links) {
		foreach($links as $link) {
			$link->clicks_total = number_format_i18n(self::_get_clicks_count($link->ID));
			$link->clicks_30 = number_format_i18n(self::_get_clicks_count($link->ID, 30));
		}

		return $links;
	}

	// Query modification

	public static function pre_get_posts($wp_query) {
		if('clicks' === $wp_query->get('orderby') && '' === $wp_query->get('meta_key')) {
			$wp_query->set('meta_compare', '>=');
			$wp_query->set('meta_key', self::LINK_CLICK_META_COUNT);
			$wp_query->set('meta_value', '0');
			$wp_query->set('orderby', 'meta_value_num');
		}
	}

	// Administrative interface

	public static function add_columns($columns) {
		$date = $columns['date'];

		unset($columns['date']);

		$columns['clicks-total'] = __('Clicks (total)');
		$columns['clicks-month'] = __('Clicks (30 days)');

		$columns['date'] = $date;

		return $columns;
	}

	public static function display_tools_page($vars) {
		extract($vars);

		include('views/backend/pages/tools.php');
	}

	public static function output_columns($column, $post_id) {
		switch($column) {
			case 'clicks-total':
				echo number_format_i18n(self::_get_clicks_count($post_id));
				break;
			case 'clicks-month':
				echo number_format_i18n(self::_get_clicks_count($post_id, 30));
				break;
		}
	}

	public static function post_row_actions($actions, $post) {
		if(Better_Links::TYPE_LINK === $post->post_type) {
			if('link-active' === $post->post_status) {
				$actions['better-links-reset-clicks'] = sprintf('<a href="%1$s">%2$s</a>', self::_get_reset_click_data_link($post->ID), __('Reset click data'));
			}
		}

		return $actions;
	}

	// Frontend interface

	public static function wp_enqueue_scripts() {

	}

	// Meta boxes

	public static function add_link_meta_boxes($post) {
		$screen = get_current_screen();

		// Sidebar
		add_meta_box('bl-quick-stats', __('Quick Stats'), array(__CLASS__, 'display_link_meta_box_quick_stats'), $screen, 'side', 'high');
	}

	public static function display_link_meta_box_quick_stats($post) {
		$clicks_count = self::_get_clicks_count($post->ID);
		$clicks_count_30 = self::_get_clicks_count($post->ID, 30);
		$clicks_data = self::_get_clicks_data($post->ID, array('posts_per_page' => 1));
		$reset_link = self::_get_reset_click_data_link($post->ID);

		$template = apply_filters('better_links_meta_box_actions_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/quick-stats.php'));
		$vars = apply_filters('better_links_meta_box_actions_vars', compact('clicks_count', 'clicks_data', 'post', 'reset_link'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	// Click tracking

	public static function pre_get_link_click_meta($link_click_meta, $link_click_id) {
		$link_click_meta = is_array($link_click_meta) ? $link_click_meta : array();

		return shortcode_atts(self::_get_link_click_meta_defaults($link_click_id), $link_click_meta);
	}

	public static function pre_set_link_click_meta($link_click_meta, $link_click_id) {
		$link_click_meta = is_array($link_click_meta) ? $link_click_meta : array();

		if(isset($link_click_meta['ip'])) {
			update_post_meta($link_click_id, self::LINK_CLICK_META_IP_NAME, $link_click_meta['ip']);
		}

		if(isset($link_click_meta['post'])) {
			update_post_meta($link_click_id, self::LINK_CLICK_META_POST_NAME, $link_click_meta['post']);
		}

		if(isset($link_click_meta['referer'])) {
			update_post_meta($link_click_id, self::LINK_CLICK_META_REFERER_NAME, $link_click_meta['referer']);
		}

		return shortcode_atts(self::_get_link_click_meta_defaults($link_click_id), $link_click_meta);
	}

	public static function track_redirect($link) {
		$data = stripslashes_deep($_REQUEST);
		$post = isset($data['bl-pid']) ? $data['bl-pid'] : 0;

		$link_click_id = wp_insert_post(array(
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_parent' => $link->ID,
			'post_status' => 'link-click-tracked',
			'post_type' => self::TYPE_LINK_CLICK,
		));

		if(!is_wp_error($link_click_id)) {
			$meta = array(
				'browser' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
				'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
				'post' => $post,
				'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
			);

			self::_increment_link_click_count($link->ID);
			self::_set_link_click_meta($link_click_id, $meta);
		}
	}

	private static function _increment_link_click_count($link_id) {
		self::_set_link_click_count($link_id, self::_get_link_click_count($link_id) + 1);
	}

	private static function _get_link_click_count($link_id) {
		return intval(get_post_meta($link_id, self::LINK_CLICK_META_COUNT, true));
	}

	private static function _set_link_click_count($link_id, $count) {
		update_post_meta($link_id, self::LINK_CLICK_META_COUNT, $count);
	}

	private static function _get_link_click_meta($link_click_id) {
		$link_click_id = is_null($link_click_id) && in_the_loop() ? get_the_ID() : $link_click_id;

		$link_click_meta = false;
		if(!empty($link_click_id)) {
			$link_click_meta = apply_filters('better_links_pre_get_link_click_meta', get_post_meta($link_click_id, self::LINK_CLICK_META_NAME, true), $link_click_id);

			wp_cache_set(self::LINK_CLICK_META_NAME, $link_click_meta, $link_click_id, BETTER_LINKS_CACHE_PERIOD);
		}

		return $link_click_meta;
	}

	private static function _get_link_click_meta_defaults($link_click_id) {
		return apply_filters('better_links_pre_get_link_click_meta_defaults', array(
			'browser' => false,
			'ip' => false,
			'post' => 0,
			'referer' => false,
		));
	}

	private static function _set_link_click_meta($link_click_id, $link_click_meta) {
		$link_click_meta = apply_filters('better_links_pre_set_link_click_meta', $link_click_meta, $link_click_id);

		update_post_meta($link_click_id, self::LINK_CLICK_META_NAME, $link_click_meta);

		wp_cache_delete(self::LINK_CLICK_META_NAME, $link_click_id);

		return $link_click_meta;
	}

	private static function _get_reset_click_data_link($link_id) {
		return wp_nonce_url(Better_Links::_get_tools_page_link(array('better-links-action' => 'reset_click_data', 'id' => $link_id)), 'better-links-action-reset_click_data', 'better-links-action-reset_click_data-nonce');
	}

	// Link delete and save

	public static function delete_link($link_id) {
		$clicks_ids = self::_get_clicks_data($link_id, array(
			'fields' => 'ids',
			'nopaging' => true,
		));

		foreach($clicks_ids as $clicks_id) {
			wp_delete_post($clicks_id);
		}
	}

	public static function save_link_finalize($post_id, $post, $link_meta, $link_meta_errors) {
		self::_set_link_click_count($post_id, self::_get_link_click_count($post_id));
	}

	// Registration

	public static function register() {
		self::_register_link_click();
	}

	private static function _register_link_click() {
		$args = array(
			'labels' => array(),
			'description' => false,
			'public' => false,
			'hierarchical' => false,
			'exclude_from_search' => false,
			'publicly_queryable' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'menu_position' => false,
			'menu_icon' => null,
			'supports' => array(false),
			'register_meta_box_cb' => null,
			'rewrite' => false,
			'query_var' => false,
			'can_export' => false,
			'delete_with_user' => false,
			'taxonomies' => array(),
		);

		register_post_type(self::TYPE_LINK_CLICK, $args);
	}

	private static function _register_link_click_stati() {
		register_post_status('link-click-tracked', array(
			'label'       => __('Tracked'),
			'label_count' => _n_noop('Tracked <span class="count">(%s)</span>', 'Tracked <span class="count">(%s)</span>'),
			'protected' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
		));
	}

	// Tools delegates

	public static function process_tools_actions_purge($data) {
		$period = isset($data['better-links-action-purge-30']) ? 30 : 90;

		$args = array(
			'date_query' => array( array( 'before' => sprintf('%d days ago', $period) ) ),
			'fields' => 'ids',
			'nopaging' => true,
		);

		$clicks_ids = self::_get_clicks_data(false, $args);

		foreach($clicks_ids as $clicks_id) {
			wp_delete_post($clicks_id, true);
		}

		add_settings_error('better_links_tools', 'tools_processed', sprintf(__('Click data purged successfully. %s clicks purged.'), number_format_i18n(count($clicks_ids))), 'updated');
		set_transient('settings_errors', get_settings_errors(), 30);
	}

	public static function process_tools_actions_reset_click_data($data) {
		$id = isset($data['id']) ? $data['id'] : 0;

		if($id) {
			$args = array(
				'fields' => 'ids',
				'nopaging' => true,
			);

			self::_set_link_click_count($id, 0);

			$clicks_ids = self::_get_clicks_data($id, $args);
			foreach($clicks_ids as $clicks_id) {
				wp_delete_post($clicks_id, true);
			}
		}
	}

	public static function process_tools_actions_reset_click_data_redirect_link($redirect_link, $data) {
		$id = isset($data['id']) ? $data['id'] : 0;

		if($id) {
			$redirect_link = add_query_arg(array('message' => 32), get_edit_post_link($id, 'raw'));
		}

		return $redirect_link;
	}

	public static function import_pretty_link($link, $link_id) {
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'prli_clicks';
		$clicks_exists = $clicks_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $clicks_table));

		if($clicks_exists) {
			$clicks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$clicks_table} WHERE link_id = %d", $link->id));

			foreach($clicks as $click) {
				$link_click_id = wp_insert_post(array(
					'comment_status' => 'closed',
					'ping_status' => 'closed',
					'post_date' => $click->created_at,
					'post_parent' => $link_id,
					'post_status' => 'link-click-tracked',
					'post_type' => self::TYPE_LINK_CLICK,
				));


				if(!is_wp_error($link_click_id)) {
					$meta = array(
						'browser' => $click->browser,
						'ip' => $click->ip,
						'post' => 0,
						'referer' => $click->referer,
					);

					self::_set_link_click_meta($link_click_id, $meta);
				}
			}

			self::_set_link_click_count($link_id, count($clicks));
		}
	}

	// Data retrieval delegates

	private static function _get_clicks_args($link_id, $args) {
		$clicks_args = array_merge(array(
			'orderby' => 'date',
			'order' => 'DESC',
			'post_parent' => $link_id,
			'posts_per_page' => 25,
			'post_status' => 'link-click-tracked',
			'post_type' => self::TYPE_LINK_CLICK,
		), $args);

		return $clicks_args;
	}

	private static function _get_clicks_count($link_id, $period = null) {
		if(is_null($period)) {
			return self::_get_link_click_count($link_id);
		} else {
			$args = array(
				'date_query' => array( array( 'after' => sprintf('%d days ago', $period) ) ),
				'fields' => 'ids',
				'posts_per_page' => 1
			);
			$args = self::_get_clicks_args($link_id, $args);
			$clicks = new WP_Query($args);

			$count = $clicks->found_posts;
		}

		return $count;;
	}

	private static function _get_clicks_data($link_id, $args = array()) {
		$args = self::_get_clicks_args($link_id, $args);
		$clicks = get_posts($args);

		return $clicks;
	}
}

function better_links_tracking_init() {
	$dependencies_present = true;

	if($dependencies_present) {
		Better_Links_Tracking::init();
	}
}

add_action('plugins_loaded', 'better_links_tracking_init', 11);