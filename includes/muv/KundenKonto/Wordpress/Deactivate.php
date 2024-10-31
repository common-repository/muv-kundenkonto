<?php

namespace muv\KundenKonto\Wordpress;


defined( 'ABSPATH' ) OR exit;


class Deactivate {

	
	public static function deactivate($netzwerkweit) {
		
		if ( is_multisite() && $netzwerkweit ) {
			$currentId = get_current_blog_id();
			
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::_deactivate();
			}
			switch_to_blog( $currentId );
		} else {
			
			self::_deactivate();
		}
	}

	
	private static function _deactivate(){
		
		wp_clear_scheduled_hook( 'muv-kk-cron-delete-accounts' );

		
		
	}
}
