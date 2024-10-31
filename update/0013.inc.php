<?php


defined( 'ABSPATH' ) OR exit;




$sql = "ALTER TABLE ". $tables['kundendaten_ext'] ."
DROP PRIMARY KEY,
ADD UNIQUE INDEX `K__kid_key1_key2_daten` (`kunde_id`, `key1`, `key2`, `daten`(255)) ;";
$wpdb->query( $sql );
