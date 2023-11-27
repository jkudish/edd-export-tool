<?php

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class EDD_Export_Command extends WP_CLI_Command {

		/**
		 * Export EDD payment data.
		 *
		 * ## OPTIONS
		 *
		 * [--output-format=<format>]
		 * : Output format (csv or json).
		 * ---
		 * default: csv
		 * options:
		 *   - csv
		 *   - json
		 * ---
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function payments( $args, $assoc_args ) {
			$this->version_check();

		}

		/**
		 * Checks the EDD version installed and throws a WP_CLI error if it's not 2.9.14 or greater.
		 * Note that in normal circumstances this won't fire, because the EDD_Export_Tool class performs an initial dependency check.
		 */
		private function version_check() {
			$edd_version = get_option( 'edd_version' );
			if ( ! class_exists( 'Easy_Digital_Downloads' ) || ! version_compare( $edd_version, '2.9.14', '>=' ) ) {
				WP_CLI::error( __( 'Easy Digital Downloads 2.9.14 or greater is required to run this export tool. Please make sure EDD 2.9.14 is installed and activated.', 'edd-export-tool' ) );
			}
		}
	}

	// Register the WP-CLI command.
	WP_CLI::add_command( 'edd-export', 'EDD_Export_Command' );
}
