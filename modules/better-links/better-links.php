<?php

// Don't load this file directly
if(!defined('ABSPATH')) { die(); }

class Better_Links {
	const PAGE_SLUG_PRO = 'better-links-pro';
	const PAGE_SLUG_TOOLS = 'better-links-tools';
	const PAGE_SLUG_SETTINGS = 'better-links-settings';

	const TAXONOMY_LINK_CATEGORY = 'better-link-category';
	const TYPE_LINK = 'better-link';

	const LINK_META_NAME = 'better-links-meta';
	const LINK_META_ERRORS_NAME = 'better-links-meta-errors';
	const LINK_META_SLUG_NAME = 'better-links-meta-slug';

	const SETTINGS_NAME = 'better-links-settings';

	private static $admin_pages = array();

	public static function init() {
		self::_add_actions();
		self::_add_filters();
		self::_add_shortcodes();
	}

	private static function _add_actions() {
		if(is_admin()) {
			add_action('add_meta_boxes_' . self::TYPE_LINK, array(__CLASS__, 'remove_other_meta_boxes'), 9999999);
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
			add_action('admin_init', array(__CLASS__, 'register_setting'));
			add_action('admin_menu', array(__CLASS__, 'add_submenu_items'));
			add_action('admin_notices', array(__CLASS__, 'admin_notices'));
			add_action('dbx_post_sidebar', array(__CLASS__, 'display_upgrade_nag'));
			add_action('media_buttons', array(__CLASS__, 'add_media_buttons'), 11);
			add_action('media_upload_better_links', array(__CLASS__, 'add_media_upload_output'));
			add_action('save_post', array(__CLASS__, 'save_link'), 10, 2);

			add_action('better_links_process_tools_action_create_better_link', array(__CLASS__, 'process_tools_actions_create_better_link'));
			add_action('better_links_process_tools_action_import_pretty_links', array(__CLASS__, 'process_tools_actions_import_pretty_links'));
		} else {

		}

		add_action('delete_post', array(__CLASS__, 'delete_link'));
		add_action('init', array(__CLASS__, 'register'));
		add_action('parse_request', array(__CLASS__, 'parse_request'));
		add_action('post_updated', array(__CLASS__, 'post_updated'));
		add_action('wp_ajax_better-links', array(__CLASS__, 'ajax_callback'));

		add_action('better_links_ajax_callback_create', array(__CLASS__, 'ajax_callback_create'), 10, 2);
		add_action('better_links_ajax_callback_name', array(__CLASS__, 'ajax_callback_name'), 10, 2);
		add_action('better_links_ajax_callback_search', array(__CLASS__, 'ajax_callback_search'), 10, 2);
		add_action('better_links_save_link_finalize', array(__CLASS__, 'save_link_finalize'), 999999, 4);
	}

	private static function _add_filters() {
		if(is_admin()) {
			add_filter(sprintf('bulk_actions-edit-%s', self::TYPE_LINK), array(__CLASS__, 'bulk_actions'));
			add_filter('bulk_post_updated_messages', array(__CLASS__, 'bulk_post_updated_messages'), 10, 2);
			add_filter('display_post_states', array(__CLASS__, 'display_post_states'), 10, 2);
			add_filter('media_upload_tabs', array(__CLASS__, 'add_media_upload_tabs'));
			add_filter('post_row_actions', array(__CLASS__, 'post_row_actions'), 10, 2);
			add_filter('post_updated_messages', array(__CLASS__, 'post_updated_messages'));
			add_filter('redirect_post_location', array(__CLASS__, 'redirect_post_location'), 10, 2);
		} else {

		}

		add_filter('post_type_link', array(__CLASS__, 'post_type_link'), 10, 4);

		add_filter('better_links_ajax_search_results', array(__CLASS__, 'ajax_search_results'));

		add_filter('better_links_pre_get_link_meta', array(__CLASS__, 'pre_get_link_meta'), 10, 2);
		add_filter('better_links_pre_set_link_meta', array(__CLASS__, 'pre_set_link_meta'), 10, 2);
		add_filter('better_links_validate_link_meta', array(__CLASS__, 'validate_link_meta'), 10, 3);

		add_filter('better_links_pre_get_settings', array(__CLASS__, 'pre_get_settings'));

		add_filter('better_links_pre_get_link_meta_errors', array(__CLASS__, 'pre_get_link_meta_errors'), 10, 2);
	}

	private static function _add_shortcodes() {
		add_shortcode('bl', array(__CLASS__, 'shortcode_bl'));
	}

	// AJAX

	public static function ajax_callback() {
		$data = stripslashes_deep($_POST);
		$perform = isset($data['perform']) ? $data['perform'] : false;

		$response = apply_filters("better_links_ajax_callback_{$perform}", array(), $data);

		wp_send_json($response);
	}

	public static function ajax_callback_create($response, $data) {
		$link = $data['link'];

		$name = trim($link['name']);
		$slug = trim($link['slug']);
		$url = trim($link['url']);

		$link_meta = array(
			'slug' => $slug,
			'redirect' => $url,
		);

		$errors = array();
		if(empty($name)) {
			$errors['name'] = __('You must provide a name for your link.');
		}

		$errors = array_merge($errors, self::_validate_link_meta(0, $link_meta));
		$link = array();

		if(empty($errors)) {
			$link_id = wp_insert_post(array(
				'post_status' => 'link-inactive',
				'post_title' => $name,
				'post_type' => self::TYPE_LINK,
			));

			if(is_wp_error($link_id)) {
				$errors = array_merge($errors, $link_id->errors);
			} else {
				self::_set_link_meta($link_id, $link_meta);

				do_action('better_links_save_link_finalize', $link_id, get_post($link_id), $link_meta, $errors);

				wp_cache_delete($link_id, 'posts');

				$link = array(
					'ID' => $link_id,
					'link' => do_shortcode(sprintf('[bl id="%d"]__ANCHOR__[/bl]', $link_id))
				);
			}

		}

		return array(
			'error' => count($errors) > 0,
			'error_message' => array_shift($errors),
			'link' => $link,
		);
	}

