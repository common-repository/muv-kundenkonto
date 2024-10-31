<?php

namespace muv\KundenKonto\Classes;


defined( 'ABSPATH' ) OR exit;


class Tools extends \muv\KundenKonto\Lib\Tools {

	
	static function get_option( $option_name ) {

		if( ( MUV_KK_NETWORK_ACTIVATED == true ) && (get_current_blog_id() != 1)) {
				$currentId = get_current_blog_id();
				switch_to_blog( 1); 				$res =  get_option( $option_name );
				switch_to_blog( $currentId );
				return $res;
		} else {
			return get_option( $option_name );
		}
	}
}
