var betterLinksSelection = null;

function betterLinksAutoTitle(event) {
	var $this = jQuery(this),
		$name = jQuery('#post_title'),
		name = jQuery.trim($name.val()),
		url = jQuery.trim($this.val());

	if('' === name && '' !== url) {
		jQuery.post(
			ajaxurl,
			{
				action: Better_Links.ajaxAction,
				perform: 'name',
				url: url
			},
			function(data, status) {
				debugger;
				if(data.error) {

				} else {
					$name.val(data.name);
				}
			},
			'json'
		);
	}
}

function betterLinksGetSelection() {
	var ed, textarea, activeEditor = wpActiveEditor, selection = '';

	if('undefined' === typeof activeEditor) {
		activeEditor = 'content';
	}

	if(activeEditor && 'undefined' !== typeof(tinymce) && (ed = tinymce.get(activeEditor)) && !ed.isHidden()) {
		selection = ed.selection.getContent();
	} else {
		textarea = document.getElementById(activeEditor);

		if(textarea) {
		    if('selectionStart' in textarea) {
		        selection = textarea.value.substr(textarea.selectionStart, textarea.selectionEnd - textarea.selectionStart);
		    } else if(document.selection) {
		        textarea.focus();
		        var r = document.selection.createRange();
		        var tr = textarea.createTextRange();
		        var tr2 = tr.duplicate();
		        tr2.moveToBookmark(r.getBookmark());
		        tr.setEndPoint('EndToStart',tr2);
		        if (r !== null && tr !== null) {
			        var text_part = r.text.replace(/[\r\n]/g,'.'); //for some reason IE doesn't always count the \n and \r in the length
			        var text_whole = textarea.value.replace(/[\r\n]/g,'.');
			        var the_start = text_whole.indexOf(text_part,tr.text.length);
			        selection = r.text;
		        }
		    }
		}
	}

	return selection;
}

jQuery(document).ready(function($) {
	if(ZeroClipboard) {
		ZeroClipboard.config({
			moviePath: Better_Links.zeroClipboardSwfUrl
		});

		var zeroClipboardClient = new ZeroClipboard($('.better-links-copy-link'));

		zeroClipboardClient.on('noflash', function(client, args) {
			$('.better-links-copy').remove();
		});

		zeroClipboardClient.on('mouseover', function(client, args) {
			$(this).parents('.row-actions').addClass('visible');
		});

		zeroClipboardClient.on('mouseout', function(client, args) {
			$(this).parents('.row-actions').removeClass('visible');
		});

		zeroClipboardClient.on('complete', function(client, args) {
			var $this = $(this);

			$this.text(Better_Links.copiedText);
			setTimeout(function() { $this.text(Better_Links.copyText); }, 1500);
		});
	}

	$(document.body).on('click', '.insert-better-links', function(event) {
		event.preventDefault();

		var $this = $(this),
			editor = Better_Links.stateName,
			workflow = wp.media.editor.add(editor, { frame: 'post', state: Better_Links.stateName, title: Better_Links.stateTitle });

		betterLinksSelection = betterLinksGetSelection();

		workflow.once('open', function() {
			jQuery('.media-frame').addClass('hide-menu');
		});

		workflow.once('close', function() {
			jQuery('.media-frame').removeClass('hide-menu');
		});

		wp.media.editor.open(editor, { title: Better_Links.stateTitle });
	});

	$(document.body).on('submit', '#better-links-import-pretty-links-form', function(event) {
		$('#better-links-action-import_pretty_links').prop('disabled', true);
	});

	$(document).on('change', '#better-links-redirect', betterLinksAutoTitle);

	var $betterLinksProcess = $('.better-links-process');

	if($betterLinksProcess.size() > 0) {
		var BLVM = new BetterLinksVM();

		window.BLVM = BLVM;

		for(var i = 0; i < window.BLVM_CALLBACKS.length; i++) {
			window.BLVM_CALLBACKS[i](BLVM);
		}

		ko.applyBindings(BLVM, $betterLinksProcess.get(0));

		window.BLVM.doSearch();
	}
});

window.BLVM_CALLBACKS = window.BLVM_CALLBACKS || [];

