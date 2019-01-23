<?php
/* 
  Plugin Name: Speedex courier integration for Wocommerce
  Plugin URI: ''
  Description: Provides interface with Speedex web service API for Woocommerce
  Version: 1.0.0
  Author: 'Vasilis Pantelis'
  Author URI: ''
  License:           GPL-3.0+
  License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */
if (!defined('ABSPATH')){
    exit;
}

if ( ! class_exists ( 'WC_Speedex_CEP' ) ) {

	class WC_Speedex_CEP {

		function __construct() {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			if ( class_exists( 'WooCommerce' ) ) {

				if ( is_admin() ) {
					include_once( 'includes/class-woocommerce-speedex-cep-speedex-interface.php' );
					include_once( 'includes/class-woocommerce-speedex-cep-settings.php' );
				}

				//include_once( 'includes/class-wc-additional-variation-images-frontend.php' );
			} else {

				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

			}
			return true;
		}
		
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'woocommerce_speedex_cep_plugin_locale', get_locale(), 'woocommerce-speedex-cep' );

			load_textdomain( 'woocommerce-speedex-cep', trailingslashit( WP_LANG_DIR ) . 'woocommerce-speedex-cep/woocommerce-speedex-cep' . '-' . $locale . '.mo' );

			load_plugin_textdomain( 'woocommerce-speedex-cep', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			return true;
			
		}
		
		public function woocommerce_missing_notice() {
			/* translators: 1: html link for downloading WC */
			echo '<div class="error"><p>' . sprintf( __( 'Woocommerce Speedex CEP Plugin requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-speedex-cep' ), '<a href="http://www.woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</p></div>';
		}

	}
	add_action( 'plugins_loaded', 'wc_speedex_cep_init', 0 );
	function wc_speedex_cep_init() {
		new WC_Speedex_CEP();
		return true;
	}
}//endif;