	public static function ajax_callback_name($response, $data) {
		$url = isset($data['url']) ? $data['url'] : '';

		$error = false;
		$name = '';
		$response = wp_remote_get($url, array(
			'redirection' => 15,
			'timeout' => 15,
		));

		if(is_wp_error($response)) {
			$error = true;
			$name = $response->get_error_message();
		} else {
			$document = new DOMDocument();

			// We are suppressing the errror here because we honestly don't know what is going to
			// come back from the remote resource
			@$document->loadHTML(wp_remote_retrieve_body($response));

			$titles = $document->getElementsByTagName('title');

			if($titles->length > 0) {
				$title = $titles->item(0);
				$name = strip_tags($title->textContent);
			}
		}

		return compact('error', 'name');
	}

	public static function ajax_callback_search($response, $data) {
		$ids = array();
		if(!empty($data['searchTerms'])) {
			$name_ids = get_posts(array(
				'fields' => 'ids',
				'nopaging' => true,
				'post_status' => 'link-active',
				'post_type' => self::TYPE_LINK,
				's' => $data['searchTerms'],
			));

			$slug_ids = get_posts(array(
				'fields' => 'ids',
				'meta_query' => array(
					array(
						'compare' => 'LIKE',
						'key' => self::LINK_META_SLUG_NAME,
						'value' => strtolower($data['searchTerms']),
					)
				),
				'nopaging' => true,
				'post_status' => 'link-active',
				'post_type' => self::TYPE_LINK,
			));

			$ids = array_unique(array_merge(
				$name_ids,
				$slug_ids
			));
		}

		if(empty($ids) && !empty($data['searchTerms'])) {
			$links = array();
			$page = 1;
			$pages = 1;
		} else {
			$orderby = isset($data['orderby']) && in_array($data['orderby'], array('clicks', 'date', 'title')) ? $data['orderby'] : 'date';
			$order = 'title' === $orderby ? 'ASC' : 'DESC';

			$links_query = new WP_Query(array(
				'orderby' => $orderby,
				'order' => $order,
				'paged' => $data['page'],
				'post__in' => array_map('intval', $ids),
				'post_status' => 'link-active',
				'post_type' => self::TYPE_LINK,
				'posts_per_page' => 10,
			));

			$links = $links_query->posts;
			$page = $data['page'];
			$pages = $links_query->max_num_pages;
		}

		$links = apply_filters('better_links_ajax_search_results', $links);

		return array(
			'items' => $links,
			'page' => $page,
			'pages' => $pages,
		);
	}

	public static function ajax_search_results($links) {
		foreach($links as $link) {
			$link->link = do_shortcode(sprintf('[bl id="%d"]__ANCHOR__[/bl]', $link->ID));
		}

		return $links;
	}

	// Main stuff that redirects appropriately

	public static function parse_request($wp) {
		if(!is_admin()) {
			$links = get_posts(array(
				'meta_query' => array(
					array(
						'compare' => '=',
						'key' => self::LINK_META_SLUG_NAME,
						'value' => $wp->request,
					),
				),
				'posts_per_page' => 1,
				'post_status' => 'link-active',
				'post_type' => self::TYPE_LINK,
			));

			if(!empty($links)) {
				self::_redirect_link($links[0]);
			}
		}
	}

	private static function _redirect_link($link) {
		$settings = self::_get_settings();

		$link_meta = self::_get_link_meta($link->ID);

		$cookie_key = "bl-redirect-{$link->ID}";

		$redirect_type = 'default' === $link_meta['redirect-type'] ? $settings['redirect-type'] : $link_meta['redirect-type'];

		if(isset($_COOKIE[$cookie_key]) && !empty($_COOKIE[$cookie_key])) {
			$redirect_url = urldecode($_COOKIE[$cookie_key]);
		} else {
			$redirect_url = apply_filters('better_links_redirect_url', $link_meta['redirect'], $link_meta, $link->ID);
		}

		do_action('better_links_redirecting', $link);

		setcookie($cookie_key, urlencode($redirect_url), time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());

		better_links_redirect($redirect_url, $redirect_type);
	}

	// Administrative interface

	public static function add_media_buttons() {
		printf('<a class="button insert-better-links" href="%1$s">%2$s</a>', '#', __('Better Links'));
	}

	public static function add_media_upload_output() {
		return wp_iframe(array(__CLASS__, 'get_media_upload_output'));
	}

	public static function get_media_upload_output() {
		$new_link_url = add_query_arg(array('post_type' => self::TYPE_LINK), admin_url('post-new.php'));;

		include('views/backend/popup/process.php');
	}

	public static function add_media_upload_tabs($tabs) {
		return array_merge($tabs, array('better_links' => __('Better Links')));
	}

	public static function add_submenu_items() {
		$tools = self::$admin_pages[] = add_submenu_page(sprintf('edit.php?post_type=%s', self::TYPE_LINK), __('Better Links - Link Tools'), __('Link Tools'), 'edit_posts', self::PAGE_SLUG_TOOLS, array(__CLASS__, 'display_tools_page'));
		$settings = self::$admin_pages[] = add_submenu_page(sprintf('edit.php?post_type=%s', self::TYPE_LINK), __('Better Links - Settings'), __('Settings'), 'manage_options', self::PAGE_SLUG_SETTINGS, array(__CLASS__, 'display_settings_page'));
		$pro = self::$admin_pages[] = add_submenu_page(sprintf('edit.php?post_type=%s', self::TYPE_LINK), __('Better Links - Why Pro?'), __('Why Pro?'), 'manage_options', self::PAGE_SLUG_PRO, '__return_false');

		add_action("load-{$tools}", array(__CLASS__, 'load_tools_page'));
		add_action("load-{$settings}", array(__CLASS__, 'load_settings_page'));
		add_action("load-{$pro}", array(__CLASS__, 'load_pro_page'));
	}

	public static function admin_notices() {
		$screen = get_current_screen();

		if(self::TYPE_LINK === $screen->id) {
			$errors = self::_get_link_meta_errors(get_the_ID());

			if(!empty($errors)) {
				printf('<div class="error"><p id="better-links-errors">%s</p></div>', __('Some errors were detected with this link and it is currently inactive. Please fix the errors below and save this link again.'));
			}
		} else if(self::_is_pretty_link_active()) {
			printf('<div class="error"><p id="better-links-plp-errors">%s</p></div>', sprintf(__('Better Links has detected that Pretty Link is currently active. Please <a href="%s" target="_blank">deactivate Pretty Link</a> to prevent plugin conflicts.'), admin_url('plugins.php')));
		}
	}

