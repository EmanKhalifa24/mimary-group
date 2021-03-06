<?php

class MailsterUpdate {


	public function __construct() {

		add_filter( 'upgrader_pre_download', array( &$this, 'upgrader_pre_download' ), 10, 3 );

		if ( ! class_exists( 'UpdateCenterPlugin' ) ) {
			require_once MAILSTER_DIR . 'classes/UpdateCenterPlugin.php';
		}

		UpdateCenterPlugin::add( array(
			'licensecode' => get_option( 'mailster_license' ),
			'remote_url' => 'https://update.mailster.co/',
			'plugin' => MAILSTER_SLUG,
			'autoupdate' => mailster_option( 'autoupdate', true ),
		) );

	}


	/**
	 *
	 *
	 * @param unknown $reply
	 * @param unknown $package
	 * @param unknown $upgrader
	 * @return unknown
	 */
	public function upgrader_pre_download( $reply, $package, $upgrader ) {

		if ( ( isset( $upgrader->skin->plugin ) && $upgrader->skin->plugin === MAILSTER_SLUG ) ||
			( isset( $upgrader->skin->plugin_info ) && $upgrader->skin->plugin_info['Name'] === 'Mailster - Email Newsletter Plugin for WordPress' ) ) {

			$upgrader->strings['mailster_download'] = __( 'Downloading the latest version of Mailster', 'mailster' ) . '...';
			$upgrader->skin->feedback( 'mailster_download' );

			$res = $upgrader->fs_connect( array( WP_CONTENT_DIR ) );
			if ( ! $res ) {
				return new WP_Error( 'fs_unavailable', $upgrader->strings['fs_unavailable'] );
			}

			add_filter( 'http_response', array( &$this, 'alter_update_message' ), 10, 3 );
			$download_file = download_url( $package );
			remove_filter( 'http_response', array( &$this, 'alter_update_message' ), 10, 3 );

			if ( is_wp_error( $download_file ) ) {

				$short_msg = isset( $_SERVER['HTTP_REFERER'] ) ? preg_match( '#page=envato-market#', $_SERVER['HTTP_REFERER'] ) : false;

				$upgrader->strings['mailster_download_error'] = __( 'Not able to download Mailster!', 'mailster' );
				$upgrader->skin->feedback( 'mailster_download_error' );

				$code = $download_file->get_error_message();

				$error_msg = mailster()->get_update_error( $code, $short_msg, __( 'An error occurred while updating Mailster!', 'mailster' ) );

				switch ( $code ) {

					case 680: // Licensecode in use

						$error_msg = $error_msg . ' <a href="https://mailster.co/go/buy/?utm_campaign=plugin&utm_medium=inline+link" target="_blank"><strong>' . sprintf( __( 'Buy an additional license for %s.', 'mailster' ), ( mailster_is_local() ? __( 'your new site', 'mailster' ) : $_SERVER['HTTP_HOST'] ) . '</strong></a>' );

					case 679: // No Licensecode provided
					case 678: // Licensecode invalid

						add_filter( 'update_plugin_complete_actions', array( &$this, 'add_update_action_link' ) );
						add_filter( 'install_plugin_complete_actions', array( &$this, 'add_update_action_link' ) );

					break;

					case 500: // Internal Server Error
					case 503: // Service Unavailable
						$error = __( 'Envato servers are currently down. Please try again later!', 'mailster' );
					break;

					default:

						$error = __( 'An error occurred while updating Mailster!', 'mailster' );
						if ( $message ) {
							$error .= '<br>' . $message;
						}
					break;
				}

				if ( is_a( $upgrader->skin, 'Bulk_Plugin_Upgrader_Skin' ) ) {

					return new WP_Error( 'mailster_download_error', $error_msg );

				} else {

					$upgrader->strings['mailster_error'] = '<div class="error inline"><p><strong>' . $error_msg . '</strong></p></div>';
					$upgrader->skin->feedback( 'mailster_error' );
					$upgrader->skin->result = new WP_Error( 'mailster_download_error', $error_msg );
					return new WP_Error( 'mailster_download_error', '' );

				}
			}

			return $download_file;

		}

		return $reply;

	}


	/**
	 *
	 *
	 * @param unknown $response
	 * @param unknown $r
	 * @param unknown $url
	 * @return unknown
	 */
	public function alter_update_message( $response, $r, $url ) {

		$code = wp_remote_retrieve_response_code( $response );

		$response['response']['message'] = $code;

		return $response;

	}


	/**
	 *
	 *
	 * @param unknown $actions
	 * @return unknown
	 */
	public function add_update_action_link( $actions ) {

		$actions['mailster_get_license'] = '<a href="https://mailster.co/go/buy/?utm_campaign=plugin&utm_medium=action+link">' . __( 'Buy a new Mailster License', 'mailster' ) . '</a>';

		return $actions;

	}

}
