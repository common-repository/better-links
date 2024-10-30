<h3><?php _e('Purge Click Data'); ?></h3>

<p>
	<?php _e('Are you concerned about the size of your database? You can purge click details that are greater than 30 or 90 days old.'); ?>
</p>

<p>
	<strong><?php _e('Important'); ?>:</strong> <?php _e('This action is destructive and irreversible. You will not be able to restore old click data after confirming your choice.'); ?>
</p>

<form action="<?php echo esc_attr(esc_url($tools_page_link)); ?>" method="post">
	<p>
		<input type="hidden" name="better-links-action" value="purge" />
		<?php wp_nonce_field('better-links-action-purge', 'better-links-action-purge-nonce'); ?>

		<input type="submit" class="button button-secondary" name="better-links-action-purge-90" value="<?php _e('Purge Click Data Older than 90 Days'); ?>" />
		<input type="submit" class="button button-secondary" name="better-links-action-purge-30" value="<?php _e('Purge Click Data Older than 30 Days'); ?>" />
	</p>
</form>