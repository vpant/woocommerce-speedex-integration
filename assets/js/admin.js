jQuery( document ).ready( function( $ ) {
	
	// tooltip
	$(function(){
		if ($('#woocommerce-speedex-cep-settings-page').length > 0)
		{
			$( '.tips, .help_tip, .woocommerce-help-tip' ).tipTip( {
				'attribute': 'data-tip',
				'fadeIn': 50,
				'fadeOut': 50,
				'delay': 200
			} );
		}
	});
	
	function disableButtons() {
		$('.download_voucher').click(false);
		$('.cancel_voucher').click(false);
		$('.manually_create_voucher').click(false);
	}
	
	function getFormatedTodayDate()
	{
		var today = new Date();
		var dd = today.getDate();
		var mm = today.getMonth() + 1; 
		var yyyy = today.getFullYear();
		if( dd < 10 ){
			dd = '0' + dd;
		} 
		if( mm < 10 ){
			mm = '0' + mm;
		} 
		return dd + '-' + mm + '-' + yyyy;
	}
	
	function b64toBlob(b64Data, contentType, sliceSize) {
		contentType = contentType || '';
		sliceSize = sliceSize || 512;

		var byteCharacters = atob(b64Data);
		var byteArrays = [];

		for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
			var slice = byteCharacters.slice(offset, offset + sliceSize);

			var byteNumbers = new Array(slice.length);
			for (var i = 0; i < slice.length; i++) {
				byteNumbers[i] = slice.charCodeAt(i);
			}
			var byteArray = new Uint8Array(byteNumbers);
			byteArrays.push(byteArray);
		}
		return new Blob(byteArrays, {type: contentType});
}
	
	$( '.wc_speedex_cep_voucher_box' ).on( 'click', 'input.cancel_voucher', function( e ) {	
		e.preventDefault();
		$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("show");
		var currentElementClicked = $(this);
		var i18n = wc_speedex_cep_local;
		var data = {
			action: 'wc_speedex_cep_cancel_bol',
			ajaxAdminCancelBOLNonce: wc_speedex_cep_local.ajaxAdminCancelBOLNonce,
			voucher_code: $(this).data("voucher-code"),
			post_id_number: $("#post_ID").val()
		};

		$.ajax({
			url: wc_speedex_cep_local.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: function (response){
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("hide");			
				disableButtons();
				if ( response.status == 'success' ) {
					if ($(currentElementClicked).closest('div').has('.speedex_cep_cancel_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_success_message speedex_cep_cancel_voucher_success_message" style="display:block; ">' + response.message + '</label>'));
					}
				}
				else {
					if ($(currentElementClicked).closest('div').has('.speedex_cep_cancel_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_cancel_voucher_error_message" style="display:block; ">' + response.message + '</label>'));
					}
				}

				setTimeout(function(){ window.location.reload(); }, 3000);
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("hide");
				if ($(currentElementClicked).closest('div').has('.speedex_cep_cancel_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_cancel_voucher_error_message" style="display:block; ">' + wc_speedex_cep_local.ajaxErrorMessage + '</label>'));
				}
			}
		});
		
	});
	
	$('#bol-creation-advanced-options').click(function () {
		if ($(this).is(':checked')) {
			$('#advanced-bol-creation-comments').show();
		} else {
			$('#advanced-bol-creation-comments').hide();
		}
	});
	
	$( '#speedex_order_fields' ).on( 'click', 'input.manually_create_voucher', function( e ) {	
		e.preventDefault();
		$( '#wc_speedex_cep_vouchers' ).LoadingOverlay('show');
		var currentElementClicked = $(this);
		var i18n = wc_speedex_cep_local;
		
		var voucherComments = '';
		if($('#bol-creation-advanced-options').is(':checked')) {
			voucherComments = $('#advanced-bol-creation-comments').val();
		}
		
		var data = {
			action: 'wc_speedex_cep_manually_create_bol',
			ajaxAdminManuallyCreateBolNonce: wc_speedex_cep_local.ajaxAdminManuallyCreateBolNonce,
			voucher_comments: voucherComments,
			post_id_number: $('#post_ID').val()
		};

		$.ajax({
			url: wc_speedex_cep_local.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: function (response){
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay('hide');				
				if ( response.status == 'success' ) {
					if ($(currentElementClicked).closest('div').has('.speedex_cep_create_voucher_success_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_success_message speedex_cep_create_voucher_success_message" style="display:block; ">' + response.message + '</label>'));
					}
					disableButtons();
					setTimeout(function(){ window.location.reload(); }, 3000);
				}
				else {
					if ($(currentElementClicked).closest('div').has('.speedex_cep_create_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_create_voucher_error_message" style="display:block; ">' + response.message + '</label>'));
					}
				}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay('hide');
				if ($(currentElementClicked).closest('div').has('.speedex_cep_create_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_create_voucher_error_message" style="display:block; ">' + wc_speedex_cep_local.ajaxErrorMessage + '</label>'));
				}
			}
		});
		
	});
	
	$( '.wc_speedex_cep_voucher_box' ).on( 'click', 'input.download_voucher', function( e ) {	
		e.preventDefault();
		$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("show");
		var currentElementClicked = $(this);
		var i18n = wc_speedex_cep_local;
		var voucher_id = $(this).data("voucher-code");
		var data = {
			action: 'wc_speedex_cep_get_bol_pdf',
			ajaxAdminGetBOLPdfNonce: wc_speedex_cep_local.ajaxAdminGetBOLPdfNonce,
			voucher_code: voucher_id,
			post_id_number: $("#post_ID").val()
		};

		$.ajax({
			url: wc_speedex_cep_local.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: function (response){
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("hide");
				if ( response.status == 'success' ) {
					var base64array = response.base64array;
					if ( typeof base64array !== 'undefined' && base64array.length > 0 )
					{
						var index;
						for (index = 0; index < base64array.length; ++index) {
							var blob = b64toBlob( base64array[index], 'application/pdf' );
							if( blob != '' )
							{
								var link=document.createElement('a');
								link.href = window.URL.createObjectURL(blob);
								link.download = voucher_id + "-voucher-" + getFormatedTodayDate() + ".pdf";
								link.click();
								
							}else {
								if ($(currentElementClicked).closest('div').has('.speedex_cep_download_voucher_error_message').length === 0) {
									$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_download_voucher_error_message" style="display:block; ">' + wc_speedex_cep_local.invalidPdfError + '</label>'));
								}
							}
						}
					}
				}
				else {
					if ($(currentElementClicked).closest('div').has('.speedex_cep_download_voucher_error_message').length === 0) {
						$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_download_voucher_error_message" style="display:block; ">' + response.message + '</label>'));
					}
				}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				$( '#wc_speedex_cep_vouchers' ).LoadingOverlay("hide");
				if ($(currentElementClicked).closest('div').has('.speedex_cep_download_voucher_error_message').length === 0) {
					$(currentElementClicked).closest('div').append($('<label type="text" class="speedex_cep_error_message speedex_cep_download_voucher_error_message" style="display:block; ">' + wc_speedex_cep_local.ajaxErrorMessage + '</label>'));
				}
			}
		});
	});
	
	$( '#wp-admin-bar-speedex-cep' ).on( 'click', '#wp-admin-bar-speedex-cep-bol-summary-pdf', function( e ) {	
		e.preventDefault();
		$.LoadingOverlay("show");
		var currentElementClicked = $(this);
		var i18n = wc_speedex_cep_local;
		
		var data = {
			action: 'wc_speedex_cep_get_bol_summary_pdf',
			ajaxAdminGetBolSummaryPdfNonce: wc_speedex_cep_local.ajaxAdminGetBolSummaryPdfNonce
		};

		$.ajax({
			url: wc_speedex_cep_local.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: function (response){
				$.LoadingOverlay("hide");
				if ( response.status == 'success' ) {
					var blob = b64toBlob(response.base64string, 'application/pdf' );
					if(blob != '' )
					{
						var link = document.createElement('a');
						link.href = window.URL.createObjectURL(blob);
						link.download = "voucher-summary-" + getFormatedTodayDate() + ".pdf";
						link.click();	
					}else {
						if ($('#wpbody-content').has('.speedex-cep-download-failed-error').length === 0) {
							$('#wpbody-content div:first').before('<div class="error is-dismissible speedex-cep-download-failed-error" ><p>' + wc_speedex_cep_local.ajaxGetBolSummaryPdfError + '</p></div>');
						}
						window.scrollTo( 0, 0 );
					}
				}
				else {
					$.LoadingOverlay( "hide" );
					if ($('#wpbody-content').has('.speedex-cep-download-failed-error').length === 0) {
						$('#wpbody-content div:first').before('<div class="error is-dismissible speedex-cep-download-failed-error" ><p>' + response.message + '</p></div>');
					}
					window.scrollTo(0, 0);
				}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				$.LoadingOverlay( "hide" );
					if ( $('#wpbody-content').has('.speedex-cep-download-failed-error').length === 0 ) {
						$('#wpbody-content div:first').before('<div class="error is-dismissible speedex-cep-download-failed-error" ><p>' + wc_speedex_cep_local.ajaxErrorMessage + '</p></div>');
					}
			}
		});
	});
});
