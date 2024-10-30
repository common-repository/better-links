<div class="wrap">
	<h2><?php _e('Link Tools'); ?></h2>

	<?php settings_errors('better_links_tools', get_defined_vars()); ?>

	<?php do_action('better_links_tools_page_top'); ?>

	<h3><?php _e('Better Link This') ?></h3>

	<p>
		<?php _e('<em>Better Link This</em> is a bookmarklet: a little app that runs in your browser and lets you grab bits of the web.'); ?>
	</p>

	<p>
		<?php _e('Use <em>Better Link This</em> to instantly create a Better Link for any page.'); ?>
	</p>

	<p class="description">
		<?php _e('Drag-and-drop the following link to your bookmarks bar or right click it and add it to your favorites for a linking shortcut.'); ?>
	</p>

	<p class="pressthis">
		<a onclick="return false;" href="<?php echo esc_attr($tools_bookmarklet_link); ?>">
			<span><?php _e('Better Link This'); ?></span>
		</a>
	</p>

	<?php if($can_import_pretty_links) { ?>

	<h3><?php _e('Import from Pretty Link'); ?></h3>

	<p>
		<?php _e('If you have previously used or are currently using Pretty Link or Pretty Link Pro, you can import your links data into Better Links.'); ?><br />
		<?php _e('<strong>Note:</strong> For performance reasons, Better Links will not import Pretty Link click data.'); ?><br />
		<?php _e('<strong>Note:</strong> If you have over 100 links this import process will take longer. Don\'t close your browser window or click the button again.'); ?>
	</p>

	<form action="<?php echo esc_attr(esc_url($tools_page_link)); ?>" id="better-links-import-pretty-links-form" method="post">
		<p>
			<input type="hidden" name="better-links-action" value="import_pretty_links" />
			<?php wp_nonce_field('better-links-action-import_pretty_links', 'better-links-action-import_pretty_links-nonce'); ?>

			<input type="submit" class="button button-secondary" id="better-links-action-import_pretty_links" name="better-links-action-import_pretty_links" value="<?php _e('Import Pretty Link Data'); ?>" />
		</p>
	</form>

	<?php } ?>

	<?php do_action('better_links_tools_page_bottom', get_defined_vars()); ?>
</div>
