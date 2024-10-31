<?php

namespace muv\KundenKonto\Classes;


defined( 'ABSPATH' ) OR exit;


class Kunde extends \stdClass {
	
	private $tableDaten = [];
	
	private $zusatzDaten = [];
	
	private $idDB;

	
	function __construct( $id ) {
		$this->idDB = $id;

		global $wpdb;
		$tables = DBTables::getTables();

		
		$sql              = $wpdb->prepare( "SELECT * FROM " . $tables['kunden'] . " WHERE id = %d LIMIT 1", $this->idDB );
		$this->tableDaten = $wpdb->get_row( $sql );

		
		$sql         = $wpdb->prepare( "SELECT key1, daten FROM " . $tables['kundendaten'] . " WHERE kunde_id = %d", $this->idDB );
		$zusatzDaten = $wpdb->get_results( $sql );
		if ( !empty($zusatzDaten ) ) {
			foreach ( $zusatzDaten as $d ) {
				$key = $d->key;
				$this->zusatzDaten->$key = $d->daten;
			}
		}
	}

	
	public function __get( $name ) {
		
		if ( $name === 'id' ) {
			return $this->idDB;
		}

		
		if ( array_key_exists( $name, $this->tableDaten ) ) {
			return $this->tableDaten->$name;
		}

		
		if ( array_key_exists( $name, $this->zusatzDaten ) ) {
			return $this->tableZusatzDaten[ $name ];
		}

				return null;
	}


	
	public function __set( $name, $value ) {
		if ( $name === 'id' ) {
			
			return;
		}

		global $wpdb;
		$tables = DBTables::getTables();

		
		if ( array_key_exists( $name, $this->tableDaten ) ) {
			
			if ( $value === $this->tableDaten->$name ) {
				return;
			}

						$this->tableDaten->$name = $value;

			
			$sql = $wpdb->prepare( "UPDATE " . $tables['kunden'] . " SET " . $name . " = %s WHERE id = %d", [
				$value,
				$this->idDB
			] );
			$wpdb->query( $sql );

			return;

		}

		
		if ( array_key_exists( $name, $this->zusatzDaten ) ) {
			
			if ( $value === $this->tableZusatzDaten[ $name ] ) {
				return;
			}

						$this->tableZusatzDaten[ $name ] = $value;

			
			$sql = $wpdb->prepare( "UPDATE " . $tables['kundendaten'] . " SET daten = %s WHERE kunde_id = %d AND key1 = %s", [
				$value,
				$this->idDB,
				$name
			] );
			$wpdb->query( $sql );

			return;
		} else {
			
			$sql = $wpdb->prepare( "INSERT INTO " . $tables['kundendaten'] . " (kunde_id, key1, daten) VALUES (%s, %d, %s)", [
				$value,
				$this->idDB,
				$name
			] );

			$wpdb->query( $sql );
		}
	}

	
	public function getKundenDatenExt( $key1, $key2 = null, $value = null ) {

		global $wpdb;
		$tables = DBTables::getTables();

		$sql = $wpdb->prepare( "SELECT daten, daten_bin FROM " . $tables['kundendaten_ext'] . " WHERE kunde_id = %d AND key1 = %s", [
			$this->idDB,
			$key1
		] );
		if ( ! empty( $key2 ) && ! empty( $value ) ) {
			$sql = $wpdb->prepare( "SELECT daten, daten_bin FROM " . $tables['kundendaten_ext'] . " WHERE kunde_id = %d AND key1 = %s AND key2 = %s AND daten = %s", [
				$this->idDB,
				$key1,
				$key2,
				$value
			] );
		} elseif ( ! empty( $key2 ) ) {
			$sql = $wpdb->prepare( "SELECT daten, daten_bin FROM " . $tables['kundendaten_ext'] . " WHERE kunde_id = %d AND key1 = %s AND key2 = %s", [
				$this->idDB,
				$key1,
				$key2
			] );
		} elseif ( ! empty( $value ) ) {
			$sql = $wpdb->prepare( "SELECT daten, daten_bin FROM " . $tables['kundendaten_ext'] . " WHERE kunde_id = %d AND key1 = %s AND daten = %s", [
				$this->idDB,
				$key1,
				$value
			] );
		}
		$result = $wpdb->get_row( $sql );
		if ( ! empty( $value ) ) {
			return $result->daten_bin;
		}

		return $result->daten;
	}

	
	public function setKundenDatenExt( $key1, $key2 = null, $value = null, $bin = null ) {

		global $wpdb;
		$tables = DBTables::getTables();

		$bestehend = $this->getKundenDatenExt( $key1, $key2, $value );
		if ( empty( $bestehend ) ) {
			$sql = $wpdb->prepare( "INSERT INTO " . $tables['kundendaten_ext'] . "(kunde_id, key1, key2, daten, daten_bin) VALUES (%d, %s, %s, %s, %s)", [
				$this->idDB,
				$key1,
				$key2,
				$value,
				$bin
			] );
		} else {
			if ( empty( $bin ) ) {
				if ( empty( $key2 ) ) {
					$sql = $wpdb->prepare( "UPDATE " . $tables['kundendaten_ext'] . " SET `daten` = %s WHERE kunde_id = %d AND `key1` = %s", [
						$value,
						$this->idDB,
						$key1
					] );
				} else {
					$sql = $wpdb->prepare( "UPDATE " . $tables['kundendaten_ext'] . " SET `daten` = %s WHERE kunde_id = %d AND `key1` = %s AND `key2` = %s", [
						$value,
						$this->idDB,
						$key1,
						$key2
					] );
				}
			} else {
				if ( empty( $key2 ) ) {
					$sql = $wpdb->prepare( "UPDATE " . $tables['kundendaten_ext'] . " SET `daten_bin` = %s WHERE kunde_id = %d AND `key1` = %s AND `daten` = %s", [
						$bin,
						$this->idDB,
						$key1,
						$value
					] );
				} else {
					$sql = $wpdb->prepare( "UPDATE " . $tables['kundendaten_ext'] . " SET `daten_bin` = %s WHERE kunde_id = %d AND `key1` = %s AND `key2` = %s AND `daten` = %s", [
						$bin,
						$this->idDB,
						$key1,
						$key2,
						$value
					] );
				}
			}
		}

		$wpdb->query( $sql );
	}

	
	private function sendeEmail( $kennung, $zusatzDaten ) {

		
		$fromMail                 = sanitize_email( Tools::get_option( 'muv-kk-email-von-mail' ) );
		$fromName                 = sanitize_text_field( Tools::get_option( 'muv-kk-email-von-name' ) );
		$subject                  = sanitize_text_field( Tools::get_option( 'muv-kk-email-vorlage-' . $kennung . '-betreff' ) );
		$daten['html-content']    = Tools::get_option( 'muv-kk-email-vorlage-' . $kennung . '-html' );
		$daten['text-content']    = sanitize_textarea_field( Tools::get_option( 'muv-kk-email-vorlage-' . $kennung . '-text' ) );
		$img['email-logo']        = trim( Tools::get_option( 'muv-kk-email-vorlage-logo', '' ) );
		$daten['has-header-logo'] = ! empty( $img['email-logo'] );

		
				$jahr = date( 'Y' );
				$name = trim( $this->vorname . ' ' . $this->nachname );

		$daten['html-content'] = str_replace( '##JAHR##', $jahr, $daten['html-content'] );

		
		if ( $name === '' ) {
			$daten['html-content'] = str_replace( ' ##NAME##', '', $daten['html-content'] );
		}
		$daten['html-content'] = str_replace( '##NAME##', $name, $daten['html-content'] );
		$daten['html-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['html-content'] );

		$daten['text-content'] = str_replace( '##JAHR##', $jahr, $daten['text-content'] );
		if ( $name === '' ) {
			$daten['text-content'] = str_replace( ' ##NAME##', '', $daten['text-content'] );
		}
		$daten['text-content'] = str_replace( '##NAME##', $name, $daten['text-content'] );
		$daten['text-content'] = str_replace( '##EMAIL-TO##', $fromMail, $daten['text-content'] );


		
		if ( is_array( $zusatzDaten ) ) {
			foreach ( $zusatzDaten as $k => $v ) {
				$daten['html-content'] = str_replace( '##' . strtoupper( $k ) . '##', $v, $daten['html-content'] );
				$daten['text-content'] = str_replace( '##' . strtoupper( $k ) . '##', $v, $daten['text-content'] );
			}
		}

		$daten = array_merge( $daten, $zusatzDaten );


		$htmlMessage  = Tools::getTemplateContent( 'Mails/html.tpl.php', $daten );
		$plainMessage = Tools::getTemplateContent( 'Mails/text.tpl.php', $daten );

		
		$typ = (int) ( Tools::get_option( 'muv-kk-email-vorlage-' . $kennung . '-typ', '' ) );

		if ( $typ === 1 ) {
			
			$htmlMessage = '';
			$img         = array();
		}
		if ( $typ === 2 ) {
			
			$plainMessage = '';
		}

		return Mail::send( $fromMail, $fromName, $this->email, $subject, $htmlMessage, $plainMessage, '', $img );
	}


	
	public function aenderePwd( $pwdAlt, $pwd1, $pwd2 ) {
		$pwd1   = trim( $pwd1 );
		$pwd2   = trim( $pwd2 );
		$pwdAlt = trim( $pwdAlt );

		
		if ( empty( $pwdAlt ) ) {
			return - 1; 		}
		$valid = password_verify( $pwdAlt, $this->passwort );
		if ( ! $valid ) {
			sleep( 2 ); 
			return - 2; 		}

		
		if ( empty( $pwd1 ) || ( strlen( $pwd1 ) < 8 ) ) {
			return - 3; 		}
		if ( $pwd1 !== $pwd2 ) {
			return - 4; 		}

		
		$passwort = password_hash( $pwd1, PASSWORD_DEFAULT );

		$this->letztes_passwort = $this->passwort;
		$this->passwort         = $passwort;
				$timeLocal                       = current_time( 'mysql', false );
		$timeUtc                         = current_time( 'mysql', true );
		$this->passwort_geaendert_am     = $timeLocal;
		$this->passwort_geaendert_am_utc = $timeUtc;

		
		$this->sendeEmailPwdGeaendert();

		return true;
	}

	
	private function sendeEmailPwdGeaendert() {
				$daten = [];

				return $this->sendeEmail( 'pwd-geaendert', $daten );
	}


	
	public function aendereEmail( $pwd, $emailNeu ) {
		$pwd      = trim( $pwd );
		$emailNeu = trim( $emailNeu );

		
		if ( empty( $pwd ) ) {
			return - 1; 		}
		$valid = password_verify( $pwd, $this->passwort );
		if ( ! $valid ) {
			sleep( 2 ); 
			return - 2; 		}

		
		$emailNeu = filter_var( trim( $emailNeu ), FILTER_VALIDATE_EMAIL );
		if ( empty( $emailNeu ) ) {
			return - 3; 		}


		
		if ( strtolower( $emailNeu ) === strtolower( $this->email ) ) {
			return - 4; 		}

		
		$this->email_neu   = $emailNeu;
		$this->email_token = bin2hex( openssl_random_pseudo_bytes( 50 ) );

				$this->sendeEmailEmailAktivieren();

		return true; 	}

	
	private function sendeEmailEmailAktivieren() {
		$daten              = [];
		$daten['email-neu'] = $this->email_neu;

		
		$daten['link'] = add_query_arg( [
			'muv-kk-aktion' => 'bestaetige-email',
			'muv-kk-email'  => $this->email,
			'muv-kk-token'  => $this->email_token
		], Tools::getPageUrl() );

		return $this->sendeEmail( 'email-aktivieren', $daten );
	}

	
	public function aktiviereNeueEmail( $token ) {
		
		if ( $token !== $this->email_token ) {
			return - 1;
		}

		
		global $wpdb;
		$tables = DBTables::getTables();

		$sql = $wpdb->prepare( "SELECT id FROM " . $tables['kunden'] . " WHERE email = %s LIMIT 1", $this->email_neu );
		$id  = $wpdb->get_var( $sql );
		if ( ! empty( $id ) ) {
						return - 2;
		}


						$this->sendeEmailEmailGeaendert();

		
		$this->email       = $this->email_neu;
		$this->email_neu   = '';
		$this->email_token = '';

				$timeLocal                      = current_time( 'mysql', false );
		$timeUtc                        = current_time( 'mysql', true );
		$this->email_verifiziert_am     = $timeLocal;
		$this->email_verifiziert_am_utc = $timeUtc;

		return true;
	}

	
	private function sendeEmailEmailGeaendert() {
		$daten = [];
		
		$daten['email'] = $this->email_neu;

		return $this->sendeEmail( 'email-geaendert', $daten );
	}


	
	public function logout() {
		Auth::logout( $this->id, true );
	}

	public function loescheKonto( $pwd ) {
		$pwd = trim( $pwd );

		
		if ( empty( $pwd ) ) {
			return - 1; 		}
		$valid = password_verify( $pwd, $this->passwort );
		if ( ! $valid ) {
			sleep( 5 ); 
			return - 2; 		}

		
		$this->konto_loeschen_token = bin2hex( openssl_random_pseudo_bytes( 50 ) );

		$this->sendeEmailLoescheKonto();

		return true;
	}

	
	private function sendeEmailLoescheKonto() {
		$daten = [];
		
		$daten['link'] = add_query_arg( [
			'muv-kk-aktion' => 'loesche-konto',
			'muv-kk-email'  => $this->email,
			'muv-kk-token'  => $this->konto_loeschen_token
		], Tools::getPageUrl() );

		return $this->sendeEmail( 'konto-loeschen', $daten );
	}

}