<?php
/*
Plugin Name: Better Links
Plugin URI: http://boostwp.com/products/better-links/
Description: Quickly and easily create a variety of links for your site.
Version: 1.1.4
Author: BoostWP
Author URI: http://boostwp.com/
*/

// Don't load this file directly
if(!defined('ABSPATH')) { die(); }

if(!defined('BETTER_LINKS_VERSION')) {
	// This constant can be overridden by a prior define statement
	// for development purposes (to prevent caching of resources)
	define('BETTER_LINKS_VERSION', '1.1.4');
}

if(!defined('BETTER_LINKS_CACHE_PERIOD')) {
	// This constant can be overridden by a prior define statement
	// for development purposes (or to increase the time). Default
	// is 12 hours
	define('BETTER_LINKS_CACHE_PERIOD', HOUR_IN_SECONDS * 12);
}

if(!function_exists('better_links_redirect')) {
	function better_links_redirect($url, $code = 302) {
		wp_redirect($url, $code);
		exit;
	}
}

function better_links_activation() {
	do_action('better_links_activation');
}
register_activation_hook(__FILE__, 'better_links_activation');

function better_links_deactivation() {
	do_action('better_links_deactivation');
}
register_deactivation_hook(__FILE__, 'better_links_deactivation');

require_once('modules/better-links/better-links.php');
require_once('modules/better-links-tracking/better-links-tracking.php');