	private static function _deactivate_pretty_link() {
		$plugins = get_plugins();

		$active = false;
		foreach($plugins as $plugin_basename => $plugin_data) {
			if(false !== strpos($plugin_data['Name'], 'Pretty Link')) {
				deactivate_plugins($plugin_basename);
			}
		}
	}

	private static function _is_pretty_link_active() {
		$plugins = get_plugins();

		$active = false;
		foreach($plugins as $plugin_basename => $plugin_data) {
			if(false !== strpos($plugin_data['Name'], 'Pretty Link')) {
				if(is_plugin_active($plugin_basename)) {
					$active = true;
					break;
				}
			}
		}

		return $active;
	}

	public static function bulk_actions($actions) {
		unset($actions['edit']);

		return $actions;
	}

	public static function bulk_post_updated_messages($bulk_messages, $bulk_counts) {
		$bulk_messages[self::TYPE_LINK] = array(
			'updated'   => _n('%s link updated.', '%s links updated.', $bulk_counts['updated']),
			'locked'    => _n('%s link not updated, somebody is editing it.', '%s links not updated, somebody is editing them.', $bulk_counts['locked']),
			'deleted'   => _n('%s link permanently deleted.', '%s links permanently deleted.', $bulk_counts['deleted']),
			'trashed'   => _n('%s link moved to the Trash.', '%s links moved to the Trash.', $bulk_counts['trashed']),
			'untrashed' => _n('%s link restored from the Trash.', '%s links restored from the Trash.', $bulk_counts['untrashed']),
		);

		return $bulk_messages;
	}

	public static function display_post_states($post_states, $post) {
		if('link-inactive' === get_post_status($post->ID)) {
			$post_states[] = sprintf('<span class="better-links-error">%s</span>', __('Inactive'));
		}

		return $post_states;
	}

	public static function display_upgrade_nag($post) {
		if(!class_exists('Better_Links_Pro') && self::TYPE_LINK === $post->post_type) {
			include('views/backend/nags/edit.php');
		}
	}

	public static function enqueue_scripts() {
		$screen = get_current_screen();

		if(
			(isset($screen->post_type) && self::TYPE_LINK === $screen->post_type)
			||
			(isset($screen->base) && 'post' === $screen->base)
			||
			(isset($screen->base) && 'media-upload' === $screen->base && isset($_GET['tab']) && 'better_links' === $_GET['tab'])
		) {

			wp_register_script('zero-clipboard', plugins_url('resources/vendor/zero-clipboard.min.js', __FILE__), array(), '1.3.5', true);

			wp_enqueue_style('better-links-backend', plugins_url('resources/backend/better-links.css', __FILE__), array('media-views'), BETTER_LINKS_VERSION);

			wp_enqueue_script('knockout', plugins_url('resources/vendor/knockout.min.js', __FILE__), array(), '3.0.0', true);
			wp_enqueue_script('better-links-backend', plugins_url('resources/backend/better-links.js', __FILE__), array('knockout', 'jquery', 'zero-clipboard'), BETTER_LINKS_VERSION, true);
			wp_localize_script('better-links-backend', 'Better_Links', apply_filters('better_links_localize_script', array(
				'ajaxAction' => 'better-links',

				'copiedText' => __('Copied!'),
				'copyText' => __('Copy'),

				'shortcode' => 'bl',

				'stateName' => 'iframe:better_links',
				'stateTitle' => __('EasyAzon'),

				'zeroClipboardSwfUrl' => plugins_url('resources/vendor/zero-clipboard.swf', __FILE__),
			)));

			do_action('better_links_enqueue_scripts');
		}
	}

	public static function post_row_actions($actions, $post) {
		if(self::TYPE_LINK === $post->post_type) {
			unset($actions['inline hide-if-no-js']);

			if('link-active' === $post->post_status) {
				$actions['better-links-copy'] = sprintf('<a class="better-links-copy-link" data-clipboard-text="%1$s" href="#">%2$s</a>', get_permalink($post->ID), __('Copy'));
				$actions['better-links-visit'] = sprintf('<a href="%1$s">%2$s</a>', get_permalink($post->ID), __('Visit'));
			}
		}

		return $actions;
	}

	public static function post_updated_messages($messages) {
		$messages[self::TYPE_LINK] = array(
			31 => __('Link saved and activated.'),
			32 => __('Link click data reset.'),
		);

		return $messages;
	}

	public static function redirect_post_location($location, $post_id) {
		if(self::TYPE_LINK === get_post_type($post_id)) {
			$errors = self::_get_link_meta_errors($post_id);
			$location = add_query_arg(array('message' => (empty($errors) ? '31' : '32')), $location);
		}

		return $location;
	}

	// Meta boxes

	public static function add_link_meta_boxes($post) {
		$screen = get_current_screen();

		// Core
		add_meta_box('bl-urls', __('URLs'), array(__CLASS__, 'display_link_meta_box_urls'), $screen, 'normal', 'high');
		add_meta_box('bl-basics', __('Basic Information'), array(__CLASS__, 'display_link_meta_box_basics'), $screen, 'normal', 'high');

		// Sidebar
		add_meta_box('bl-actions', __('Actions'), array(__CLASS__, 'display_link_meta_box_actions'), $screen, 'side', 'high');
		add_meta_box('bl-redirect', __('Redirect Type'), array(__CLASS__, 'display_link_meta_box_redirect_type'), $screen, 'side', 'core');
		add_meta_box('bl-attributes', __('Attributes'), array(__CLASS__, 'display_link_meta_box_attributes'), $screen, 'side', 'core');

		// Remove the default publishing meta box because it doesn't make sense in the context of this content type
		remove_meta_box('submitdiv', $screen, 'side');

		do_action('better_links_add_link_meta_boxes', $post);
	}

	public static function remove_other_meta_boxes() {
		global $wp_meta_boxes;

		$screen = get_current_screen();

		$page = $screen->id;

		foreach($wp_meta_boxes[$page] as $context => $priorities) {
			foreach($priorities as $priority => $boxes) {
				foreach($boxes as $id => $box) {
					if(0 !== strpos($id, 'bl-') && 0 !== strpos($id, 'better-link-')) {
						remove_meta_box($id, $screen, $context);
					}
				}
			}
		}
	}

