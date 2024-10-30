<div class="wrap">
	<form action="options.php" method="post">
		<h2><?php _e('Better Links Settings'); ?></h2>

		<?php settings_errors(); ?>

		<div class="better-links-help">
			<p><?php _e('Need installation help or having trouble?'); ?></p>

			<p><?php printf(__('Watch step by step videos to see how to set up and use this plugin - <a href="%s" target="_blank">click here</a>'), 'http://betterlinkspro.com/how-to/'); ?></p>
		</div>

		<?php do_settings_sections(self::SETTINGS_NAME); ?>

		<p class="submit">
			<?php settings_fields(self::SETTINGS_NAME); ?>
			<input type="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>" />
		</p>
	</form>
</div>