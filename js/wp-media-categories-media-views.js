( function( media, $ ){

	media.view.AttachmentFilters.Taxonomy = media.view.AttachmentFilters.extend({

		tagName:   'select',

		createFilters: function() {
			var filters = {};
			var that = this;

			_.each( that.options.terms || {}, function( term, term_id ) {
				filters[term_id] = {
					text: term,
					priority: term_id + 2
				};
				filters[term_id]['props'] = {};
				filters[term_id]['props'][that.options.taxonomy] = term_id;
			});

			filters.all = {
				text: that.options.listTitle,
				priority: 1
			};
			filters['all']['props'] = {};
			filters['all']['props'][that.options.taxonomy] = null;

			this.filters = filters;
		}
	});

	// no way to tap into here without overriding the default
	// AttachmentsBrowser and then just calling the createToolbar
	// method on the parent class within.
	var wpAttachmentsBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = media.view.AttachmentsBrowser.extend({

		createToolbar: function() {

			wpAttachmentsBrowser.prototype.createToolbar.apply(this,arguments);

			if( WP_Media_Categories.terms && this.options.filters ){
				this.toolbar.set( 'media-category-filter', new media.view.AttachmentFilters.Taxonomy({
					controller: this.controller,
					model: this.collection.props,
					priority: -50,
					taxonomy: 'media-category',
					terms: WP_Media_Categories.terms,
					listTitle: 'View All Media Categories',
					className: 'wp-media-categories-filter attachment-media-category-filter attachment-filters'
				}).render() );
			}
		}
	});

} )( wp.media, jQuery );