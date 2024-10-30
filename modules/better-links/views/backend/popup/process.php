<div class="media-embed">
	<div class="better-links-process">
		<h2 class="nav-tab-wrapper">
			<a href="#search" class="nav-tab" data-bind="click: setSearchStateActive, css: { 'nav-tab-active': searchStateActive() }">
				<?php _e('Insert'); ?>
			</a><a href="#create" class="nav-tab" data-bind="click: setCreateStateActive, css: { 'nav-tab-active': createStateActive() }">
				<?php _e('Create'); ?>
			</a>
		</h2>

		<?php do_action('better_links_before_process'); ?>

		<div data-bind="visible: searchStateActive">
			<label class="setting">
				<span><?php _e('Search Name or Slug'); ?></span>
				<input type="text" class="regular-text better-links-input" id="better-links-search-terms" value="" data-bind="event: { keypress: enterable }, value: searchTerms, valueUpdate: 'afterkeydown'" />
				<input type="button" class="button-primary better-links-button better-links-input" value="<?php _e('Search Links'); ?>" data-bind="click: search, enable: canSearch" />
				<span class="spinner better-links-spinner" data-bind="style: { display: searchActive() ? 'inline-block' : 'none' }"></span>
				<p class="description better-links-error" data-bind="text: errorMessage, visible: hasErrorMessage"></p>
			</label>

			<label class="setting">
				<span><?php _e('Order By'); ?></span>
				<select data-bind="value: orderBy">
					<option value="date"><?php _e('Created (newest first)'); ?></option>
					<option value="title"><?php _e('Name (a-z)'); ?></option>
					<option value="clicks"><?php _e('Clicks (most first)'); ?></option>
				</select>
			</label>

			<?php do_action('better_links_after_search'); ?>

			<?php do_action('better_links_before_results'); ?>

			<div data-bind="visible: hasSearchResults">
				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="pagination-links">
							<a class="prev-page" title="<?php _e('Go to the previous page'); ?>" href="#" data-bind="click: previousPage, css: { disabled: !hasPreviousPage() }">&lsaquo; <?php _e('Previous'); ?></a>
							<span class="paging-input"><span data-bind="text: page"></span> <?php _e('of'); ?> <span class="total-pages" data-bind="text: numberPages"></span></span>
							<a class="next-page" title="<?php _e('Go to the next page'); ?>" href="#" data-bind="click: nextPage, css: { disabled: !hasNextPage() }"><?php _e('Next'); ?> &rsaquo;</a>
						</span>
					</div>
				</div>

				<table class="widefat fixed">
					<thead>
						<tr>
							<th scope="col" class="better-links-name"><?php _e('Name'); ?></th>
							<th scope="col" class="better-links-clicks-total"><?php _e('Clicks (total)'); ?></th>
							<th scope="col" class="better-links-clicks-month"><?php _e('Clicks (30 days)'); ?></th>
							<th scope="col" class="better-links-actions"><?php _e(''); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th scope="col" class="better-links-image"><?php _e('Name'); ?></th>
							<th scope="col" class="better-links-clicks-total"><?php _e('Clicks (total)'); ?></th>
							<th scope="col" class="better-links-clicks-month"><?php _e('Clicks (30 days)'); ?></th>
							<th scope="col" class="better-links-actions"><?php _e(''); ?></th>
						</tr>
					</tfoot>
					<tbody data-bind="template: { foreach: searchResults, name: 'better-links-search-result-template' }"></tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="pagination-links">
							<a class="prev-page" title="<?php _e('Go to the previous page'); ?>" href="#" data-bind="click: previousPage, css: { disabled: !hasPreviousPage() }">&lsaquo; <?php _e('Previous'); ?></a>
							<span class="paging-input"><span data-bind="text: page"></span> <?php _e('of'); ?> <span class="total-pages" data-bind="text: numberPages"></span></span>
							<a class="next-page" title="<?php _e('Go to the next page'); ?>" href="#" data-bind="click: nextPage, css: { disabled: !hasNextPage() }"><?php _e('Next'); ?> &rsaquo;</a>
						</span>
					</div>
				</div>
			</div>

			<?php do_action('better_links_after_results'); ?>

			<script type="text/html" id="better-links-search-result-template">
			<tr>
				<td class="better-links-name">
					<strong data-bind="text: original.post_title"></strong>
				</td>
				<td scope="col" class="better-links-clicks-total" data-bind="text: original.clicks_total"></td>
				<td scope="col" class="better-links-clicks-month" data-bind="text: original.clicks_30"></td>
				<td class="better-links-actions">
					<a href="#" data-bind="click: $parent.insert"><?php _e('Insert'); ?></a>
					|
					<a href="#" data-bind="click: $parent.insertRaw"><?php _e('Insert Raw'); ?></a>
				</td>
			</tr>
			</script>
		</div>

		<div data-bind="visible: createStateActive">
			<label class="setting">
				<span><?php _e('Destination URL'); ?></span>
				<input type="text" class="code large-text" value="" data-bind="value: newUrl, valueUpdate: 'input'" />
			</label>

			<label class="setting">
				<span><?php _e('Name'); ?></span>
				<input type="text" class="code regular-text" value="" data-bind="value: newName, valueUpdate: 'input'" />
			</label>

			<label class="setting">
				<span><?php _e('Slug'); ?></span>
				<input type="text" class="code regular-text" value="" data-bind="value: newSlug, valueUpdate: 'input'" />
			</label>

			<label class="setting">
				<?php printf(__('For more advanced link creation, please <a href="%s" target="_blank">add a new link</a>.'), esc_attr(esc_url($new_link_url))); ?>
			</label>

			<label class="setting better-links-setting-inline-block">
				<input type="button" class="button button-primary" value="<?php _e('Create and Insert'); ?>" data-bind="click: createAndInsert, enable: canCreate" />
			</label>

			<label class="setting better-links-setting-inline-block">
				<input type="button" class="button button-secondary" value="<?php _e('Create and Insert Raw'); ?>" data-bind="click: createAndInsertRaw, enable: canCreate" />
			</label>

			<label class="setting better-links-setting-inline-block">
				<span class="spinner better-links-spinner" data-bind="style: { display: createActive() ? 'inline-block' : 'none' }"></span>
			</label>

			<label class="setting">
				<p class="description better-links-error" data-bind="text: createErrorMessage, visible: hasCreateErrorMessage"></p>
			</label>
		</div>

		<?php do_action('better_links_after_process'); ?>

		<div class="better-links-process-clear"></div>
	</div>
</div>