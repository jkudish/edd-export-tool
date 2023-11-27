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
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 * ---
		 *
		 * [--destination=<destination>]
		 * : Path to the destination directory for the resulting CSV or JSON file. Defaults to the wp-uploads/edd-exports folder.
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function payments( $args, $assoc_args ) {
			$this->version_check();
			$args = wp_parse_args( $assoc_args, array(
				'output-format' => 'table',
				'destination'   => wp_upload_dir()['basedir'] . '/edd-exports',
			) );

			$payments_data = $this->get_payments_data();
			if ( empty( $payments_data ) ) {
				return WP_CLI::error( __( 'No EDD payments found.', 'edd-export-tool' ) );
			}

			switch ( $args['output-format'] ) {
				case 'csv':
					$file_path = $this->write_csv( $payments_data, $args['destination'] );

					return WP_CLI::success( sprintf( '%s %s', __( 'Payments exported to CSV file:', 'edd-export-tool' ), $file_path ) );

				case 'json':
					$file_path = $this->write_json( $payments_data, $args['destination'] );

					return WP_CLI::success( sprintf( '%s %s', __( 'Payments exported to JSON file:', 'edd-export-tool' ), $file_path ) );

				case 'table':
				default:
					return WP_CLI\Utils\format_items( 'table', $payments_data, array_keys( $payments_data[0] ) );
			}
		}

		/**
		 * Get the payments data.
		 *
		 * @return array $data The data for the export
		 * @since 1.0
		 *
		 */
		private function get_payments_data() {
			$data = array();

			$args = array(
				'order'   => 'ASC',
				'orderby' => 'date_created',
				'type'    => 'sale',
			);

			$orders = edd_get_orders( $args );

			foreach ( $orders as $order ) {
				/** @var EDD\Orders\Order $order */
				$data[] = $this->get_payment_data( $order );
			}

			$data = apply_filters( 'edd_export_tool_get_payments_data', $data );

			return $data;
		}

		/**
		 * Get the data for an individual payment
		 *
		 * @param EDD\Orders\Order $order The order object
		 *
		 * @return array $data The data for an individual payment
		 * @since 1.0
		 *
		 */
		private function get_payment_data( $order ) {
			$payment_data = array(
				'Customer ID'     => $order->customer_id,
				'Customer Email'  => $order->email,
				'Order ID'        => $order->id,
				'Sequence ID'     => $order->get_number(),
				'Transaction ID'  => $order->get_transaction_id(),
				'Payment Date'    => $order->date_created,
				'Payment Status'  => $order->status,
				'Payment Amount'  => html_entity_decode( edd_format_amount( $order->total ) ),
				'Payment Gateway' => edd_get_gateway_admin_label( $order->gateway ),
			);

			$payment_data = apply_filters( 'edd_export_tool_get_payment_data', $payment_data );

			return $payment_data;
		}

		/**
		 * Write the CSV file
		 *
		 * @param array $data The data to write to the CSV file
		 * @param string $destination The path to the destination directory for the resulting CSV file
		 *
		 * @return mixed WP_CLI::error if the file could not be opened for writing, otherwise the path to the CSV file
		 * @since 1.0
		 */
		private function write_csv( $data, $destination ) {
			$file_path = trailingslashit( $destination ) . 'edd-payments-' . date( 'Y-m-d' ) . '.csv';
			$file      = $this->get_file( $file_path );
			if ( ! $file ) {
				return WP_CLI::error( sprintf( '%s %s', __( 'Could not open csv file for writing:', 'edd-export-tool' ), $file_path ) );
			}

			WP_CLI\Utils\write_csv( $file, $data, array_keys( $data[0] ) );
			return $file_path;
		}

		/**
		 * Write the JSON file
		 *
		 * @param array $data The data to write to the CSV file
		 * @param string $destination The path to the destination directory for the resulting CSV file
		 *
		 * @return mixed WP_CLI::error if the file could not be opened for writing, otherwise the path to the JSON file
		 * @since 1.0
		 */
		private function write_json( $data, $destination ) {
			$file_path = trailingslashit( $destination ) . 'edd-payments-' . date( 'Y-m-d' ) . '.json';
			$file      = $this->get_file( $file_path );
			if ( ! $file ) {
				return WP_CLI::error( sprintf( '%s %s', __( 'Could not open json file for writing:', 'edd-export-tool' ), $file_path ) );
			}

			$json = json_encode( $data, JSON_PRETTY_PRINT );
			file_put_contents( $file_path, $json );
			return $file_path;
		}

		/**
		 * Retrieve the file data is written to
		 *
		 * @param string $file_path The path to the file
		 *
		 * @return false|resource file pointer resource on success, or false on error.
		 * @since 1.0
		 */
		private function get_file( $file_path ) {
			$dir = dirname( $file_path );
			if ( ! is_dir( $dir ) ) {
				@mkdir( $dir, 0775, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}


			if ( ! @file_exists( $file_path ) ) {
				@file_put_contents( $file_path, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@chmod( $file_path, 0664 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			return @fopen( $file_path, 'w' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