var BetterLinksVM = function() {
	var self = this;

	// Errors
	self.errorMessage = ko.observable('');
	self.hasErrorMessage = ko.computed(function() { return '' !== self.errorMessage(); });

	self.createErrorMessage = ko.observable('');
	self.hasCreateErrorMessage = ko.computed(function() { return '' !== self.createErrorMessage(); });

	// Search terms
	self.lastOrderBy = ko.observable('date');
	self.orderBy = ko.observable('date');

	self.orderBy.subscribe(function(value) {
		self.search();
	});

	self.lastSearchTerms = ko.observable('');
	self.searchTerms = ko.observable('');
	self.hasSearchTerms = ko.computed(function() { return '' !== jQuery.trim(self.searchTerms()); });

	// Search results
	self.searchResults = ko.observableArray();

	// Pagination
	self.page = ko.observable(1);
	self.numberPages = ko.observable(1);

	// Creation
	self.newName = ko.observable('');
	self.newSlug = ko.observable('');
	self.newUrl = ko.observable('');

	// Flags
	self.createActive = ko.observable(false);
	self.searchActive = ko.observable(false);

	self.canCreate = ko.computed(function() { return !self.createActive() && '' !== self.newName() && '' !== self.newSlug() && '' !== self.newUrl(); });
	self.canSearch = ko.computed(function() { return !self.searchActive(); });

	self.hasNextPage = ko.computed(function() { return self.canSearch() && self.page() < self.numberPages() && false !== self.lastSearchTerms(); });
	self.hasPreviousPage = ko.computed(function() { return self.canSearch() && self.page() > 1 && false !== self.lastSearchTerms(); });
	self.hasSearchResults = ko.computed(function() { return self.searchResults().length > 0; });

	// State
	self.state = ko.observable('search');

	self.createStateActive = ko.computed(function() { return 'create' === self.state(); });
	self.searchStateActive = ko.computed(function() { return 'search' === self.state(); });

	self.setCreateStateActive = function() { self.state('create'); };
	self.setSearchStateActive = function() { self.state('search'); };

	// Shortcodes
	self.insertShortcode = function(shortcode, attributes, content, forceClose) {
		var win = window.dialogArguments || opener || parent || top, html = '';

		html += '[' + shortcode;
		html += jQuery.map(attributes, function(value, name) { return (!value) ? ('') : (' ' + name + '="' + value + '"'); }).join('');
		html += ']';
		html += (('' === jQuery.trim(content) && !forceClose) ? '' : (content + '[/' + shortcode + ']'));

		self.setSearchStateActive();
		win.send_to_editor(html);
	};

	// Link creation
	function doCreate() {
		self.createErrorMessage('');
		self.createActive(true);

		jQuery.post(
			ajaxurl,
			{
				action: Better_Links.ajaxAction,
				link: {
					name: self.newName(),
					slug: self.newSlug(),
					url: self.newUrl()
				},
				perform: 'create'
			},
			function(data, status) {
				if(data.error) {
					self.createErrorMessage(data.error_message);
				} else {
					self.newName('');
					self.newSlug('');
					self.newUrl('');

					self.setSearchStateActive();

					if(insertCreationAsRaw) {
						self.insertRaw({
							original: data.link
						});
					} else {
						self.insert({
							original: data.link
						});
					}
				}

				self.createActive(false);
			},
			'json'
		);
	}

	// Results retrieval
	function doSearch() {
		self.errorMessage('');
		self.lastSearchTerms(self.searchTerms());
		self.searchActive(true);

		jQuery.post(
			ajaxurl,
			{
				action: Better_Links.ajaxAction,
				orderby: self.orderBy(),
				page: self.page(),
				perform: 'search',
				searchTerms: self.searchTerms()
			},
			function(data, status) {
				self.searchResults.removeAll();

				if(data.error) {
					self.errorMessage(data.error_message);
				} else {
					self.page(parseInt(data.page));
					self.numberPages(parseInt(data.pages));

					for(var i in data.items) {
						self.searchResults.push(new BetterLinksResultVM(data.items[i]));
					}
				}

				self.searchActive(false);
			},
			'json'
		);
	}

	self.doSearch = doSearch;

	// Actions
	self.enterable = function(data, event) {
		var key = event.which ? event.which : event.keyCode;

		if(13 === key) {
			self.search();
			return false;
		} else {
			return true;
		}
	};

	self.nextPage = function() {
		if(self.canSearch() && self.hasNextPage()) {
			self.page(self.page() + 1);
			self.orderBy(self.lastOrderBy());
			self.searchTerms(self.lastSearchTerms());
			doSearch();
		}
	};

	self.previousPage = function() {
		if(self.canSearch() && self.hasPreviousPage()) {
			self.page(self.page() - 1);
			self.orderBy(self.lastOrderBy());
			self.searchTerms(self.lastSearchTerms());
			doSearch();
		}
	};

	var insertCreationAsRaw = false;

	self.createAndInsert = function() {
		if(self.canCreate()) {
			insertCreationAsRaw = false;
			doCreate();
		}
	};

	self.createAndInsertRaw = function() {
		if(self.canCreate()) {
			insertCreationAsRaw = true;
			doCreate();
		}
	};

	self.search = function() {
		if(self.canSearch()) {
			self.page(1);
			doSearch();
		}
	};

	self.insert = function(data) {
		var win = window.dialogArguments || opener || parent || top, html = '',
			selection = win.betterLinksSelection || 'LINK TEXT';


		self.insertShortcode('bl', { id: data.original.ID }, win.betterLinksSelection, true)
	};

	self.insertRaw = function(data) {
		var win = window.dialogArguments || opener || parent || top, html = '',
			selection = win.betterLinksSelection || 'LINK TEXT',
			link = data.original.link.replace('__ANCHOR__', selection);

		win.send_to_editor(link);
	};
};

var BetterLinksResultVM = function(result) {
	var self = this;

	self.original = result;
};

if(('undefined' !== typeof wp) && ('undefined' !== typeof wp.media) && ('undefined' !== typeof wp.media.view) && ('undefined' !== typeof wp.media.view.MediaFrame)) {
	var betterLinksCreateIframeStates = wp.media.view.MediaFrame.prototype.createIframeStates;
	wp.media.view.MediaFrame.prototype.createIframeStates = function() {
		betterLinksCreateIframeStates.apply(this, arguments);

		this.on('menu:render:default', function(view) {
			view.unset(Better_Links.stateName);
		}, this);
	};
}