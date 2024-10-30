<p>
	<strong><?php _e('Clicks (total):'); ?></strong><br />
	<?php echo esc_html(number_format_i18n($clicks_count)); ?>
</p>

<p>
	<strong><?php _e('Clicks (30 days):'); ?></strong><br />
	<?php echo esc_html(number_format_i18n($clicks_count_30)); ?>
</p>

<?php if(!empty($clicks_data)) { ?>
<p>
	<strong><?php _e('Last Clicked:'); ?></strong><br />
	<?php echo esc_html($clicks_data[0]->post_date); ?>
</p>

<p>
	<a href="<?php echo esc_attr(esc_url($reset_link)); ?>"><?php _e('Reset click data'); ?></a>
</p>
<?php } ?>