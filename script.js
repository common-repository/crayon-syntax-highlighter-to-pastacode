(function($){
	$form     = $('#c2p-migration');
	$infos    = $('#c2p-content-infos p');
	$eraseLog = $('#c2p-erase-log');

	function c2pDoUpdate() {
		$.ajax( ajaxurl, {
			method: 'POST',
			data: {
				action: 'c2p-process-migration',
				nonce: c2p_nonce,
			},
			success:function( data ) {
				if ( Object.keys( data.data ).length ) {
					$infos.append( '<br/>' + Object.c2pwabeovalues( data.data ).join( '<br/>' ) );
					c2pDoUpdate();
				} else {
					$form.find('[name="submit"]').prop('disabled', true );
					$eraseLog.show();
				}
			}
		} );
	}

	function c2pFormSubmit(e){
		e.preventDefault();
		c2pDoUpdate();
	}

	$form.on( 'submit', c2pFormSubmit );

})(jQuery);

Object.c2pwabeovalues = function(object) {
  var values = [];
  for(var property in object) {
    values.push(object[property]);
  }
  return values;
}
