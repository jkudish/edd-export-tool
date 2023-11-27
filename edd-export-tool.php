<?php
/**
 * Plugin Name:     Edd Export Tool
 * Plugin URI:      https://github.com/jkudish/edd-export-tool
 * Description:     WP-CLI command that facilitates the extraction of payment data from Easy Digital Downloads (EDD) platform. The command provides the capability to export payment data from a specified time frame, enabling users to generate outputs in either CSV or JSON formats, and can output its data directly or to a specified file location. The tool offers customization options for selecting the data fields to be included in the output, along with various filtering mechanisms based on payment criteria.
 * Author:          Joey Kudish
 * Author URI:      https://github.com/jkudish
 * Text Domain:     edd-export-tool
 * Domain Path:     /languages
 * Version:         1.0
 *
 * @package         Edd_Export_Tool
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_Export_Tool' ) ) {

	final class EDD_Export_Tool {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD Export Tool exists in memory at any one time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * The plugin version.
		 *
		 * @var string
		 */
		public $version = '1.0';

		/**
		 * Path to the plugin file.
		 *
		 * @var string
		 */
		public $file;

		/**
		 * Path to the plugin's directory.
		 *
		 * @var string
		 */
		public $plugin_dir;

		/**
		 * URL to the plugin's directory.
		 *
		 * @var string
		 */
		public $plugin_url;


		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof edd_export_tool ) ) {
				self::$instance = new Edd_Export_Tool;
				self::$instance->setup_globals();
				self::$instance->hooks();
				self::$instance->init_wp_cli_command();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;

		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Globals
		 *
		 * @return void
		 * @since 1.0
		 *
		 */
		private function setup_globals() {

			$this->file       = __FILE__;
			$this->basename   = apply_filters( 'edd_export_tool_plugin_basename', plugin_basename( $this->file ) );
			$this->plugin_dir = apply_filters( 'edd_export_tool_plugin_dir_path', plugin_dir_path( $this->file ) );
			$this->plugin_url = apply_filters( 'edd_export_tool_plugin_dir_url', plugin_dir_url( $this->file ) );
		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @return void
		 * @since 1.0
		 *
		 */
		private function hooks() {
			// text domain
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );
		}

		/**
		 * Initialize the WP CLI command
		 *
		 * @return void
		 * @since 1.0
		 *
		 */
		private function init_wp_cli_command() {
			require_once dirname( __FILE__ ) . '/includes/edd-export-command.php';
		}


		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @return void
		 * @since 1.0
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( $this->file ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_export_tool_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'edd-export-tool' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'edd-export-tool', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-export-tool/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-export-tool folder
				load_textdomain( 'edd-export-tool', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-export-tool/languages/ folder
				load_textdomain( 'edd-export-tool', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'edd-export-tool', false, $lang_dir );
			}
		}
	}
}

/**
 * Loads a single instance of EDD Export Tool
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_export_tool = edd_export_tool(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Export_Tool::get_instance()
 *
 * @return object Returns an instance of the edd_export_tool class
 */
function edd_export_tool() {
	return EDD_Export_Tool::get_instance();
}

require_once dirname( __FILE__ ) . '/vendor/autoload.php';
\EDD\ExtensionUtils\v1\ExtensionLoader::loadOrQuit( __FILE__, 'edd_export_tool', array(
	'php'                    => '5.4',
	'easy-digital-downloads' => '2.9.14',
	'wp'                     => '4.4',
) );
