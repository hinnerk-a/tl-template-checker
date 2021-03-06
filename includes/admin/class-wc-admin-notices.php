<?php
/**
 * Display notices in admin.
 *
 * @author      WooThemes
 * @category    Admin
 * @package     WooCommerce/Admin
 * @version     2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TPLC_Admin_Notices Class
 */
class TPLC_Admin_Notices {

	/**
	 * Array of notices - name => callback
	 * @var array
	 */
	private $notices = array(
		'template_files'      => 'template_file_check_notice'
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
		add_action( 'wp_loaded', array( $this, 'hide_notices' ) );
		add_action( 'admin_print_styles', array( $this, 'check_theme_is_updated' ) );
		add_action( 'admin_print_styles', array( $this, 'add_notices' ) );
	}

	/**
	 * Reset notices for themes when switched or a new version of WC is installed
	 */
	public function reset_admin_notices() {
		self::add_notice( 'template_files' );
	}

	/**
	 * Show a notice
	 * @param  string $name
	 */
	public static function add_notice( $name ) {
		$notices = array_unique( array_merge( get_option( 'tplc_admin_notices', array() ), array( $name ) ) );
		update_option( 'tplc_admin_notices', $notices );
	}

	/**
	 * Remove a notice from being displayed
	 * @param  string $name
	 */
	public static function remove_notice( $name ) {
		$notices = array_diff( get_option( 'tplc_admin_notices', array() ), array( $name ) );
		update_option( 'tplc_admin_notices', $notices );
	}

	/**
	 * See if a notice is being shown
	 * @param  string  $name
	 * @return boolean
	 */
	public static function has_notice( $name ) {
		return in_array( $name, get_option( 'tplc_admin_notices', array() ) );
	}

	/**
	 * Hide a notice if the GET variable is set.
	 */
	public function hide_notices() {
		if ( isset( $_GET['hide_template_files_notice'] ) ) {
			$hide_notice = sanitize_text_field( $_GET['hide_template_files_notice'] );
			self::remove_notice( $hide_notice );
		}
	}

	/**
	 * Prevent hiding notices action from being fired on page refresh.
	 *
	 * Works with filter 'removable_query_args' since WP version 4.2
	 */
	public function removable_query_args( $removable_query_args ) {
		array_push( $removable_query_args, 'hide_template_files_notice' );
		return $removable_query_args;
	}

	/**
	 * Add notices + styles if needed.
	 */
	public function add_notices() {
		$notices = get_option( 'tplc_admin_notices', array() );

		foreach ( $notices as $notice ) {
			wp_enqueue_script( 'wc-admin-notices' );
			add_action( 'admin_notices', array( $this, $this->notices[ $notice ] ) );
		}
	}


	/**
	 * Show a notice highlighting bad template files
	 */
	public function template_file_check_notice() {
		
		require_once( 'class-wc-admin-status.php' );


		$template_path = get_template_directory() . '/';

		$parent_theme_templates = TPLC_Admin_Status::scan_template_files( $template_path );
		// TPLC_Admin_Status::scan_template_files( WC()->plugin_path() . '/templates' );
		$outdated       = false;

		foreach ( $parent_theme_templates as $file ) {
			$theme_file = false;
			
			$child_path = get_stylesheet_directory() . '/' . $file;

			// Exclude functions.php
			if ( file_exists( $child_path ) && basename( $file ) !== 'functions.php' ) {
				$theme_file = $child_path;
			} else {
				$theme_file = false;
			}

			if ( $theme_file ) {
				$core_version = TPLC_Admin_Status::get_file_version( get_template_directory() . '/' . $file );
				$theme_version = TPLC_Admin_Status::get_file_version( $theme_file );

				if ( $core_version && $theme_version && version_compare( $theme_version, $core_version, '<' ) ) {
					$outdated = true;
					break;
				}
			}
		}

		if ( $outdated ) {
			include( 'views/html-notice-template-check.php' );
		} else {
			self::remove_notice( 'template_files' );
		}
	}

	/**
	 * Check if the core theme has been updated since last call and reset admin notices, in case of update.
	 *
	 * This works for manual theme updates, as well as for automatic updates.
	 */
	public function check_theme_is_updated() {
		$core_theme = wp_get_theme( get_template() );
		if ( ! $core_theme->exists() ) {
			return;
		}
		$latest_core_version  = get_theme_mod( 'tl_latest_core_version' );
		$current_core_version = $core_theme->get( 'Version' );
		if ( version_compare( $latest_core_version, $current_core_version, '<' ) ) {
			$this->reset_admin_notices();
			set_theme_mod( 'tl_latest_core_version', $current_core_version );
		}
	}

}

new TPLC_Admin_Notices();
