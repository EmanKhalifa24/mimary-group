<?php

class Ip2City {

	//maxmind doesn't provide a zip version so I've uploaded it to bitbucket (updated weekly)
	public $zip = 'http://mailster.github.io/GeoIPCity.zip';
	private $dbfile;
	private $gi;
	private $renew = false;


	public function __construct() {

		require_once MAILSTER_DIR . 'classes/libs/geoipcity.inc.php';

		$this->dbfile = MAILSTER_UPLOAD_DIR . '/GeoIPCity.dat';

		if ( mailster_option( 'cities_db' ) && mailster_option( 'cities_db' ) != $this->dbfile ) {
			$this->dbfile = mailster_option( 'cities_db' );
		} else if ( !file_exists( $this->dbfile ) ) {
				add_action( 'shutdown', array( &$this, 'renew' ) );
			}

		if ( file_exists( $this->dbfile ) ) {
			$this->gi = new mailster_CityIP( $this->dbfile );
		}
	}


	/**
	 *
	 *
	 * @param unknown $code
	 * @return unknown
	 */
	public function country( $code ) {
		return ( isset( $this->gi->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper( $code )] ) ) ? $this->gi->GEOIP_COUNTRY_NAMES[$this->gi->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper( $code )]] : $code;
	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function get_countries() {

		$rawcountries = $this->gi->GEOIP_COUNTRY_NAMES;
		$countries = array();
		foreach ( $rawcountries as $key => $country ) {
			if ( !$key ) {
				continue;
			}

			$countries[$this->gi->GEOIP_COUNTRY_CODES[$key]] = $country;
		}

		return $countries;
	}


	/**
	 *
	 *
	 * @param unknown $ip
	 * @param unknown $part (optional)
	 * @return unknown
	 */
	public function get( $ip, $part = null ) {

		//prevent some errors
		$error = ini_get( "error_reporting" );
		error_reporting( E_ERROR );
		$record = $this->gi->geoip_record_by_addr( $ip );
		error_reporting( $error );

		if ( is_null( $part ) ) {
			if ( isset( $record->city ) ) {
				$record->city = utf8_encode( trim( $record->city ) );
			}

			return $record;
		} else {
			return isset( $record->{$part} ) ? utf8_encode( $record->{$part} ) : false;
		}

	}


	/**
	 *
	 *
	 * @param unknown $force (optional)
	 * @return unknown
	 */
	public function renew( $force = false ) {

		global $wp_filesystem;

		$filemtime = file_exists( $this->dbfile ) ? filemtime( $this->dbfile ) : 0;

		if ( !$filemtime || $force || $this->renew ) {
			$do_renew = true;
		} else {
			$r = wp_remote_get( $this->zip, array( 'method' => 'HEAD' ) );
			$headers = wp_remote_retrieve_headers( $r );
			//check header
			if ( $headers['content-type'] != 'application/zip' ) {
				return new WP_Error( 'wrong_filetype', 'wrong file type' );
			}

			$lastmodified = strtotime( $headers['last-modified'] );
			$do_renew = $lastmodified - $filemtime > 0;
		}

		if ( $do_renew ) {

			mailster_require_filesystem();
			@set_time_limit( 120 );

			if ( !function_exists( 'download_url' ) ) {
				include ABSPATH . 'wp-admin/includes/file.php';
			}

			//download
			$tempfile = download_url( $this->zip );

			//create directory
			if ( !is_dir( dirname( $this->dbfile ) ) ) {
				if ( !wp_mkdir_p( dirname( $this->dbfile ) ) ) {
					return new WP_Error( 'create_directory', sprintf( 'not able to create directory %s', dirname( $this->dbfile ) ) );
				}
			}

			//unzip
			if ( !unzip_file( $tempfile, dirname( $this->dbfile ) ) ) {
				return new WP_Error( 'unzip_file', 'error unzipping file' );
			}

			if ( !file_exists( $this->dbfile ) ) {
				mailster_update_option( 'trackcities', false );
				return new WP_Error( 'file_missing', 'file is missing' );
			}

			$this->gi = new mailster_CityIP( $this->dbfile );

		}

		return file_exists( $this->dbfile ) ? $this->dbfile : false;

	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function remove() {

		global $wp_filesystem;
		mailster_require_filesystem();

		return $wp_filesystem->delete( $this->dbfile );

	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function get_real_ip() {
		return mailster_get_ip();
	}


}
