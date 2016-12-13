jQuery(document).ready( function(){
		

		if(jQuery("#greenmidgetnoncescriptnoJS").length!=0){

			var data = {
				'action': 'greenmidget_comment_nonce',
				'nonce': greenmidgetnoncescript.nonce
			};

			jQuery.post(greenmidgetnoncescript.ajaxURL, data, function(response) {
				jQuery("#greenmidgetnoncescriptnoJS").attr("name","greenmidgetnoncescriptAJAX");
				jQuery("#greenmidgetnoncescriptnoJS").attr("value",response);				
			});

		}		

	}
);