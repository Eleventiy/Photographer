(function($){
	
	$(document).on( 'click', '.pexeto-notice .notice-dismiss', function() {
		
		$.ajax({
			url: ajaxurl,
			data: {
				action: 'pexeto_mark_notice_as_dismissed',
				notice_id: $(this).parent('.pexeto-notice').data('notice_id')
			}
		});

	});
	
})(jQuery);