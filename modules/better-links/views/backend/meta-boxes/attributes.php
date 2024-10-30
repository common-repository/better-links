<p>
	<label for="better-links-new-window"><strong><?php _e('New Window:'); ?></strong></label><br />
	<select id="better-links-new-window" name="better-links[new-window]">
		<option <?php selected('default', $link_meta['new-window']); ?> value="default"><?php _e('Default'); ?></option>
		<option <?php selected('yes', $link_meta['new-window']); ?> value="yes"><?php _e('Yes'); ?></option>
		<option <?php selected('no', $link_meta['new-window']); ?> value="no"><?php _e('No'); ?></option>
	</select>
</p>

<p>
	<label for="better-links-nofollow"><strong><?php _e('No Follow:'); ?></strong></label><br />
	<select id="better-links-nofollow" name="better-links[nofollow]">
		<option <?php selected('default', $link_meta['nofollow']); ?> value="default"><?php _e('Default'); ?></option>
		<option <?php selected('yes', $link_meta['nofollow']); ?> value="yes"><?php _e('Yes'); ?></option>
		<option <?php selected('no', $link_meta['nofollow']); ?> value="no"><?php _e('No'); ?></option>
	</select>
</p>
