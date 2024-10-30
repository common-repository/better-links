<ul>
	<li>
		<label>
			<input type="radio" <?php checked('301', $redirect_type); ?> id="better-links-settings-redirect-type-301" name="better-links-settings[redirect-type]" value="301" />
			<?php _e('301 - Permanent'); ?>
		</label>
	</li>

	<li>
		<label>
			<input type="radio" <?php checked('302', $redirect_type); ?> id="better-links-settings-redirect-type-302" name="better-links-settings[redirect-type]" value="302" />
			<?php _e('302 - Temporary'); ?>
		</label>
	</li>

	<li>
		<label>
			<input type="radio" <?php checked('307', $redirect_type); ?> id="better-links-settings-redirect-type-307" name="better-links-settings[redirect-type]" value="307" />
			<?php _e('307 - Temporary (alternative)'); ?>
		</label>
	</li>
</ul>
