<?php

namespace muv\KundenKonto\Admin;


defined( 'ABSPATH' ) OR exit;


class MultiSite {

	
	public static function hinweis() {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sie haben nicht das Recht, diese Seite zu sehen!', 'muv-kundenkonto' ) );
		};


		if ( MUV_KK_NETWORK_ACTIVATED && ( get_current_blog_id() != 1 ) ) {
			
		} else {
			wp_die( __( 'Sie haben nicht das Recht, diese Seite zu sehen!', 'muv-kundenkonto' ) );
		}

				echo '<div class="wrap muv muv-kundenkonto">';

		echo '<h1><i class="fa fa-fw fa-cog"></i> ';
		_e( 'Einstellungen', 'muv-kundenkonto' );
		echo '</h1>';

		$primSite = get_site( 1 );

		echo '<div class="alert alert-info">';
		printf(
			__( 'Das Plugin muv Kundenkonto wurde netzwerkweit aktiviert.<br>' .
			    'Daraus folgt, dass alle Websites auf die selben Kundendaten und die selben Einstellungen zurück greifen.<br><br>' .
			    'Diese können in der ersten, primäten Site "%s" geändert werden.', 'muv-kundenkonto' ),
			esc_html( $primSite->blogname )
		);
		echo '</div>';

		echo '</div>'; 	}
}
