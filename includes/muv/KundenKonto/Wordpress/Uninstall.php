<?php

namespace muv\KundenKonto\Wordpress;

use muv\KundenKonto\Classes\DBTables;



defined( 'ABSPATH' ) OR exit;


class Uninstall {

	
	public static function uninstall() {
		
		if ( is_multisite()) {
			$currentId = get_current_blog_id();
			
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::_uninstall();
			}
			switch_to_blog( $currentId );
		} else {
			
			self::_uninstall();
		}
	}

	
	public static function deleteBlog($blog_id) {
		
		if ( is_plugin_active_for_network( MUV_KK_BASE ) ) {
			$currentId = get_current_blog_id();
			switch_to_blog( $blog_id );
			self::_uninstall();
			switch_to_blog( $currentId );
		}
	}

	
	private static function _uninstall(){
		
		self::dropTables();

		
		self::dropOptions();

	}


	
	public static function init() {
		
		self::dropTables();
	}

	
	private static function dropTables() {
		
		if ( is_multisite() &&
		     is_plugin_active_for_network( MUV_KK_BASE ) &&
		     ( get_current_blog_id() != 1 )
		) {
			return; 		}

		
		global $wpdb;

		
		$tables = DBTables::getTables();

		
		foreach ( $tables as $table ) {
			if ( $table !== $tables['intversion'] ) {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
			}
		}

		
		$tblGefunden = $wpdb->get_row( "SHOW TABLES LIKE '" . $tables['intversion'] . "'" );
		if ( ! empty( $tblGefunden ) ) {
			
			$sql = $wpdb->prepare( "DELETE FROM " . $tables['intversion'] . " WHERE `identifier` = %s", MUV_KK_UPATE_IDENTIFIER );
			$wpdb->query( $sql );

			

			$rest = $wpdb->get_var( "SELECT COUNT(*) FROM " . $tables['intversion'] );
			if ( empty( $rest ) ) {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $tables['intversion'] );
			}
		}
	}

	
	private static function dropOptions() {
		global $wpdb;
		
		$wpdb->query('DELETE FROM ' . $wpdb->options  . " WHERE option_name like 'muv-kk-%'");
	}


}
