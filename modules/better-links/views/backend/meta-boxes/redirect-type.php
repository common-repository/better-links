<ul>
	<li>
		<label>
			<input type="radio" <?php checked('default', $link_meta['redirect-type']); ?> id="better-links-redirect-type-default" name="better-links[redirect-type]" value="default" />
			<?php _e('Default'); ?>
		</label>
	</li>

	<li>
		<label>
			<input type="radio" <?php checked('301', $link_meta['redirect-type']); ?> id="better-links-redirect-type-301" name="better-links[redirect-type]" value="301" />
			<?php _e('301 - Permanent'); ?>
		</label>
	</li>

	<li>
		<label>
			<input type="radio" <?php checked('302', $link_meta['redirect-type']); ?> id="better-links-redirect-type-302" name="better-links[redirect-type]" value="302" />
			<?php _e('302 - Temporary'); ?>
		</label>
	</li>

	<li>
		<label>
			<input type="radio" <?php checked('307', $link_meta['redirect-type']); ?> id="better-links-redirect-type-307" name="better-links[redirect-type]" value="307" />
			<?php _e('307 - Temporary (alternative)'); ?>
		</label>
	</li>
</ul>
