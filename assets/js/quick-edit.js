(function($) {

	// TODO: Get column and field names via localized variables.

	// Copy of the WP inline edit post function.
	var $wp_inline_edit = inlineEditPost.edit;

	// Overwrite the function.
	inlineEditPost.edit = function( id ) {

		// Invoke the original function.
		$wp_inline_edit.apply( this, arguments );

		var $post_id = 0;
		if ( typeof( id ) == 'object' ) {
			$post_id = parseInt( this.getId( id ) );
		}

		if ( $post_id > 0 ) {
			// Define the data sources.
			var $edit_row = $( '#edit-' + $post_id );
			var $post_row = $( '#post-' + $post_id );
			var $data_row = $( '#inline_' + $post_id );

			// Get the data.
			var $silent_publish = !! $('.column-date .silent_publish', $post_row ).size();

			// Populate the data.
			$( ':input[name="silent_publish"]', $edit_row ).prop( 'checked', $silent_publish );

			// Determine if checkbox should be disabled.
			var $post_status = $('._status', $data_row ).text();
			var $disabled = '';
			if ( $post_status === 'publish' ) {
				$disabled = 'disabled';
			}
			$( ':input[name="silent_publish"]', $edit_row ).prop( 'disabled', $disabled );

			// Determine if checkbox should be visible.
			var $is_published = $post_status === 'publish';

			if ( $is_published ) {
				$( '.c2c-silent-publish', $edit_row ).hide();
			} else {
				$( '.c2c-silent-publish', $edit_row ).show();
			}

			// Determine if message indicating post was silent published should
			// be shown.
			if ( $is_published && $silent_publish ) {
				$( '.c2c-silent-published', $edit_row ).show();
			} else {
				$( '.c2c-silent-published', $edit_row ).hide();
			}
		}
	};

})(jQuery);
