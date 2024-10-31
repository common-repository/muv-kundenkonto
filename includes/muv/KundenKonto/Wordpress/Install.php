<?php

namespace muv\KundenKonto\Wordpress;

use muv\KundenKonto\Classes\DBTables;


defined( 'ABSPATH' ) OR exit;


class Install {

	
	public static function install( $netzwerkweit ) {
		
		if ( is_multisite() && $netzwerkweit ) {
			$currentId = get_current_blog_id();
			
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::_install();
			}
			switch_to_blog( $currentId );
		} else {
			
			self::_install();
		}
	}

	
	public static function newBlog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		if ( is_plugin_active_for_network( MUV_KK_BASE ) ) {
			
			$currentId = get_current_blog_id();
			switch_to_blog( $blog_id );
			self::_install();
			switch_to_blog( $currentId );
		}
	}

	
	private static function _install() {
		
		if ( ! wp_next_scheduled( 'muv-kk-cron-delete-accounts' ) ) {
			wp_schedule_event( strtotime( '02:00 tomorrow' ), 'daily', 'muv-kk-cron-delete-accounts' );
		}
		
		
		
		self::updateTables();
	}


	
	private static function updateTables() {
		
		if ( is_multisite() &&
		     is_plugin_active_for_network( MUV_KK_BASE ) &&
		     ( get_current_blog_id() != 1 )
		) {
			return; 		}

		
		global $wpdb;

		
		$tables = DBTables::getTables();

		
		$sql = "CREATE TABLE IF NOT EXISTS " . $tables['intversion'] . " ( 
				`identifier` VARCHAR(50) NOT NULL,
				`version` INT(10) UNSIGNED NOT NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`identifier`, `version`)
				)";
		$wpdb->query( $sql );

		$updateRoot = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/update/';

		
		$sollVersion = count( glob( $updateRoot . '/*.inc.php' ) );

		
		$sql = $wpdb->prepare( "SELECT max(version) FROM " . $tables['intversion'] . " WHERE identifier = %s",
			MUV_KK_UPATE_IDENTIFIER );

		$istVersionSql = $wpdb->get_var( $sql );
		$istVersion    = ( empty( $istVersionSql ) ) ? 0 : (integer) $istVersionSql;

		
		if ( $istVersion < $sollVersion ) {
			
			for ( $i = $istVersion; $i < $sollVersion; $i ++ ) {
				
				$updateFile = $updateRoot . str_pad( $i + 1, 4, '0', STR_PAD_LEFT ) . '.inc.php';

				
				if ( file_exists( $updateFile ) ) {
					include $updateFile;
				}
				
				$sql = $wpdb->prepare( "INSERT INTO " . $tables['intversion'] .
				                       " (`identifier`, `version`, `created_at`) VALUES (%s, %d, NOW())", MUV_KK_UPATE_IDENTIFIER, $i + 1 );

				$wpdb->query( $sql );
			}
		}
	}
}
