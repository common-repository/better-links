<div class="submitbox better-links-submitbox">
	<input type="submit" class="button button-primary" id="better-links-save" name="better-links-save" value="<?php _e('Save Link'); ?>" />

	<a class="submitdelete" href="<?php echo esc_attr(esc_url($delete_link)); ?>"><?php _e('Remove link'); ?></a>
</div>

<?php wp_nonce_field('better-links-save-post', 'better-links-save-post-nonce'); ?>
