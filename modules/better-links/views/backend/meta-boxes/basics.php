<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="post_title"><?php _e('Name'); ?></label></th>
			<td>
				<input type="text" class="large-text" id="post_title" name="post_title" value="<?php echo esc_attr($post->post_title); ?>" />
				<p class="description"><?php _e('Never displayed to the user, this field is only used identify links in the backend'); ?></p>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row"><label for="better-links-slug"><?php _e('Slug'); ?></label></th>
			<td>
				<div class="better-links-slug-outer">
					<div class="better-links-slug-inner">
						<code class="better-links-slug-domain"><?php echo esc_html(home_url('/')); ?></code>
						<div class="better-links-slug-slug">
							<input type="text" class="code large-text" id="better-links-slug" name="better-links[slug]" value="<?php echo esc_attr($link_meta['slug']); ?>" />
						</div>
					</div>
				</div>

				<?php if('link-active' === $post->post_status) { ?>
				<p class="description"><span class="better-links-copy"><a class="better-links-copy-link" data-clipboard-text="<?php echo esc_attr($permalink); ?>" href="#"><?php _e('Copy link'); ?></a>.</span></p>
				<?php } ?>

				<p class="description"><?php _e('Only letters, numbers, hyphens, underscores, periods, and the forward slash are allowed'); ?></p>

				<?php if(!empty($link_meta_errors['slug'])) { ?>
				<p class="description better-links-error"><?php echo esc_html($link_meta_errors['slug']); ?></p>
				<?php } ?>
			</td>
		</tr>
	</tbody>
</table>