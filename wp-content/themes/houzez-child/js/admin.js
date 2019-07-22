/*
 Theme Name: Houzez Child Theme
 Description: Houzez Child Theme
 Version: 1.0
 */

jQuery(document).ready(function() {
    jQuery('.payment_option').closest('.rwmb-row').addClass('payment');

    for (var i = 1; i < 5; i++) {
    	if (jQuery('#fave_payment_option' + i).val() != '') {
            if (i > 1)
                jQuery('#fave_billing_unit').closest('.rwmb-column').hide();

    		jQuery('#fave_payment_option' + i).closest('.rwmb-row').addClass('selected');
    		jQuery('#fave_billing_time_unit').find('option[value=option' + i + ']').hide();
    	}
    }

    jQuery('#fave_billing_unit_add').click(function() {
    	var option = jQuery('#fave_billing_time_unit').val();

    	if (option == '') {
    		jQuery('#fave_billing_time_unit').css('border', '1px solid #ff0000');
    	} else {
            if (option != 'option1')
                jQuery('#fave_billing_unit').closest('.rwmb-column').hide();

    		jQuery('#fave_billing_time_unit').css('border', '1px solid #ddd');
    		jQuery('#fave_payment_' + option).closest('.payment').show();
    		jQuery('#fave_billing_time_unit').val('');
    		jQuery('#fave_billing_time_unit').find('option[value=' + option + ']').hide();
    	}
    });

    jQuery('.payment_option button').click(function() {
    	var len = jQuery(this).attr('id').length;
	    var option = 'option' + jQuery(this).attr('id').substring(len - 1, len );

        jQuery(this).closest('.payment').hide();
        jQuery('#fave_billing_time_unit').find('option[value=' + option + ']').show();

    	if (jQuery(this).closest('.payment').hasClass('selected')) {
    		var ajaxurl = houzez_admin_vars.ajaxurl;
	    	var postID = jQuery('#post_ID').val();
	    	var metaKey = jQuery(this).closest('.rwmb-column').prev().prev().find('input').attr('id');

	    	jQuery.ajax({
	            type: 'POST',
	            url: ajaxurl,
	            dataType: 'JSON',
	            data: {
	                'action' : 'houzez_remove_payment_option',
	                'postID' : postID,
	                'metaKey' : metaKey
	            },
	            success: function(data) {
	            }
	        });
    	}

        jQuery(this).closest('.payment').removeClass('selected');
        
        var disArr = [];
        jQuery('.payment').each(function() {
            disArr.push(jQuery(this).css('display'));
        });

        if (disArr[1] == 'none' && disArr[2] == 'none' && disArr[3] == 'none')
            jQuery('#fave_billing_unit').closest('.rwmb-column').show();
    });
});