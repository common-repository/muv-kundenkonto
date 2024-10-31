<?php

namespace muv\KundenKonto\Classes;



defined( 'ABSPATH' ) OR exit;


class Auth {

	
	public static function getLoggedInKunde( $pseudo = false ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		if ( $pseudo ) {
			$pseudo = Tools::get_option( 'muv-kk-erlaube-pseudo-login', DefaultSettings::ERLAUBE_PSEUDO_LOGIN );
		}

		
		if ( $pseudo == true ) {
			
			$pseudoLoginToken = filter_input( INPUT_COOKIE, 'muv-kk-plogin-token' );

			
			if ( empty( $pseudoLoginToken ) ) {
				return null; 			}

			
			$sql = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE pseudo_login_token = %s LIMIT 1", $pseudoLoginToken );
			$id  = $wpdb->get_var( $sql );

			if ( empty( $id ) ) {
				return null; 			}

			

			return new Kunde( $id );

		} else {

			
			$loginToken = filter_input( INPUT_COOKIE, 'muv-kk-login-token' );

			
			if ( empty( $loginToken ) ) {
				return null; 			}

			
			$sql = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE login_token = %s LIMIT 1", $loginToken );
			$id  = $wpdb->get_var( $sql );

			if ( empty( $id ) ) {
				return null; 			}

						$kunde = new Kunde( $id );

			
			$optionen = Tools::get_option( 'muv-kk-logout', array() );
			$idle     = abs( (int) $optionen['idle'] );
			$gesamt   = abs( (int) $optionen['gesamt'] );

			
			$timeLocal = current_time( 'mysql', false );
			$timeUtc   = current_time( 'mysql', true );

			
			$inaktiv = strtotime( $timeUtc ) - strtotime( $kunde->letzte_aktivitaet_utc );
			if ( $inaktiv > 60 * $idle ) { 				Flash::addMessage( __( 'Sie waren zu lange inaktiv. Ihre Sitzung wurde aus Sicherheitsgründen beendet.', 'muv-kundenkonto' ), 'warning' );
				
				self::logout( $kunde->id, false );

				return null; 			}

			
			$loginTime = strtotime( $timeUtc ) - strtotime( $kunde->letzter_login_utc );
			if ( $loginTime > 60 * 60 * $gesamt ) { 				Flash::addMessage( __( 'Sie waren zu lange angemeldet. Ihre Sitzung wurde aus Sicherheitsgründen beendet.', 'muv-kundenkonto' ), 'warning' );
				
				self::logout( $kunde->id, false );

				return null; 			}

			
			if ( $inaktiv > 30 ) { 				$kunde->letzte_aktivitaet     = $timeLocal;
				$kunde->letzte_aktivitaet_utc = $timeUtc;
			}

			

			return $kunde;
		}
	}

	
	public static function getLoggedInKundenId( $pseudo = false ) {
		$kunde = self::getLoggedInKunde( $pseudo );
		if ( empty( $kunde ) ) {
			return false;
		}
		$id = $kunde->id;
		if ( empty( $id ) ) {
			return false;
		}

		return $id;
	}

	
	public static function getKundeMitEMail( $email ) {
		global $wpdb;
		$tables = DBTables::getTables();

		$sql = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$id  = $wpdb->get_var( $sql );

		if ( empty( $id ) ) {
			return null; 		}

				return new Kunde( $id );
	}

	
	public static function getKundeMitKundenNummer( $kundennummer ) {
		global $wpdb;
		$tables = DBTables::getTables();

		$sql = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE kundennr = %s ORDER BY id LIMIT 1", $kundennummer );
		$id  = $wpdb->get_var( $sql );
		if ( empty( $id ) ) {
			return null; 		}

				return new Kunde( $id );
	}

	
	public static function checkLogin( $email, $pwd ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		if ( empty( $email ) ) {
			return - 1; 		}
		if ( empty( $pwd ) ) {
			return - 2; 		}

		
		$sql   = $wpdb->prepare( "SELECT * FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$daten = $wpdb->get_row( $sql );

		
		if ( empty( $daten ) ) {
			sleep( 5 ); 
			return - 3; 		}

		
		if ( empty( $daten->email_verifiziert_am ) ) {
			
			self::sendeBestaetigungsEmail( $daten->email );

			return - 4;
		}

		
		$valid = password_verify( $pwd, $daten->passwort );
		if ( ! $valid ) {
			sleep( 5 ); 
			return - 3; 		}

		
		self::doLogin( $daten->id );

		

		return true;
	}

	
	private static function doLogin( $kundeId ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		session_regenerate_id();

		


		
		$loginToken = bin2hex( openssl_random_pseudo_bytes( 100 ) );

		
		$timeLocal = current_time( 'mysql', false );
		$timeUtc   = current_time( 'mysql', true );

		$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET " .
		                       " letzter_login = %s, " .
		                       " letzter_login_utc = %s, " .
		                       " letzte_aktivitaet = %s, " .
		                       " letzte_aktivitaet_utc = %s, " .
		                       " pwd_token = '', " .
		                       " login_token = %s " .
		                       " WHERE id = %d", [
			$timeLocal,
			$timeUtc,
			$timeLocal,
			$timeUtc,
			$loginToken,
			$kundeId
		] );
		$wpdb->query( $sql );

		
		$loginDomain = parse_url( 'http://' . Tools::get_option( 'muv-kk-login-domain', '' ), PHP_URL_HOST );

		if ( empty( $loginDomain ) ) {
			$loginDomain = ''; 		}

		setcookie( 'muv-kk-login-token', $loginToken, 0, '/', $loginDomain );

		
		$pseudoLogin = Tools::get_option( 'muv-kk-erlaube-pseudo-login', DefaultSettings::ERLAUBE_PSEUDO_LOGIN );
		if ( $pseudoLogin == true ) {
			
			$sql   = $wpdb->prepare( "SELECT pseudo_login_token FROM " . $tables['kunden'] . " WHERE id = %d", $kundeId );
			$token = $wpdb->get_var( $sql );
			if ( empty( $token ) ) {
				
				$token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
				$sql   = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET pseudo_login_token = %s WHERE id = %d", [
					$token,
					$kundeId
				] );
				$wpdb->query( $sql );
			}

			
			setcookie( 'muv-kk-plogin-token', $token, time() + 60 * 60 * 24 * 365, '/', $loginDomain );
		}
	}

	
	public static function logout( $kundeId, $clearPseudoLogin = true ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET login_token = NULL WHERE id = %d", $kundeId );
		$wpdb->query( $sql );

		
		if ( $clearPseudoLogin ) {
			$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET pseudo_login_token = NULL WHERE id = %d", $kundeId );
			$wpdb->query( $sql );
		}

		
	}

	
	public static function erzeugeKundenZugang( $email, $vorname, $nachname, $passwort, $verifiziert = false ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		$email = filter_var( $email, FILTER_VALIDATE_EMAIL );
		if ( empty( $email ) ) {
			return - 1;
		}
		if ( empty( $passwort ) || ( strlen( $passwort ) < 8 ) ) {
			return - 2;
		}

		$sql                = $wpdb->prepare( "SELECT email_verifiziert_am FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$emailVerifiziertAm = $wpdb->get_var( $sql );

		
		if ( ! empty( $emailVerifiziertAm ) ) {
			
			if ( empty( $emailVerifiziertAm ) ) {
				self::sendeBestaetigungsEmail( $email );

				return - 3;
			} else {
				return - 4;
			}
		}

		
		$vorname    = empty( $vorname ) ? '' : $vorname;
		$nachname   = empty( $nachname ) ? '' : $nachname;
		$passwort   = password_hash( $passwort, PASSWORD_DEFAULT );
		$erstelltIp = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		
		if ( empty( $erstelltIp ) ) {
			$erstelltIp = '???.???.???.???';
		}

		
		$emailNeu = $email;

		
		$timeLocal = current_time( 'mysql', false );
		$timeUtc   = current_time( 'mysql', true );

		
		$sql = $wpdb->prepare( "INSERT INTO " . $tables['kunden'] .
		                       "(`email`, `passwort`, `vorname`, `nachname`, `erstellt_am`, `erstellt_am_utc`, `erstellt_ip`, `email_neu`) " .
		                       "VALUES (%s, %s, %s, %s, %s, %s, %s, %s)", [
			$email,
			$passwort,
			$vorname,
			$nachname,
			$timeLocal,
			$timeUtc,
			$erstelltIp,
			$emailNeu
		] );
		$wpdb->query( $sql );

		if ( ! $verifiziert ) {
			
			self::sendeBestaetigungsEmail( $email );
		}

		return true; 	}

	
	private static function sendeBestaetigungsEmail( $email ) {
		global $wpdb;
		$tables = DBTables::getTables();

		$sql   = $wpdb->prepare( "SELECT * FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$kunde = $wpdb->get_row( $sql );

		if ( empty( $kunde ) ) {
			return - 101; 		}

		
		if ( empty( $kunde->email_token ) ) {
			$kunde->email_token = bin2hex( openssl_random_pseudo_bytes( 50 ) );
			$sql                = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET email_token = %s WHERE id = %d", [
				$kunde->email_token,
				$kunde->id
			] );
			$wpdb->query( $sql );
		}

		
		$fromMail                 = sanitize_email( Tools::get_option( 'muv-kk-email-von-mail' ) );
		$fromName                 = sanitize_text_field( Tools::get_option( 'muv-kk-email-von-name' ) );
		$subject                  = sanitize_text_field( Tools::get_option( 'muv-kk-email-vorlage-konto-aktivieren-betreff' ) );
		$daten['html-content']    = Tools::get_option( 'muv-kk-email-vorlage-konto-aktivieren-html' );
		$daten['text-content']    = sanitize_textarea_field( Tools::get_option( 'muv-kk-email-vorlage-konto-aktivieren-text' ) );
		$img['email-logo']        = trim( Tools::get_option( 'muv-kk-email-vorlage-logo', '' ) );
		$daten['has-header-logo'] = ! empty( $img['email-logo'] );

		
		
		$goto = filter_input( INPUT_POST, 'muv-kk-seite', FILTER_SANITIZE_URL );
		if ( empty( $goto ) ) {
			$goto = Tools::getPageUrl();
		}

		$link = add_query_arg( [
			'muv-kk-aktion' => 'aktiviere-konto',
			'muv-kk-email'  => $email,
			'muv-kk-token'  => $kunde->email_token
		], $goto );

		
		$jahr = date( 'Y' );
		
		$name = trim( $kunde->vorname . ' ' . $kunde->nachname );

		$daten['html-content'] = str_replace( '##LINK##', $link, $daten['html-content'] );
		$daten['html-content'] = str_replace( '##JAHR##', $jahr, $daten['html-content'] );

		
		if ( $name === '' ) {
			$daten['html-content'] = str_replace( ' ##NAME##', '', $daten['html-content'] );
		}
		$daten['html-content'] = str_replace( '##NAME##', $name, $daten['html-content'] );
		$daten['html-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['html-content'] );

		$daten['text-content'] = str_replace( '##LINK##', $link, $daten['text-content'] );
		$daten['text-content'] = str_replace( '##JAHR##', $jahr, $daten['text-content'] );
		if ( $name === '' ) {
			$daten['text-content'] = str_replace( ' ##NAME##', '', $daten['text-content'] );
		}
		$daten['text-content'] = str_replace( '##NAME##', $name, $daten['text-content'] );
		$daten['text-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['text-content'] );

		$htmlMessage  = Tools::getTemplateContent( 'Mails/html.tpl.php', $daten );
		$plainMessage = Tools::getTemplateContent( 'Mails/text.tpl.php', $daten );

		
		$typ = (int) ( Tools::get_option( 'muv-kk-email-vorlage-konto-aktivieren-typ', '' ) );

		if ( $typ === 1 ) {
			
			$htmlMessage = '';
			$img         = array();
		}
		if ( $typ === 2 ) {
			
			$plainMessage = '';
		}

		return Mail::send( $fromMail, $fromName, $email, $subject, $htmlMessage, $plainMessage, '', $img );
	}

	
	public static function bestaetigeEmail( $email, $emailToken ) {
		global $wpdb;
		$tables = DBTables::getTables();

		$sql   = $wpdb->prepare( "SELECT * FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$daten = $wpdb->get_row( $sql );

		if ( empty( $daten ) ) {
			return - 1; 		}

		
		if ( ( $daten->email_token == $emailToken ) && ( empty( $daten->email_verifiziert_am ) ) ) {
			$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET " .
			                       "email = email_neu, " .
			                       "email_verifiziert_am = %s, " .
			                       "email_verifiziert_am_utc = %s, " .
			                       "email_neu = '', " .
			                       "email_token = '' " .
			                       "WHERE id = %d", [
				current_time( 'mysql', false ),
				current_time( 'mysql', true ),
				$daten->id
			] );

			$wpdb->query( $sql );

			return true; 		} else {
			
			if ( empty( $daten->email_verifiziert_am ) ) {
				return - 2; 			}
			if ( $daten->email_token != $emailToken ) {
				return - 1; 			}
		}

		return true;
	}

	
	public static function sendePwdVergessenEmail( $email ) {
		global $wpdb;
		$tables = DBTables::getTables();

		
		$email = filter_var( $email, FILTER_VALIDATE_EMAIL );
		if ( empty( $email ) ) {
			return - 1; 		}

		$sql   = $wpdb->prepare( "SELECT * FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $email );
		$kunde = $wpdb->get_row( $sql );

		if ( empty( $kunde ) ) {
			return - 2; 		}


		if ( empty( $kunde->email_verifiziert_am ) ) {
			self::sendeBestaetigungsEmail( $kunde->email );

			return - 3; 		}

		
		$kunde->pwd_token = bin2hex( openssl_random_pseudo_bytes( 50 ) );
		$sql              = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET pwd_token = %s WHERE id = %d", [
			$kunde->pwd_token,
			$kunde->id
		] );
		$wpdb->query( $sql );

		
		$fromMail                 = sanitize_email( Tools::get_option( 'muv-kk-email-von-mail' ) );
		$fromName                 = sanitize_text_field( Tools::get_option( 'muv-kk-email-von-name' ) );
		$subject                  = sanitize_text_field( Tools::get_option( 'muv-kk-email-vorlage-pwd-vergessen-betreff' ) );
		$daten['html-content']    = Tools::get_option( 'muv-kk-email-vorlage-pwd-vergessen-html' );
		$daten['text-content']    = sanitize_textarea_field( Tools::get_option( 'muv-kk-email-vorlage-pwd-vergessen-text' ) );
		$img['email-logo']        = trim( Tools::get_option( 'muv-kk-email-vorlage-logo', '' ) );
		$daten['has-header-logo'] = empty( $img['email-logo'] );

		
		
		$link = add_query_arg( [
			'muv-kk-aktion' => 'aendere-vergessenes-pwt',
			'muv-kk-email'  => $email,
			'muv-kk-token'  => $kunde->pwd_token
		], Tools::getPageUrl() );

		
		$jahr = date( 'Y' );
		
		$name = trim( $kunde->vorname . ' ' . $kunde->nachname );

		$daten['html-content'] = str_replace( '##LINK##', $link, $daten['html-content'] );
		$daten['html-content'] = str_replace( '##JAHR##', $jahr, $daten['html-content'] );

		
		if ( $name === '' ) {
			$daten['html-content'] = str_replace( ' ##NAME##', '', $daten['html-content'] );
		}
		$daten['html-content'] = str_replace( '##NAME##', $name, $daten['html-content'] );
		$daten['html-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['html-content'] );

		$daten['text-content'] = str_replace( '##LINK##', $link, $daten['text-content'] );
		$daten['text-content'] = str_replace( '##JAHR##', $jahr, $daten['text-content'] );
		if ( $name === '' ) {
			$daten['text-content'] = str_replace( ' ##NAME##', '', $daten['text-content'] );
		}
		$daten['text-content'] = str_replace( '##NAME##', $name, $daten['text-content'] );
		$daten['text-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['text-content'] );

		$htmlMessage  = Tools::getTemplateContent( 'Mails/html.tpl.php', $daten );
		$plainMessage = Tools::getTemplateContent( 'Mails/text.tpl.php', $daten );

		
		$typ = (int) ( Tools::get_option( 'muv-kk-email-vorlage-pwd-vergessen-typ', '' ) );

		if ( $typ === 1 ) {
			
			$htmlMessage = '';
			$img         = array();
		}
		if ( $typ === 2 ) {
			
			$plainMessage = '';
		}

		return Mail::send( $fromMail, $fromName, $email, $subject, $htmlMessage, $plainMessage, '', $img );
	}

	
	public static function aendereVegessenesPwd( $email, $token, $pwd1, $pwd2 ) {
		global $wpdb;
		$tables = DBTables::getTables();

		if ( empty( $pwd1 ) || ( strlen( $pwd1 ) < 8 ) ) {
			return - 1; 		}

		if ( $pwd1 !== $pwd2 ) {
			return - 2; 		}
		if ( trim( $email ) == '' ) {
			return - 3; 		}
		if ( trim( $token ) == '' ) {
			return - 3; 		}

		
		$sql      = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE email = %s AND  pwd_token = %s LIMIT 1", $email, $token );
		$kundenId = $wpdb->get_var( $sql );

		if ( empty( $kundenId ) ) {
			return - 3; 		}

		
		$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET letztes_passwort = passwort WHERE id = %d", $kundenId );
		$wpdb->query( $sql );

		$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET " .
		                       "passwort = %s, " .
		                       "passwort_geaendert_am = %s, " .
		                       "passwort_geaendert_am_utc = %s " .
		                       "WHERE id = %d", [
			password_hash( $pwd1, PASSWORD_DEFAULT ),
			current_time( 'mysql', false ),
			current_time( 'mysql', true ),
			$kundenId
		] );

		$wpdb->query( $sql );

		return true;
	}

	
	public static function loescheKonto( $email, $token ) {
		global $wpdb;
		$tables = DBTables::getTables();

		$token = trim( $token );
		$email = trim( $email );
		if ( empty( $token ) || empty( $email ) ) {
			return - 1; 		}

		
		$sql      = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE email = %s AND konto_loeschen_token = %s LIMIT 1", [
			$email,
			$token
		] );
		$kundenId = $wpdb->get_var( $sql );

		if ( empty( $kundenId ) ) {
			return - 1; 		}

		
		$sql = $wpdb->prepare( "DELETE FROM " . $tables['kunden'] . " WHERE id = %d", $kundenId );
		$wpdb->query( $sql );

		
		$sql = $wpdb->prepare( "DELETE FROM " . $tables['kundendaten'] . " WHERE kunde_id = %d", $kundenId );
		$wpdb->query( $sql );
		$sql = $wpdb->prepare( "DELETE FROM " . $tables['kundendaten_ext'] . " WHERE kunde_id = %d", $kundenId );
		$wpdb->query( $sql );

		
		do_action( 'muv-kk-auth-konto-loeschen', $kundenId );

		return true;
	}

}
