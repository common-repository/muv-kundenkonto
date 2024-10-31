<?php

namespace muv\KundenKonto\Classes;


defined( 'ABSPATH' ) OR exit;


class DBTables {

	
	public static function getTables() {
		global $wpdb;

		
		$blogId = null; 
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( MUV_KK_NETWORK_ACTIVATED) {
			$blogId = 1; 		};


		
		$tables['intversion'] = $wpdb->get_blog_prefix($blogId) . 'muv_sh_intversion';

		
		$tables['kunden'] = $wpdb->get_blog_prefix($blogId) . 'muv_kk_kunden';

		
		$tables['kundendaten'] = $wpdb->get_blog_prefix($blogId) . 'muv_kk_kundendaten';

		
		$tables['kundendaten_ext'] = $wpdb->get_blog_prefix($blogId) . 'muv_kk_kundendaten_ext';

		return $tables;
	}

}
