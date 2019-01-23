<?php
if (!defined('ABSPATH'))
    exit;

class WC_Speedex_CEP_Admin {
	private static $_this;
	
	public function __construct() {
		self::$_this = $this;
		
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

		add_action( 'wp_ajax_wc_speedex_cep_cancel_bol', array( $this, 'cancelBol' ) );

		add_action( 'wp_ajax_wc_speedex_cep_get_bol_pdf', array( $this, 'getBolPdf' ) );
		
		add_action( 'wp_ajax_wc_speedex_cep_manually_create_bol', array( $this, 'manuallyCreateBol' ) );

		add_action( 'wp_ajax_wc_speedex_cep_get_bol_summary_pdf', array( $this, 'getBolSummaryPdf' ) );
		
		add_action( 'add_meta_boxes', array( $this, 'speedex_add_meta_boxes' ) );
		
	
		$sel_statuses = get_option( 'order-statuses' );
		if ( !empty( $sel_statuses ) ) {
			foreach ( $sel_statuses as $status ){
				add_action( 'woocommerce_order_status_'.$status, array( $this, 'autoCreateBol' ) );
			}
		}

	}
	
	function load_admin_scripts()
	{
		$suffix = '';

		wp_enqueue_script( 'wc_speedex_cep_loadingoverlay_lib_js', plugins_url( 'assets/libs/loadingoverlay/loadingoverlay.min.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_script( 'wc_speedex_cep_goodpopup_lib_js', plugins_url( 'assets/libs/jquery.goodpopup/js/script.min.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_script( 'wc_speedex_cep_admin_order_page_script', plugins_url( 'assets/js/admin' . $suffix . '.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
				
		$localized_vars = array(
			'ajaxurl'                   		=> admin_url( 'admin-ajax.php' ),
			'ajaxAdminCancelBOLNonce'   		=> wp_create_nonce( '_wc_speedex_cep_cancel_bol_nonce' ),
			'ajaxAdminGetBOLPdfNonce'  			=> wp_create_nonce( '_wc_speedex_cep_get_bol_pdf' ),
			'ajaxAdminManuallyCreateBolNonce'   => wp_create_nonce( '_wc_speedex_cep_manually_create_bol' ),
			'ajaxAdminGetBolSummaryPdfNonce'   	=> wp_create_nonce( '_wc_speedex_cep_get_bol_summary_pdf' ),
			'invalidPdfError'					=> __( 'Download failed. An error occured.', 'woocommerce-speedex-cep' ),
			'ajaxErrorMessage'					=> __( 'An network error occured.', 'woocommerce-speedex-cep' ),
			'ajaxGetBolSummaryPdfError'			=> __( 'Voucher list failed to download because the pdf is invalid.','woocommerce-speedex-cep' ),
		);
		
		wp_localize_script( 'wc_speedex_cep_admin_order_page_script', 'wc_speedex_cep_local', $localized_vars );
		wp_enqueue_style( 'wc_speedex_cep_goodpopup_lib_css', plugins_url( 'assets/libs/jquery.goodpopup/css/style.min.css' , dirname( __FILE__ ) ) );
		wp_enqueue_style( 'wc_speedex_cep_admin_style', plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ) );
		
		return true;
	}
	
	function autoCreateBol( $order_id )
	{
		$speedex_post_meta = get_post_meta( $order_id, '_speedex_voucher_code', true );
		if ( empty( $speedex_post_meta ) ) {
			$this->createBol( $order_id );
		}
	}
	
	function getBolSummaryPdf( $beginDate = 0, $endDate = 0 )
	{
		$nonce = $_POST['ajaxAdminGetBolSummaryPdfNonce'];
		if ( ! wp_verify_nonce( $nonce, '_wc_speedex_cep_get_bol_summary_pdf' ) ) {
		     die ( 'error' );	
		}
		
		$options = array(
			'cache_wsdl' => WSDL_CACHE_NONE,
			'encoding' => 'UTF-8',
			'exceptions' => true,
		);
		
		$url = ( get_option( 'testmode' ) != 1 ) ? 'http://www.speedex.gr/accesspoint/accesspoint.asmx' : 'http://www.speedex.gr/AccessPointTest/AccessPoint.asmx';
		$soap_client = new SoapClient( $url."?WSDL", $options );
		
		$beginDate = empty( $beginDate ) ? mktime( 0, 0, 0, date( 'n' ), date( 'j' ) ) : $beginDate;
		$endDate = empty( $endDate ) ?  time() : $endDate; //(mktime(0, 0, 0, date('n'), date('j') + 1) - 1)
		
		try
		{	
			$session_id_response = $this->getSession( $soap_client );
			if ( $session_id_response['status'] === 'success' ) {
				$session_id = $session_id_response['session_id'];
			}
			else {
				echo json_encode( array(
					'status' => 'fail',
					'message' => $session_id_response['message'],
					'base64string' => ''
				));
				exit;
			}
			
			$get_bol_summary_pdf_response = $soap_client->GetBOLSummaryPdf( array( "sessionID" => $session_id, "beginDate" => $beginDate, "endDate" => $endDate));
			if ( $get_bol_summary_pdf_response->returnCode != 1 ) {
				$this->destroySession( $session_id, $soap_client );
				echo json_encode( array( 
					'status' => 'fail',
					'message' => sprintf( __( 'Voucher summary list pdf failed to download. Error Code: %s Error Message: %s', 'woocommerce-speedex-cep' ), $get_bol_summary_pdf_response->returnCode, $get_bol_summary_pdf_response->returnMessage ),
					'base64string' => ''
				));				
				exit;
			}else
			{
				$base64_pdf_string = $get_bol_summary_pdf_response->GetBOLSummaryPdfResult;
				
				echo json_encode( array( 
					'status' => 'success',
					'message' => '',
					'base64string' => base64_encode( $base64_pdf_string )
				));	
				exit;
			}
		}catch ( Exception $e ) {
			echo json_encode( array( 
					'status' => 'fail',
					'message' => $e->getMessage(),
					'base64string' => ''
				));	
			exit;
		}
		
	}
	
	function manuallyCreateBol()
	{
		$nonce = $_POST['ajaxAdminManuallyCreateBolNonce'];
		if ( ! wp_verify_nonce( $nonce, '_wc_speedex_cep_manually_create_bol' ) ) {
		     die ( 'error' );	
		}
		$create_bol_result = $this->createBol( $_POST['post_id_number'] );
		if ( $create_bol_result['status'] === 'success' ) {
			$response = array(
				'status' => 'success',
				'message' => $create_bol_result['message'].' '.__( 'The page will refresh shortly.', 'woocommerce-speedex-cep' )
			);
		}
		else { 
			$response = array(
				'status' => 'fail',
				'message' => $create_bol_result['message']
			);
		}	
		echo json_encode( $response );
		exit;
	}
	
	function getBolPdf()
	{
		$nonce = $_POST['ajaxAdminGetBOLPdfNonce'];
		if ( ! wp_verify_nonce( $nonce, '_wc_speedex_cep_get_bol_pdf' ) ) {
		     die ( 'error' );	
		}
		$options = array(
			'cache_wsdl' => WSDL_CACHE_NONE,
			'encoding' => 'UTF-8',
			'exceptions' => true,
		);
		$url = ( get_option( 'testmode' ) != 1 ) ? 'http://www.speedex.gr/accesspoint/accesspoint.asmx' : 'http://www.speedex.gr/AccessPointTest/AccessPoint.asmx';
		$voucher_code = $_POST[ 'voucher_code' ];
		$soap_client = new SoapClient( $url."?WSDL", $options );

		try
		{	
			$session_id_response = $this->getSession( $soap_client );
			if ( $session_id_response['status'] === 'success' ) {
				$session_id = $session_id_response['session_id'];
			}
			else {
				echo json_encode( array(
					'status' => 'fail',
					'message' => $session_id_response['message'],
					'base64array' => array()
				));
				exit;
			}

			$get_bol_pdf_response = $soap_client->GetBOLPdf( array( "sessionID" => $session_id, "voucherIDs" => array ($voucher_code ), "perVoucher" => true, "paperType" => 1)); // Option gia paper type?
			if ($get_bol_pdf_response->returnCode != 1) {
				$this->destroySession( $session_id, $soap_client );
				echo json_encode( array( 
					'status' => 'fail',
					'message' => sprintf( __( 'Voucher\'s pdf file failed to download. Error Code: %s Error Message: %s', 'woocommerce-speedex-cep' ), $get_bol_summary_pdf_response->returnCode, $get_bol_summary_pdf_response->returnMessage ),
					'base64array' => array()
				));
				exit;
			} else {
				$base64array = array();
				foreach( $get_bol_pdf_response->GetBOLPdfResult as $result )
				{
					$base64array[] = base64_encode( $result->pdf );
				}
				if ( empty( $base64array ) ) {
					echo json_encode( array( 
						'status' => 'fail',
						'message' => __( 'No vouchers found for this voucher code.', 'woocommerce-speedex-cep' ),
						'base64array' => array()
					));
					exit;
				}
				else {
					echo json_encode( array( 
						'status' => 'success',
						'message' => '',
						'base64array' => $base64array
					));
				exit;
				}
			}
		}catch ( Exception $e ) {
			echo json_encode( array( 
					'status' => 'fail',
					'message' => $e->getMessage(),
					'base64array' => array()
				));
			exit;
		}
	}

	function cancelBol()
	{
		$nonce = $_POST['ajaxAdminCancelBOLNonce'];
		if ( ! wp_verify_nonce( $nonce, '_wc_speedex_cep_cancel_bol_nonce' ) ) {
		     die ( 'error' );	
		}

		if ( !isset ( $_POST['post_id_number'] ) || !isset ( $_POST[ 'voucher_code' ] ) ) {
			echo json_encode( array(
				'status' => 'fail',
				'message' => __( 'Cannot cancel the voucher. An error occured.','wc_speedex_cep_cancel' )
			));
			exit;
		}
		
		$order = wc_get_order( $_POST['post_id_number'] );
		$voucher_code = $_POST['voucher_code'];
		if( $order ) {
			$options = array(
				'cache_wsdl'=>WSDL_CACHE_NONE,
				'encoding'=>'UTF-8',
				'exceptions'=>true,
			);
			$url = ( get_option('testmode') != 1 ) ? 'http://www.speedex.gr/accesspoint/accesspoint.asmx' : 'http://www.speedex.gr/AccessPointTest/AccessPoint.asmx';

			$soap_client = new SoapClient( $url."?WSDL", $options );
			try
			{	
				$session_id_response = $this->getSession( $soap_client );
				if ( $session_id_response['status'] === 'success' ) {
					$session_id = $session_id_response['session_id'];
				}
				else {
					return json_encode( array (
						'status' => 'fail',
						'message' => $session_id_response['message']
					) );
					exit;
				}
				
				$is_woocommerce_shipping_tracking_active = is_plugin_active( 'woocommerce-shipping-tracking/shipping-tracking.php' );
				if ( !( function_exists( 'save_shippings_info_metas' ) || class_exists( 'WCST_Order' ) ) ) {
					require_once( ABSPATH . '/wp-content/plugins/woocommerce-shipping-tracking/classes/com/WCST_Order.php' );
				}

				$cancel_bol_response = $soap_client->CancelBOL( array( "sessionID" => $session_id, "voucherID" => $voucher_code ) );
				if ( $cancel_bol_response->returnCode != 1 ) {
					if( $cancel_bol_response->returnCode == 603 ) {
						$response = array(
							'status' => 'fail',
							'message' => __( 'Speedex respond that shipment does not exist. This voucher got deleted from this order. The page will refresh shortly.','woocommerce-speedex-cep' )
						);
						
						delete_post_meta( $_POST['post_id_number'], '_speedex_voucher_code', $voucher_code );
						if( $is_woocommerce_shipping_tracking_active ) { 
							delete_post_meta( $_POST['post_id_number'], '_wcst_order_trackno', $voucher_code ); 
						}
					} else {
						throw new Exception( sprintf( __( 'Could not cancel Voucher. Error Code: %s Error Message: %s', 'woocommerce-speedex-cep' ), $cancel_bol_response->returnCode, $cancel_bol_response->returnMessage ) );
					}		
				} else {
					if( $is_woocommerce_shipping_tracking_active ) { 
						delete_post_meta($_POST['post_id_number'],'_wcst_order_trackno', $voucher_code ); 
					}
					delete_post_meta( $_POST['post_id_number'], '_speedex_voucher_code', $voucher_code );
					$response = array(
						'status' => 'success',
						'message' => __( 'This voucher is successfully deleted. The page will refresh shortly.', 'woocommerce-speedex-cep' )
					);
				}
			}catch ( Exception $e ) {
				$response = array(
					'status' => 'fail',
					'message' => $e->getMessage().__( 'The page will refresh shortly.', 'woocommerce-speedex-cep' )
				);
			}
			$this->destroySession( $session_id, $soap_client );
			echo json_encode( $response );
			exit;
		}
	}

	function splitCommentsToArray( $comments ) {
		
		$arrayWords = explode( ' ', $comments );

		$maxLineLength = 40;

		$currentLength = 0;
		$index = 1;

		if( !empty( $arrayWords ) ) {
			foreach ( $arrayWords as $word ) {
				$wordLength = strlen( $word ) + 1;

				if ( ( $currentLength + $wordLength ) <= $maxLineLength ) {
					$arrayOutput[ $index ] .= $word . ' ';

					$currentLength += $wordLength;
				} else {
					$index += 1;

					$currentLength = $wordLength;

					$arrayOutput[ $index ] = $word . ' ';
				}
			}
		} else {
			for( $i = 0; $i <= 2; $i++ ) {
				$splittedString = substr( $comments, $i * 40, ( $i + 1 ) * 40 );
				$arrayOutput[ $i + 1 ] = !empty( $splittedString ) ? $splittedString : '';
			}
		}
		
		return $arrayOutput;
	}
	
	function createBol( $order_id )
	{
		$order = wc_get_order( $order_id );
		if ( !$order ) { 
			return array( 
				'status' => 'fail',
				'message' => __( 'This post is not a valid shop order.', 'woocommerce-speedex-cep' )
			);
		}
		
		$sel_methods = get_option( 'methods' );
		foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
			$shipping_method_id = $shipping_item_obj->get_method_id(); // The method ID
			if( $shipping_method_id ){
				$shipping_method_id = ( strpos( $shipping_method_id, ':' ) === false ) ? $shipping_method_id : substr( $shipping_method_id, 0, strpos( $shipping_method_id, ':' ) );
				break;
			}	
		}
		
		if( !in_array($shipping_method_id, $sel_methods)){
			return array( 
				'status' => 'fail',
				'message' => __( 'Voucher creation is not available for the selected shipping method of this order.', 'woocommerce-speedex-cep' )
			);
		}
		$options = array(
			'cache_wsdl' => WSDL_CACHE_NONE,
			'encoding' => 'UTF-8',
			'exceptions' => true,
		);
		$url = get_option('testmode') != 1 ? 'http://www.speedex.gr/accesspoint/accesspoint.asmx' : 'http://www.speedex.gr/AccessPointTest/AccessPoint.asmx';
		$soap_client = new SoapClient( $url."?WSDL", $options );
		
		$comments = isset( $_POST['voucher_comments'] ) ? sanitize_text_field( trim( $_POST['voucher_comments'] ) ) : '';
		//$comments = $_POST['voucher_comments'] ;
		
		$bol_object_array = array(
			'EnterBranchId' => ( get_option( 'testmode' ) != 1 ) ? get_option( 'branch_id' ) : '1000;0101', // Mandatory
			'SND_Customer_Id' => ( get_option( 'testmode' ) != 1) ? get_option( 'customer_id' ) : 'PE145031', // Mandatory
			'Snd_agreement_id' => ( get_option( 'testmode' ) != 1 ) ? get_option( 'agreement_id' ) : '88499', // Mandatory
			'RCV_Name' => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(), // Mandatory
			'RCV_Addr1' => $order->get_shipping_address_1(), // Mandatory
			'RCV_Addr2' => $order->get_shipping_address_2(),
			'RCV_Zip_Code' => $order->get_shipping_postcode(), // Mandatory
			'RCV_City' => $order->get_shipping_city(), // Mandatory
			'RCV_Country' => $order->get_shipping_country(), // Mandatory
			'RCV_Tel1' => $order->get_billing_phone(), // Mandatory
			'Voucher_Weight' => 0, // Mandatory
			'Pod_Amount_Cash' => ( strcmp( $order->get_payment_method(), 'cod' ) == 0 ) ? $order->get_total() : 0,
			'Pod_Amount_Description' => 'M',
			'Security_Value' => 0,
			'Express_Delivery' => 0,
			'Saturday_Delivery' => 0,
			'Time_Limit' => 0,
			'Comments_2853_1' => sprintf( __( 'Order ID: %s','woocommerce-speedex-cep' ), $order->get_id() ),
			'Voucher_Volume' => 0,
			'PayCode_Flag' => 1,
			'Items' => 1,
			'Paratiriseis_2853_1' => !empty( mb_substr( $comments, 0, 65, 'UTF-8') ) ? mb_substr( $comments, 0, 65, 'UTF-8') : '',
			'Paratiriseis_2853_2' => !empty( mb_substr( $comments, 65, 65, 'UTF-8') ) ? mb_substr( $comments, 65, 65, 'UTF-8') : '',
			'Paratiriseis_2853_3' => !empty( mb_substr( $comments, 130, 65, 'UTF-8') ) ? mb_substr( $comments, 130, 65, 'UTF-8') : '',
			'_cust_Flag' => 0
		);
		
		if( empty( $bol_object_array['RCV_Name'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee Name', 'woocommerce-speedex-cep' );
		}
		if( empty( $bol_object_array['RCV_Addr1'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee Address', 'woocommerce-speedex-cep' );
		}
		if( empty( $bol_object_array['RCV_Zip_Code'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee Zip Code', 'woocommerce-speedex-cep' );
		}
		if( empty( $bol_object_array['RCV_City'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee City or Town', 'woocommerce-speedex-cep' );
		}
		if( empty( $bol_object_array['RCV_Country'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee Country', 'woocommerce-speedex-cep' );
		}
		if( empty( $bol_object_array['RCV_Tel1'] ) ) {
			$mandatory_fields_empty[] = __( 'Consignee Telephone', 'woocommerce-speedex-cep' );
		}
		
		if( !empty( $mandatory_fields_empty ) ) {
			$mandatory_fields_length = sizeof( $mandatory_fields_empty );
			return array(
				'status' => 'fail',
				'message' => sprintf( _n( '%s is a mandatory field.', '%s are mandatory fields', $mandatory_fields_length, 'woocommerce-speedex-cep' ), implode( ', ', $mandatory_fields_empty ) )
			);
		}
		
		try
		{
			$session_id_response = $this->getSession( $soap_client );
			if ( $session_id_response['status'] === 'success' ) {
				$session_id = $session_id_response['session_id'];
			}
			else {
				return array(
					'status' => 'fail',
					'message' => $session_id_response['message']
				);
			}
			
			$create_bol_response = $soap_client->CreateBOL( array( 'sessionID' => $session_id, 'inListPod' => array( $bol_object_array ), 'tableFlag' => 3 ) );
			if ( $create_bol_response->returnCode != 1 ) {
				$this->destroySession( $session_id, $soap_client );
				return array(
					'status' => 'fail',
					'message' => sprintf( __( 'Voucher creation failed. Error Code: %s Error Message: %s', 'woocommerce-speedex-cep' ), $create_bol_response->returnCode, $create_bol_response->returnMessage )
				);
			} else {
				$is_woocommerce_shipping_tracking_active = is_plugin_active( 'woocommerce-shipping-tracking/shipping-tracking.php' );
				if ( ! ( function_exists( 'save_shippings_info_metas' ) || class_exists( 'WCST_Order' ) ) ) {
					require_once( ABSPATH . '/wp-content/plugins/woocommerce-shipping-tracking/classes/com/WCST_Order.php' ); // Find better solution to find path
				}
				$wcst_order_model = $is_woocommerce_shipping_tracking_active ? new WCST_Order() : null;
				$plural = 0;
				foreach( $create_bol_response->outListPod as $response )
				{
					$plural++;
					if( count( $response ) > 1 )
					{
						foreach( $response as $value )
						{
							$order->add_order_note( sprintf ( __( 'The newly created voucher code is: %s', 'woocommerce-speedex-cep' ), $value->voucher_code ));
						}
					}
					else{
						if ( $is_woocommerce_shipping_tracking_active ) {
							$data_to_save['_wcst_order_trackno'] = $response->voucher_code;
							$data_to_save['_wcst_custom_text']='';
							$data_to_save['_wcst_order_dispatch_date'] = current_time( 'Y-m-d' );
							$data_to_save['_wcst_order_trackurl'] = '0';
							$data_to_save['_wcst_order_disable_email'] = 'no';
							$wcst_order_model->save_shippings_info_metas( $order_id, $data_to_save );
						}
						
						$order->add_meta_data( '_speedex_voucher_code', stripslashes( $response->voucher_code ) );
						$order->save();
					}
				}
				return array(
					'status' => 'success',
					'message' => _n( 'Voucher creation succeeded.', 'Vouchers creation succeeded.', $plural , 'woocommerce-speedex-cep' )
				);
			}
		}
		catch ( Exception $e)
		{
			return array(
				'status' => 'fail',
				'message' => $e->getMessage()
			);
		}
	}

	function getSession( $soap_client )
	{		
		$username = get_option ( 'username' );
		$password = get_option ( 'password' );
		if( !$username && !$password ) {
			return array (
				'status' => 'fail',
				'message' => sprintf ( __( 'Username or password are not valid. Please update them with valid ones in the <a href="%s">settings</a> page.', 'woocommerce-speedex-cep' ) , admin_url( 'admin.php?page=wc-speedex-cep-settings' ) ), // TO-DO settings link
				'session_id' => '',
			);
		}
		try { 
			$authentication_result = $soap_client->CreateSession( array( 'username' => $username, 'password' => $password ) );
			if ( $authentication_result->returnCode != 1 ) {
				if ( $authentication_result->returnCode == 100 ) {
					return array (
						'status' => 'fail',
						'message' => sprintf ( __( 'Username or password are not valid. Please update them with valid ones in the <a href="%s">settings</a> page.', 'woocommerce-speedex-cep' ), admin_url( 'admin.php?page=wc-speedex-cep-settings' ) ),
						'session_id' => '',
					);
				} else {
					return array(
						'status' => 'fail',
						'message' => sprintf( __( 'An error occured while requesting for session ID. Error Code: %s, Error Message: %s', 'woocommerce-speedex-cep' ), $authentication_result->returnCode, $authentication_result->returnMessage ),
						'session_id' => '',
					); 
				}
				
				
			}else
			{
				return array(
					'status' => 'success',
					'message' => __( 'Request for session id was successful', 'woocommerce-speedex-cep' ),
					'session_id' => $session_ID = $authentication_result->sessionId,
				); 
			}
		}
		catch( SoapFault $soap_fault ) {
			return array(
					'status' => 'fail',
					'message' => $soap_fault->getMessage(),
					'session_id' => '',
				); 
		}	
	}

	function destroySession( $session_id, $soap_client_obj )
	{
		try { 
			$authentication_result = $soap_client_obj->DestroySession( array( 'sessionID' => $session_id ) );
			if ( $authentication_result->returnCode != 1 ) {
				return false;
			}else{
				return true;
			}
		}
		catch( SoapFault $soap_fault ) {
			return false;
		}
	}


	function speedex_add_meta_boxes()
	{
		add_meta_box( 'speedex_order_fields', __( 'Speedex','woocommerce-speedex-cep' ), array( $this, 'speedex_voucher_code_management_widget' ), 'shop_order', 'side', 'core' );
	}


	function speedex_voucher_code_management_widget()
	{		
		global $post;
		$order_ob = wc_get_order($post->ID);
		$meta_fields_data = $order_ob->get_meta( '_speedex_voucher_code', false );
		
		$shipping_method_id = array();
		$sel_methods = get_option('methods');
		foreach( $order_ob->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
			$shipping_method_id = $shipping_item_obj->get_method_id(); // The method ID
			
			if( $shipping_method_id ){
				$shipping_method_id = ( strpos( $shipping_method_id, ':' ) === false ) ? $shipping_method_id : substr( $shipping_method_id, 0, strpos( $shipping_method_id, ':' ) );
				break;
			}	
		}
		
		if( !$sel_methods || !in_array( $shipping_method_id, $sel_methods )){
			_e( 'Voucher creation is not available for the selected shipping method of this order.', 'woocommerce-speedex-cep' );
		}
		else {
			if( empty( $meta_fields_data ) || !isset( $meta_fields_data ) ) {
				?><div id="wc_speedex_cep_vouchers">
					<ul class="totals">
						<li>
							<label  style="display:block; clear:both; font-weight:bold;"><?php _e( 'No vouchers exists for this order.','woocommerce-speedex-cep' ); ?></label>
						</li>
						<li>
							<input type="button" class="button generate-items manually_create_voucher" value="<?php _e( 'Create a New Voucher', 'woocommerce-speedex-cep' ); ?>"  name="manually_create_voucher" />
						</li>
						<?php $this->advancedBolCreationOptionsHTML(); ?>
					</ul>
				</div><?php		
			}
			else
			{
				?><div id="wc_speedex_cep_vouchers"> <?php
				foreach( $meta_fields_data as $meta_field )
				{
					$voucher_code = $meta_field->__get( "value" );
					?>
					<div class="wc_speedex_cep_voucher_box">
						<ul class="totals">
							<li>
								<label style="display:block; clear:both; font-weight:bold;"><?php echo sprintf( __( 'Voucher: %s', 'woocommerce-speedex-cep' ), $voucher_code ); ?></label>
							</li>
							<li>
								<input id="woocommerce-speedex-cep-cancel-voucher" type="button" class="button button-primary cancel_voucher" data-voucher-code="<?php echo $voucher_code; ?>" value="<?php _e( 'Cancel Voucher', 'woocommerce-speedex-cep' ); ?>" name="cancel_voucher" />
							</li>
							<li>
								<input id="woocommerce-speedex-cep-download-voucher" type="button" class="button generate-items download_voucher" data-voucher-code="<?php echo $voucher_code; ?>" value="<?php _e( 'Download Voucher', 'woocommerce-speedex-cep' ); ?>"  name="download_voucher" />
							</li>
						</ul>
					</div>
					<?php
				}?>
				<input id="woocommerce-speedex-cep-manually-create-voucher" type="button" class="button generate-items manually_create_voucher" style="margin-bottom: 6px;" value="<?php _e( 'Create a New Voucher', 'woocommerce-speedex-cep' ); ?>"  name="manually_create_voucher" />
				<?php $this->advancedBolCreationOptionsHTML(); ?>
				</div><?php
			}
		}
	}
	
	function advancedBolCreationOptionsHTML() {
		?>
		<input id="bol-creation-advanced-options" type="checkbox" name="bol-creation-advanced-options"/><label for="bol-creation-advanced-options"><?php _e( 'Add comments to the new voucher', 'woocommerce-speedex-cep' ); ?></label>
		<textarea id="advanced-bol-creation-comments" class="advanced-bol-creation-comments" name="advanced-bol-creation-comments" maxlength="195" style="width: 100%; margin-top: 10px; display:none;" placeholder="<?php _e( 'Add the comment you wish to be included in the voucher...', 'woocommerce-speedex-cep' ); ?>"></textarea>

		<?php
	}

}//end class

new WC_Speedex_CEP_Admin();