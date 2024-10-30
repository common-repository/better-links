<?php

// Don't load this file directly
if(!defined('ABSPATH')) { die(); }

function better_links_get_link_meta($link_id = null, $meta_key = null, $default = null) {
	return apply_filters(__FUNCTION__, Better_Links::get_link_meta($link_id, $meta_key, $default), $link_id, $meta_key, $default);
}

function better_links_get_setting($settings_key, $default = null) {
	return apply_filters(__FUNCTION__, Better_Links::get_setting($settings_key, $default), $settings_key, $default);
}