	public static function display_link_meta_box_actions($post) {
		$delete_link = get_delete_post_link($post->ID);
		$link_meta = self::_get_link_meta($post->ID);
		$link_meta_errors = self::_get_link_meta_errors($post->ID);

		$template = apply_filters('better_links_meta_box_actions_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/actions.php'));
		$vars = apply_filters('better_links_meta_box_actions_vars', compact('delete_link', 'link_meta', 'link_meta_errors', 'post'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_link_meta_box_attributes($post) {
		$link_meta = self::_get_link_meta($post->ID);
		$link_meta_errors = self::_get_link_meta_errors($post->ID);

		$template = apply_filters('better_links_meta_box_attributes_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/attributes.php'));
		$vars = apply_filters('better_links_meta_box_attributes_vars', compact('link_meta', 'link_meta_errors', 'post'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_link_meta_box_basics($post) {
		$link_meta = self::_get_link_meta($post->ID);
		$link_meta_errors = self::_get_link_meta_errors($post->ID);
		$permalink = get_permalink($post->ID);

		$template = apply_filters('better_links_meta_box_basics_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/basics.php'));
		$vars = apply_filters('better_links_meta_box_basics_vars', compact('link_meta', 'link_meta_errors', 'permalink', 'post'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_link_meta_box_redirect_type($post) {
		$link_meta = self::_get_link_meta($post->ID);
		$link_meta_errors = self::_get_link_meta_errors($post->ID);

		$template = apply_filters('better_links_meta_box_redirect_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/redirect-type.php'));
		$vars = apply_filters('better_links_meta_box_redirect_vars', compact('link_meta', 'link_meta_errors', 'post'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_link_meta_box_urls($post) {
		$link_meta = self::_get_link_meta($post->ID);
		$link_meta_errors = self::_get_link_meta_errors($post->ID);

		$template = apply_filters('better_links_meta_box_urls_template', path_join(dirname(__FILE__), 'views/backend/meta-boxes/urls.php'));
		$vars = apply_filters('better_links_meta_box_urls_vars', compact('link_meta', 'link_meta_errors', 'post'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	// Registration

	public static function register() {
		self::_register_link_category();
		self::_register_link();
		self::_register_link_stati();
	}

	private static function _register_link_category() {
		$labels = array(
			'name' => __('Link Categories'),
			'singular_name' => __('Link Category'),
			'menu_name' => __('Link Categories'),
			'search_items' => __('Search Link Categories'),
			'popular_items' => null,
			'all_items' => __('All Link Categories'),
			'parent_item' => __('Parent Link Category'),
			'parent_item_colon' => __('Parent Link Category:'),
			'edit_item' => __('Edit Link Category'),
			'view_item' => __('View Link Category'),
			'update_item' => __('Update Link Category'),
			'add_new_item' => __('Add New Link Category'),
			'new_item_name' => __('New Link Category Name'),
			'separate_items_with_commas' => null,
			'add_or_remove_items' => null,
			'choose_from_most_used' => null,
			'not_found' => null,
		);

		$args = array(
			'labels' => $labels,
			'description' => __('Group your links by category to better discern their performance.'),
			'public' => false,
			'hierarchical' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud' => false,
			'meta_box_cb' => null,
			'rewrite' => false,
			'query_var' => false,
		);

		register_taxonomy(self::TAXONOMY_LINK_CATEGORY, array(), $args);
	}

	private static function _register_link() {
		$labels = array(
			'menu_name' => __('Better Links'),
			'name' => __('Better Links'),
			'singular_name' => __('Link'),
			'add_new' => __('Add New'),
			'add_new_item' => __('Add New Link'),
			'edit_item' => __('Edit Link'),
			'new_item' => __('New Link'),
			'view_item' => null,
			'search_items' => __('Search Links'),
			'not_found' => __('No better links found.'),
			'not_found_in_trash' => __('No better links found in Trash.'),
			'parent_item_colon' => null,
			'all_items' => __('Link Dashboard'),
		);

		$args = array(
			'labels' => $labels,
			'description' => __('Easily cloak and track link redirects with Better Links.'),
			'public' => false,
			'hierarchical' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'menu_position' => 20,
			'menu_icon' => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNi4wLjMsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iNTBweCIgaGVpZ2h0PSI1MHB4IiB2aWV3Qm94PSIwIDAgNTAgNTAiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDUwIDUwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxwYXRoIGZpbGw9IiNGRkZGRkYiIGQ9Ik0yNS43NDMsNC4xODNsLTguNzI2LDguNzI2YzIuMjA0LTAuMzI0LDQuNDYtMC4yMyw2LjYzOSwwLjI3NEwyOS4yLDcuNjRjMy42NDItMy42NDQsOS41NjMtMy42NDQsMTMuMjA2LDANCgkJYzMuNjQzLDMuNjM4LDMuNjQzLDkuNTYzLDAsMTMuMjA0bC01LjU0NCw1LjU0M2wtMS40ODgsMS40ODlsLTEuNTgzLDEuNTgybC0zLjYzNywzLjYzOWMtMC42ODMsMC42ODQtMS40NSwxLjI0MS0yLjI2NywxLjY2OA0KCQljLTAuODg5LDAuNDY2LTEuODM4LDAuNzY3LTIuODA5LDAuOTNjLTAuODQsMC4xMzctMS42OTEsMC4xNjQtMi41MzQsMC4wNzRjLTIuMDQyLTAuMjE5LTQuMDMxLTEuMTA3LTUuNTk2LTIuNjcyDQoJCWMtMS41NjMtMS41NjYtMi40NS0zLjU1My0yLjY3Mi01LjU5N2wtNC4wMjEsNC4wMTljMC42OTIsMS44MzUsMS43NjEsMy41NjEsMy4yMzcsNS4wMzVjMS40NzIsMS40NzEsMy4xOTgsMi41NDQsNS4wMzEsMy4yMzMNCgkJYzAuNjI1LDAuMjM0LDEuMjU5LDAuNDIxLDEuOTAzLDAuNTY3YzAuNzEzLDAuMTU5LDEuNDMyLDAuMjYxLDIuMTU2LDAuMzFjMy4wNzYsMC4yMTEsNi4yMDYtMC41NjUsOC44NjYtMi4zNDENCgkJYzAuNzYzLTAuNTEsMS40ODktMS4xLDIuMTYyLTEuNzdsMS4xOS0xLjE5MWwyLjMzNi0yLjMzNGw4LjcyNy04LjcyOWM1LjU0NC01LjU0NSw1LjU0NC0xNC41NywwLTIwLjExOQ0KCQlDNDAuMzE4LTEuMzY2LDMxLjI5LTEuMzY0LDI1Ljc0Myw0LjE4MyIvPg0KCTxwYXRoIGZpbGw9IiNGRkZGRkYiIGQ9Ik0wLjAyMiwyOS45MDZjMC41NTEsMS42ODYsMS4yMzksMy4zMDcsMi4wNTUsNC44NTZsNC4yMzItNC4yMzJsMS40ODYtMS40ODdsMS41ODMtMS41NzlsMy42MzktMy42NDENCgkJYzAuNjg0LTAuNjg1LDEuNDUtMS4yMzksMi4yNjUtMS42NjhjMC44OS0wLjQ2NSwxLjgzOS0wLjc3LDIuODA4LTAuOTI3YzAuODM5LTAuMTQsMS42ODktMC4xNjcsMi41MzYtMC4wNzUNCgkJYzIuMDQ0LDAuMjE5LDQuMDMsMS4xMDYsNS41OTQsMi42N2MxLjU2NiwxLjU2MywyLjQ1MywzLjU1LDIuNjczLDUuNTk3bDQuMDItNC4wMjFjLTAuNjkxLTEuODM1LTEuNzU5LTMuNTYxLTMuMjM0LTUuMDMyDQoJCWMtMS40NzUtMS40NzYtMy4xOTktMi41NDItNS4wMzQtMy4yMzNjLTAuNjI1LTAuMjM2LTEuMjU5LTAuNDI1LTEuOTAyLTAuNTY2Yy0wLjcxNC0wLjE2MS0xLjQzMy0wLjI2Ni0yLjE1OS0wLjMxNA0KCQljLTMuMDc0LTAuMjEtNi4yMDMsMC41NjctOC44NjMsMi4zNDJjLTAuNzYyLDAuNTEzLTEuNDg4LDEuMDk4LTIuMTYyLDEuNzcxbC0xLjE5LDEuMTlsLTIuMzM0LDIuMzM1TDAuMDIyLDI5LjkwNnoiLz4NCgk8cGF0aCBmaWxsPSIjRkZGRkZGIiBkPSJNMTkuNTE0LDQzLjczNGwtNC4yMzEsNC4yMzNjMS41NDcsMC44MTUsMy4xNzEsMS41MDQsNC44NTYsMi4wNTRsNi4wMTYtNi4wMTINCgkJQzIzLjk0NSw0NC4zMzQsMjEuNjkxLDQ0LjI0MywxOS41MTQsNDMuNzM0Ii8+DQo8L2c+DQo8L3N2Zz4NCg==',
			'supports' => array(false),
			'register_meta_box_cb' => array(__CLASS__, 'add_link_meta_boxes'),
			'rewrite' => false,
			'query_var' => false,
			'can_export' => true,
			'delete_with_user' => false,
			'taxonomies' => array(self::TAXONOMY_LINK_CATEGORY),
		);

		register_post_type(self::TYPE_LINK, $args);
	}

	private static function _register_link_stati() {
		register_post_status('link-active', array(
			'label'       => __('Active'),
			'label_count' => _n_noop('Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>'),
			'protected' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
		));

		register_post_status('link-inactive', array(
			'label'       => __('Inactive'),
			'label_count' => _n_noop('Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>'),
			'protected' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
		));
	}

	// Tools

	public static function display_tools_page() {
		$can_import_pretty_links = self::_can_import_pretty_links();

		$tools_bookmarklet_link = self::_get_bookmarklet_link();
		$tools_page_link = self::_get_tools_page_link();

		$template = apply_filters('better_links_page_tools_template', path_join(dirname(__FILE__), 'views/backend/pages/tools.php'));
		$vars = apply_filters('better_links_page_tools_vars', compact('can_import_pretty_links', 'tools_bookmarklet_link', 'tools_page_link'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function load_tools_page() {
		$data = stripslashes_deep($_REQUEST);

		$action = isset($data['better-links-action']) ? $data['better-links-action'] : false;

		$action_nonce_action = "better-links-action-{$action}";
		$action_nonce_value = isset($data["{$action_nonce_action}-nonce"]) ? $data["{$action_nonce_action}-nonce"] : false;

		if($action && (($action_nonce_value && wp_verify_nonce($action_nonce_value, $action_nonce_action)) || (('create_better_link' === $action) && current_user_can('edit_posts')))) {
			do_action("better_links_process_tools_action_{$action}", $data);

			$redirect_link = apply_filters("better_links_process_tools_action_{$action}_redirect_link", self::_get_tools_page_link(array('settings-updated' => 'true')), $data);

			better_links_redirect($redirect_link);
		}
	}

	public static function process_tools_actions_create_better_link($data) {
		$slug = self::_generate_unique_slug();
		$title = $data['title'];
		$url = $data['url'];

		$link_id = wp_insert_post(array(
			'post_status' => 'link-active',
			'post_title' => $title,
			'post_type' => self::TYPE_LINK,
		));

		if(is_wp_error($link_id)) {
			add_settings_error('better_links_tools', 'tools_processed', sprintf(__('Link could not be created. <a href="%s">Create one manually</a>.'), add_query_arg(array('post_type' => self::TYPE_LINK), admin_url('post-new.php'))), 'updated');
		} else {
			$link_meta = array(
				'slug' => $slug,
				'redirect' => $url,
			);

			self::save_link($link_id, get_post($link_id), $link_meta, true);

			add_settings_error('better_links_tools', 'tools_processed', sprintf(__('Link created! <a href="%s">Edit it</a>. <span class="better-links-copy"><a class="better-links-copy-link" data-clipboard-text="%2$s" href="#">Copy it</a>.</span> Share it: <code>%2$s</code>.'), get_edit_post_link($link_id), get_permalink($link_id)), 'updated');
		}

		set_transient('settings_errors', get_settings_errors(), 30);
	}

	public static function process_tools_actions_import_pretty_links($data) {
		$count = self::_do_import_pretty_links();

		if(self::_is_pretty_link_active()) {
			self::_deactivate_pretty_link();
		}

		add_settings_error('better_links_tools', 'tools_processed', sprintf(__('%s Pretty Links successfully imported.'), number_format_i18n($count)), 'updated');
		set_transient('settings_errors', get_settings_errors(), 30);
	}

	private static function _can_import_pretty_links() {
		global $wpdb;

		$tables = array(
			'groups' => $wpdb->prefix . 'prli_groups',
			'links' => $wpdb->prefix . 'prli_links',
		);

		$can_import_pretty_links = true;
		foreach($tables as $table) {
			if($table !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
				$can_import_pretty_links = false;
				break;
			}
		}

		return $can_import_pretty_links;
	}

	private static function _do_import_pretty_links() {
		set_time_limit(0);

		global $wpdb;

		$count = 0;

		$keywords_table = $wpdb->prefix . 'prli_keywords';
		$keywords_exists = $keywords_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $keywords_table));

		$link_rotations_table = $wpdb->prefix . 'prli_link_rotations';
		$link_rotations_exists = $link_rotations_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $link_rotations_table));

		$groups = array();
		$group_results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}prli_groups");
		foreach($group_results as $group_result) {
			if(!($group = term_exists($group_result->name, self::TAXONOMY_LINK_CATEGORY))) {
				$group = wp_insert_term($group_result->name, self::TAXONOMY_LINK_CATEGORY, array('description' => $group_result->description));
			}

			if(!is_wp_error($group)) {
				$term_id = $group['term_id'];
				$groups[$group_result->id] = array(intval($term_id));
			}
		}

		$link_meta_defaults = self::_get_link_meta_defaults(0);
		$valid_redirect_types = array(301, 302, 307);

		$links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}prli_links");
		foreach($links as $_link) {
			if($keywords_exists) {
				$keywords = $wpdb->get_col($wpdb->prepare("SELECT text FROM {$keywords_table} WHERE link_id = %d", $_link->id));
			} else {
				$keywords = array();
			}

			$redirects = array($_link->url);
			if($link_rotations_exists) {
				$redirects = array_merge($redirects, $wpdb->get_col($wpdb->prepare("SELECT url FROM {$link_rotations_table} WHERE link_id = %d", $_link->id)));
			}

			$link_id = wp_insert_post(array(
				'post_date' => $_link->created_at,
				'post_status' => 'link-inactive',
				'post_title' => $_link->name,
				'post_type' => self::TYPE_LINK,
			));

			if(!is_wp_error($link_id)) {
				$link = get_post($link_id);

				$link_meta = array(
					'slug' => $_link->slug,

					'redirect-scheme' => 1 === count($redirects) ? 'single' : 'multiple',

					'redirect' => (1 === count($redirects) ? $redirects[0] : ''),
					'redirect-multiple' => (1 < count($redirects) ? $redirects : ''),

					'nofollow' => (1 == $_link->nofollow) ? 'yes' : 'no',
					'redirect-type' => in_array($_link->redirect_type, $valid_redirect_types) ? $_link->redirect_type : $link_meta_defaults['redirect-type'],

					'keywords' => $keywords,
				);

				$link_meta = self::_set_link_meta($link_id, $link_meta);

				$link_meta_errors = self::_validate_link_meta($link_id, $link_meta);
				$link_meta_errors = self::_set_link_meta_errors($link_id, $link_meta_errors);

				do_action('better_links_save_link_finalize', $link_id, $link, $link_meta, $link_meta_errors);

				if($link->group_id && isset($groups[$link->group_id])) {
					wp_set_object_terms($link_id, $groups[$link->group_id], self::TAXONOMY_LINK_CATEGORY);
				}

				$count++;

				do_action('better_links_import_pretty_link', $link, $link_id);
			}
		}

		return $count;
	}

	private static function _get_bookmarklet_link() {
		$admin_url = self::_get_tools_page_link(array('better-links-action' => 'create_better_link'));
		$link_url = "javascript:
				var d=document,
				w=window,
				f='{$admin_url}',
				l=d.location,
				e=encodeURIComponent,
				u=f+'&url='+e(l.href)+'&title='+e(d.title);
				a=function(){if(!w.open(u,'_blank',''))l.href=u;};
				if (/Firefox/.test(navigator.userAgent)) { setTimeout(a, 0); } else { a(); };
				void(0);";

		$link_url = str_replace(array("\r", "\n", "\t"), '', $link_url);

		return $link_url;
	}

	// Pro

	public static function load_pro_page() {
		better_links_redirect('http://betterlinkspro.com/why-pro/?utm_source=betterlinksplugin&utm_medium=link&utm_campaign=betterlinkspluginsidebar');
	}

	// Link delete and save

	public static function delete_link($link_id) {
		if(self::TYPE_LINK === get_post_type($link_id)) {
			do_action('better_links_delete_link', $link_id);
		}
	}

	public static function post_updated($post_id) {
		if(self::TYPE_LINK === get_post_type($post_id)) {
			do_action('better_links_link_updated');
		}
	}

	public static function save_link($post_id, $post, $link_meta = array(), $override = false) {
		$data = stripslashes_deep($_POST);

		$post_type = $post->post_type;
		$link_meta = isset($data['better-links']) && is_array($data['better-links']) ? $data['better-links'] : $link_meta;
		$nonce_action = 'better-links-save-post';
		$nonce_value = isset($data["{$nonce_action}-nonce"]) ? $data["{$nonce_action}-nonce"] : false;

		if(self::TYPE_LINK !== $post_type || ((!$nonce_value || !wp_verify_nonce($nonce_value, $nonce_action)) && !$override)) {
			return;
		}

		$link_meta = self::_set_link_meta($post_id, $link_meta);

		$link_meta_errors = self::_validate_link_meta($post_id, $link_meta);
		$link_meta_errors = self::_set_link_meta_errors($post_id, $link_meta_errors);

		do_action('better_links_save_link_finalize', $post_id, $post, $link_meta, $link_meta_errors);
	}

	public static function save_link_finalize($link_id, $link, $link_meta, $link_meta_errors) {
		if(!isset($link_meta_errors['slug'])) {
			update_post_meta($link_id, self::LINK_META_SLUG_NAME, $link_meta['slug']);
		} else {
			delete_post_meta($link_meta, self::LINK_META_SLUG_NAME);
		}

		global $wpdb;
		$wpdb->update($wpdb->posts, array('post_status' => (empty($link_meta_errors) ? 'link-active' : 'link-inactive')), array('ID' => $link_id));
	}

	// Link data

	public static function get_link_meta($link_id, $meta_key, $default = null) {
		$link_meta = self::_get_link_meta($link_id);

		return isset($link_meta[$meta_key]) ? $link_meta[$meta_key] : $default;
	}

	public static function post_type_link($post_link, $post, $leavename, $sample) {
		if(self::TYPE_LINK === $post->post_type) {
			$post_link = home_url(better_links_get_link_meta($post->ID, 'slug'));
		}

		return $post_link;
	}

	public static function pre_get_link_meta($link_meta, $link_id) {
		$link_meta = is_array($link_meta) ? $link_meta : array();

		return shortcode_atts(self::_get_link_meta_defaults($link_id), $link_meta);
	}

	public static function pre_set_link_meta($link_meta, $link_id) {
		$link_meta = is_array($link_meta) ? $link_meta : array();

		if(isset($link_meta['redirect']) && !empty($link_meta['redirect']) && !preg_match('#^https?://#', $link_meta['redirect'])) {
			$link_meta['redirect'] = 'http://' . $link_meta['redirect'];
		}

		if(isset($link_meta['slug'])) {
			$link_meta['slug'] = untrailingslashit($link_meta['slug']);
		}

		return shortcode_atts(self::_get_link_meta_defaults($link_id), $link_meta);
	}

	public static function validate_link_meta($link_meta_errors, $link_id, $link_meta) {
		$link_slug = isset($link_meta['slug']) ? $link_meta['slug'] : '';
		if(empty($link_slug)) {
			$link_meta_errors['slug'] = __('A link slug is required');
		} else if(preg_match('#[^A-Za-z0-9\-_/\.]#', $link_meta['slug'])) {
			$link_meta_errors['slug'] = __('This slug contains invalid characters');
		} else if(($link_slug_id = self::_get_link_id_for_slug($link_slug)) && $link_slug_id != $link_id) {
			$link_meta_errors['slug'] = __('This slug is already in use');
		}

		$link_redirect = isset($link_meta['redirect']) ? $link_meta['redirect'] : '';
		if(empty($link_redirect)) {
			$link_meta_errors['redirect'] = __('A destination url is required');
		} else if(!Better_Links::validate_url($link_redirect)) {
			$link_meta_errors['redirect'] = __('This destination url is not valid');
		}

		return $link_meta_errors;
	}

	public static function validate_url($url) {
		$response = wp_remote_get($url);

		return !is_wp_error($response);
	}

	private static function _generate_unique_slug() {
		do {
			$link_slug = wp_generate_password(8, false, false);
		} while(($link_id = self::_get_link_id_for_slug($link_slug)));

		return $link_slug;
	}

	private static function _get_link_id_for_slug($link_slug) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->posts}.post_status = 'link-active' WHERE meta_key = %s AND meta_value = %s LIMIT 1", self::LINK_META_SLUG_NAME, $link_slug));
	}

	private static function _get_link_meta($link_id) {
		$link_id = is_null($link_id) && in_the_loop() ? get_the_ID() : $link_id;

		$link_meta = false;
		if(!empty($link_id)) {
			$link_meta = apply_filters('better_links_pre_get_link_meta', get_post_meta($link_id, self::LINK_META_NAME, true), $link_id);

			wp_cache_set(self::LINK_META_NAME, $link_meta, $link_id, BETTER_LINKS_CACHE_PERIOD);
		}

		return $link_meta;
	}

	private static function _get_link_meta_defaults($link_id) {
		return apply_filters('better_links_pre_get_link_meta_defaults', array(
			// Redirection
			'redirect-scheme' => 'single',
			'slug' => '',

			// Different schemes
			'redirect' => '',

			// Attributes
			'new-window' => 'default',
			'nofollow' => 'default',
			'redirect-type' => 'default',
		));
	}

	public  static function _set_link_meta($link_id, $link_meta) {
		$link_meta = apply_filters('better_links_pre_set_link_meta', $link_meta, $link_id);

		update_post_meta($link_id, self::LINK_META_NAME, $link_meta);

		wp_cache_delete(self::LINK_META_NAME, $link_id);

		return $link_meta;
	}

	private static function _validate_link_meta($link_id, $link_meta) {
		return apply_filters('better_links_validate_link_meta', array(), $link_id, $link_meta);
	}

	// Link meta errors

	public static function pre_get_link_meta_errors($link_meta_errors, $link_id) {
		$link_meta_errors = is_array($link_meta_errors) ? $link_meta_errors : array();

		return $link_meta_errors;
	}

	private static function _get_link_meta_errors($link_id) {
		$link_id = is_null($link_id) && in_the_loop() ? get_the_ID() : $link_id;

		$link_meta_errors = false;
		if(!empty($link_id)) {
			$link_meta_errors = apply_filters('better_links_pre_get_link_meta_errors', get_post_meta($link_id, self::LINK_META_ERRORS_NAME, true), $link_id);

			wp_cache_set(self::LINK_META_ERRORS_NAME, $link_meta_errors, $link_id, BETTER_LINKS_CACHE_PERIOD);
		}

		return $link_meta_errors;
	}

	private static function _set_link_meta_errors($link_id, $link_meta_errors) {
		$link_meta_errors = apply_filters('better_links_pre_set_link_meta_errors', $link_meta_errors, $link_id);

		update_post_meta($link_id, self::LINK_META_ERRORS_NAME, $link_meta_errors);

		wp_cache_delete(self::LINK_META_ERRORS_NAME, $link_id);

		return $link_meta_errors;
	}

	// Settings

	public static function display_settings_page() {
		$settings = self::_get_settings();
		$settings_page_link = self::_get_settings_page_link();

		$template = apply_filters('better_links_page_settings_template', path_join(dirname(__FILE__), 'views/backend/pages/settings.php'));
		$vars = apply_filters('better_links_page_settings_vars', compact('settings', 'settings_page_link'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	/// Defaults

	public static function display_settings_page_section_defaults() {
		$template = apply_filters('better_links_settings_page_section_defaults_template', path_join(dirname(__FILE__), 'views/backend/sections/defaults.php'));
		$vars = apply_filters('better_links_settings_page_section_defaults_vars', array());

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_settings_page_field_defaults_redirect_type() {
		$redirect_type = better_links_get_setting('redirect-type');

		$template = apply_filters('better_links_settings_page_field_defaults_redirect_type_template', path_join(dirname(__FILE__), 'views/backend/fields/redirect-type.php'));
		$vars = apply_filters('better_links_settings_page_field_defaults_redirect_type_vars', compact('redirect_type'));

		extract($vars);

		if(file_exists($template)) {
			include($template);
		}
	}

	public static function display_settings_page_field_defaults_new_window() {
		$new_window = better_links_get_setting('new-window');
		$new_window_checked = 'yes' === $new_window ? 'checked="checked"' : '';
		$new_window_label = __('By default, links should open in new windows or tabs');

		printf('<input type="hidden" id="better-links-settings-new-window-no" name="better-links-settings[new-window]" value="no" />');
		printf('<label><input type="checkbox" %s id="better-links-settings-new-window-yes" name="better-links-settings[new-window]" value="yes" /> %s</label>', $new_window_checked, esc_html($new_window_label));
	}

	public static function display_settings_page_field_defaults_nofollow() {
		$nofollow = better_links_get_setting('nofollow');
		$nofollow_checked = 'yes' === $nofollow ? 'checked="checked"' : '';
		$nofollow_label = __('By default, links should have the nofollow attribute applied');

		printf('<input type="hidden" id="better-links-settings-nofollow-no" name="better-links-settings[nofollow]" value="no" />');
		printf('<label><input type="checkbox" %s id="better-links-settings-nofollow-yes" name="better-links-settings[nofollow]" value="yes" /> %s</label>', $nofollow_checked, esc_html($nofollow_label));
	}

	/// Other

	public static function get_setting($settings_key, $default = null) {
		$settings = self::_get_settings();

		return isset($settings[$settings_key]) ? $settings[$settings_key] : $default;
	}

	public static function load_settings_page() {
		add_settings_section('defaults', __('Defaults'), array(__CLASS__, 'display_settings_page_section_defaults'), self::SETTINGS_NAME);
		add_settings_field('redirect-type', __('Redirect Type'), array(__CLASS__, 'display_settings_page_field_defaults_redirect_type'), self::SETTINGS_NAME, 'defaults', array());
		add_settings_field('new-window', __('New Window'), array(__CLASS__, 'display_settings_page_field_defaults_new_window'), self::SETTINGS_NAME, 'defaults', array('label_for' => 'better-links-new-window-yes'));
		add_settings_field('nofollow', __('No Follow'), array(__CLASS__, 'display_settings_page_field_defaults_nofollow'), self::SETTINGS_NAME, 'defaults', array('label_for' => 'better-links-nofollow-yes'));

		do_action('better_links_load_settings_page');
	}

	public static function pre_get_settings($settings) {
		$settings = is_array($settings) ? $settings : array();

		return shortcode_atts(self::_get_settings_defaults(), $settings);
	}

	public static function register_setting() {
		register_setting(self::PAGE_SLUG_SETTINGS, self::SETTINGS_NAME, array(__CLASS__, 'sanitize_settings'));
	}

	public static function sanitize_settings($settings) {
		$settings['new-window'] = 'yes' === $settings['new-window'] ? 'yes' : 'no';
		$settings['nofollow'] = 'yes' === $settings['nofollow'] ? 'yes' : 'no';

		$settings = apply_filters('better_links_pre_set_settings', $settings);

		wp_cache_delete(self::SETTINGS_NAME);

		return $settings;
	}

	private static function _get_settings() {
		$settings = apply_filters('better_links_pre_get_settings', get_option(self::SETTINGS_NAME, self::_get_settings_defaults()));

		wp_cache_set(self::SETTINGS_NAME, $settings, null, BETTER_LINKS_CACHE_PERIOD);

		return $settings;
	}

	private static function _get_settings_defaults() {
		return apply_filters('better_links_pre_get_settings_defaults', array(
			'new-window' => 'no',
			'nofollow' => 'yes',
			'redirect-type' => '302',
		));
	}

	// Admin links

	public static function _get_settings_page_link($query_args = array()) {
		$query_args = array_merge(array(
			'page' => self::PAGE_SLUG_SETTINGS,
			'post_type' => self::TYPE_LINK,
		), $query_args);

		return add_query_arg($query_args, admin_url('edit.php'));
	}

	public static function _get_tools_page_link($query_args = array()) {
		$query_args = array_merge(array(
			'page' => self::PAGE_SLUG_TOOLS,
			'post_type' => self::TYPE_LINK,
		), $query_args);

		return add_query_arg($query_args, admin_url('edit.php'));
	}

	// Frontend display

	public static function shortcode_bl($atts = array(), $content = null) {
		$atts = shortcode_atts(array(
			'id' => '0',
			'new_window' => '',
			'nofollow' => '',
		), $atts, 'bl');

		if(empty($atts['id']) || self::TYPE_LINK !== get_post_type($atts['id']) || 'link-active' !== get_post_status($atts['id'])) {
			$output = $content;
		} else {
			$link_new_window = better_links_get_link_meta($atts['id'], 'new-window');
			$link_nofollow = better_links_get_link_meta($atts['id'], 'nofollow');

			if(empty($atts['new_window'])) {
				$new_window = 'default' === $link_new_window ? better_links_get_setting('new-window') : $link_new_window;
			} else {
				$new_window = 'yes' === $atts['new_window'] ? 'yes' : 'no';
			}

			if(empty($atts['nofollow'])) {
				$nofollow = 'default' === $link_nofollow ? better_links_get_setting('nofollow') : $link_nofollow;
			} else {
				$nofollow = 'yes' === $atts['nofollow'] ? 'yes' : 'no';
			}

			$link_attributes = array();

			$link_attributes[] = 'class="bl-link"';

			if('yes' === $new_window) {
				$link_attributes[] = 'target="_blank"';
			}

			if('yes' === $new_window) {
				$link_attributes[] = 'rel="nofollow"';
			}

			$link_attributes[] = sprintf('data-bl-id="%d"', $atts['id']);

			$link_html = sprintf('<a href="%1$s" %2$s>%3$s</a>', get_permalink($atts['id']), implode(' ', $link_attributes), $content);

			$output = apply_filters('better_links_shortcode_bl', $link_html, $atts, $content);
		}

		return $output;
	}
}

function better_links_init() {
	$dependencies_present = true;

	if($dependencies_present) {
		Better_Links::init();

		require_once('lib/template-tags.php');
	}
}

add_action('plugins_loaded', 'better_links_init');