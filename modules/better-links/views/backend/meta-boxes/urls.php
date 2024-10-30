<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="better-links-redirect"><?php _e('Destination URL'); ?></label></th>
			<td>
				<input type="text" class="code large-text" id="better-links-redirect" name="better-links[redirect]" value="<?php echo esc_attr($link_meta['redirect']); ?>" />
				<p class="description"><?php _e('Please provide a valid url'); ?></p>

				<?php if(!empty($link_meta_errors['redirect'])) { ?>
				<p class="description better-links-error"><?php echo esc_html($link_meta_errors['redirect']); ?></p>
				<?php } ?>
			</td>
		</tr>
	</tbody>
</table>
