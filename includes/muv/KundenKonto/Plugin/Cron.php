<?php

namespace muv\KundenKonto\Plugin;

use muv\KundenKonto\Classes\DBTables;
use muv\KundenKonto\Classes\Tools;


defined( 'ABSPATH' ) OR exit;


class Cron {

	
	public static function init() {
		add_action( 'muv-kk-cron-delete-accounts', array( self::class, 'deleteAccounts' ) );
	}

	
	public static function deleteAccounts() {
		
		$optionen = Tools::get_option( 'muv-kk-zugang-loeschen', array() );
		$checked  = (bool) $optionen['check'];
		$tage     = abs( (int) $optionen['tage'] );

		
		if ( ! $checked ) {
			return;
		}

				$erstelltBis = date( 'Y-m-d H:i:s', strtotime( '-' . $tage . ' days', time() ) );

		
		global $wpdb;
		$tables = DBTables::getTables();
		$sql = $wpdb->prepare( "DELETE FROM " . $tables['kunden'] . " WHERE erstellt_am_utc < %s AND email_verifiziert_am_utc IS NULL", $erstelltBis);
		$wpdb->query( $sql, $erstelltBis );
	}
}
