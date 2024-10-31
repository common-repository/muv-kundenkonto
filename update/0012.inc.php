<?php


defined( 'ABSPATH' ) OR exit;




$sql = "ALTER TABLE " . $tables['kunden'] . "
	MODIFY COLUMN `kundennr`  varchar(150) NULL DEFAULT NULL AFTER `nachname`,
ADD COLUMN `synchronisation`  bit NULL DEFAULT 0 COMMENT 'aktiviert / deaktiviert die Synchronisation der Kundendokumente' AFTER `kundennr`;";
$wpdb->query( $sql );
