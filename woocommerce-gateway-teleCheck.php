<?php
/*
Plugin Name: TeleCheck - WooCommerce Gateway
Plugin URI: http://www.SynergyTop.com/
Description: Extends WooCommerce by Adding the SynergyTop Softlab Non-Face-to-Face (NFTF) API for TeleCheck Gateway.
Version: 1.0
Author: Jatin Nahar, LADC
Author URI: http://www.SynergyTop.com/
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'tem_teleCheck_init', 0 );
function tem_teleCheck_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-teleCheck.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'tem_add_teleCheck_gateway' );
	function tem_add_teleCheck_gateway( $methods ) {
		$methods[] = 'Tem_TeleCheck';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tem_teleCheck_action_links' );
function tem_teleCheck_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'tem-teleCheck' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}

