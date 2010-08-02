var SiteIndex = {
	
	sections: [],
	progress: 0,
	config: {},
	
	init: function() {
		var self = this;
		
		// cache IDs of sections to re-index
		jQuery('span.to-re-index').each(function() {
			var span = jQuery(this);
			self.sections.push(span.attr('id').replace(/section\-/,''));
			span.removeClass('to-re-index');
		});
		
		// grab config from DOM
		var config_dom = jQuery('#search-index-config').text();
		if (config_dom) eval('this.config = ' + config_dom);
		
		// go, go, go
		this.indexNextSection();
		
	},
	
	indexNextSection: function() {
		if (this.sections.length == this.progress) return;
		this.indexSectionByPage(this.sections[this.progress], 1);
	},
	
	indexSectionByPage: function(section_id, page) {
		var self = this;
		var span = jQuery('#section-' + section_id).addClass('re-index');
		
		jQuery.ajax({
			url: self.config.extension_root_url + '/reindex/?section=' + section_id + '&page=' + page,
			success: function(xml) {
				var total_pages = parseInt(jQuery('pagination', xml).attr('total-pages'));
				var total_entries = jQuery('pagination', xml).attr('total-entries');
				
				span.show().text('Indexing page ' + page + ' of ' + total_pages);
				
				// there are more pages left
				if (total_pages > 0 && total_pages != page++) {
					setTimeout(function() {
						self.indexSectionByPage(section_id, page);
					}, (self.config['re-index-refresh-rate'] * 1000));
				}
				// proceed to next section
				else {					
					setTimeout(function() {
						span.text(total_entries + ' entries').removeClass('re-index');
						self.progress++;
						self.indexNextSection();
					}, (self.config['re-index-refresh-rate'] * 1000));
				}
			}
		});
	}
};

jQuery(document).ready(function() {
	SiteIndex.init();
});