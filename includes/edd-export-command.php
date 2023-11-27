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
		 *   - csv-file
		 *   - json-file
		 * ---
		 *
		 * [--destination=<destination>]
		 * : Path to the destination directory for the resulting CSV or JSON file. Defaults to the wp-uploads/edd-exports folder.
		 *
		 * [--fields=<fields>]
		 * : Array of fields to include in the export. Defaults to: customer_id, customer_email, payment_id, sequence_id, transaction_id, payment_date, payment_status, payment_amount, payment_gateway. Optional fields that you can include: customer_name, customer_phone, payment_notes, address1, address2, city, region, country, postal_code, phone
		 *
		 *  ## EXAMPLES
		 *
		 *      # Export default fields to a CLI table
		 *      $ wp edd-export payments
		 *
		 *      # Customize fields to include in the export
		 *      $ wp edd-export payments '--fields=["customer_id","customer_email","payment_id","customer_name","payment_notes"]'
		 *
		 *      # Export to CSV in the shell
		 *      $ wp edd-export payments --output-format=csv
		 *
		 *      # Export to CSV file
		 *      $ wp edd-export payments --output-format=csv-file
		 *
		 *      # Export to JSON file and specify custom destination
		 *      $ wp edd-export payments --output-format=json-file --destination=/path/to/destination
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function payments( $args, $assoc_args ) {
			$this->version_check();

			$assoc_args = WP_CLI\Utils\parse_shell_arrays( $assoc_args, array( 'fields' ) );
			$args       = wp_parse_args( $assoc_args, array(
				'output-format' => 'table',
				'destination'   => wp_upload_dir()['basedir'] . '/edd-exports',
				'fields'        => array(
					'customer_id',
					'customer_email',
					'order_id',
					'sequence_id',
					'transaction_id',
					'payment_date',
					'payment_status',
					'payment_amount',
					'payment_gateway',
				),
			) );

			$payments_data = $this->get_payments_data( $args['fields'], $args['output-format'] );
			if ( empty( $payments_data ) ) {
				return WP_CLI::error( __( 'No EDD payments found.', 'edd-export-tool' ) );
			}

			switch ( $args['output-format'] ) {
				case 'csv':
					return WP_CLI\Utils\format_items( 'csv', $payments_data, array_keys( $payments_data[0] ) );

				case 'json':
					return WP_CLI\Utils\format_items( 'json', $payments_data, array_keys( $payments_data[0] ) );

				case 'csv-file':
					$file_path = $this->write_csv_file( $payments_data, $args['destination'] );

					return WP_CLI::success( sprintf( '%s %s', __( 'Payments exported to CSV file:', 'edd-export-tool' ), $file_path ) );

				case 'json-file':
					$file_path = $this->write_json_file( $payments_data, $args['destination'] );

					return WP_CLI::success( sprintf( '%s %s', __( 'Payments exported to JSON file:', 'edd-export-tool' ), $file_path ) );

				case 'table':
				default:
					return WP_CLI\Utils\format_items( 'table', $payments_data, array_keys( $payments_data[0] ) );
			}
		}

		/**
		 * Get the payments data.
		 *
		 * @param array $fields The fields to include in the export
		 * @param string $output_format The output format
		 *
		 * @return array $data The data for the export
		 * @since 1.0
		 *
		 */
		private function get_payments_data( $fields, $output_format ) {
			$data = array();

			$args = array(
				'order'   => 'ASC',
				'orderby' => 'date_created',
				'type'    => 'sale',
			);

			$orders = edd_get_orders( $args );

			foreach ( $orders as $order ) {
				/** @var EDD\Orders\Order $order */
				$data[] = $this->get_payment_data( $order, $fields, $output_format );
			}

			$data = apply_filters( 'edd_export_tool_get_payments_data', $data );

			return $data;
		}

		/**
		 * Get the data for an individual payment
		 *
		 * @param EDD\Orders\Order $order The order object
		 *
		 * @param array $fields The fields to include in the export
		 * @param string $output_format The output format
		 *
		 * @return array $data The data for an individual payment
		 * @since 1.0
		 *
		 */
		private function get_payment_data( $order, $fields, $output_format ) {
			$payment_data = array();
			$fields       = apply_filters( 'edd_export_tool_payment_fields', $fields );
			$fields       = array_flip( $fields );
			$customer     = edd_get_customer( $order->customer_id );


			if ( isset( $fields['customer_id'] ) ) {
				$payment_data['customer_id'] = $order->customer_id;
			}

			if ( isset( $fields['customer_email'] ) ) {
				$payment_data['customer_email'] = $customer && isset( $customer->email ) ? $customer->email : '';
			}

			if ( isset( $fields['payment_id'] ) ) {
				$payment_data['payment_id'] = $order->id;
			}

			if ( isset( $fields['sequence_id'] ) ) {
				$payment_data['sequence_id'] = $order->get_number();
			}

			if ( isset( $fields['transaction_id'] ) ) {
				$payment_data['transaction_id'] = $order->get_transaction_id();
			}

			if ( isset( $fields['payment_date'] ) ) {
				$payment_data['payment_date'] = $order->date_created;
			}

			if ( isset( $fields['payment_status'] ) ) {
				$payment_data['payment_status'] = $order->status;
			}

			if ( isset( $fields['payment_amount'] ) ) {
				$payment_data['payment_amount'] = html_entity_decode( edd_format_amount( $order->total ) );
			}

			if ( isset( $fields['payment_gateway'] ) ) {
				$payment_data['payment_gateway'] = edd_get_gateway_admin_label( $order->gateway );
			}

			if ( isset( $fields['customer_name'] ) ) {
				$payment_data['customer_name'] = $customer && isset( $customer->name ) ? $customer->name : '';
			}

			if ( isset( $fields['address1'] ) || isset( $fields['address2'] ) || isset( $fields['city'] ) || isset( $fields['region'] ) || isset( $fields['country'] ) || isset( $fields['postal_code'] ) ) {
				$address = $order->get_address();
			}

			if ( isset( $fields['address1'] ) ) {
				$payment_data['address1'] = isset( $address->address ) ? $address->address : '';
			}

			if ( isset( $fields['address2'] ) ) {
				$payment_data['address2'] = isset( $address->address2 ) ? $address->address2 : '';
			}

			if ( isset( $fields['city'] ) ) {
				$payment_data['city'] = isset( $address->city ) ? $address->city : '';
			}

			if ( isset( $fields['region'] ) ) {
				$payment_data['region'] = isset( $address->region ) ? $address->region : '';
			}

			if ( isset( $fields['country'] ) ) {
				$payment_data['country'] = isset( $address->country ) ? $address->country : '';
			}

			if ( isset( $fields['postal_code'] ) ) {
				$payment_data['postal_code'] = isset( $address->postal_code ) ? $address->postal_code : '';
			}

			if ( isset( $fields['payment_notes'] ) ) {
				$notes = $order->get_notes();
				if ( ! empty( $notes ) ) {
					if ( in_array( $output_format, array( 'json', 'json-file' ) ) ) {
						$payment_data['payment_notes'] = array();
						foreach ( $notes as $note ) {
							$payment_data['payment_notes'][] = $note->content;
						}
					} else {
						$payment_data['payment_notes'] = '';
						foreach ( $notes as $note ) {
							if ( ! empty( $payment_data['payment_notes'] ) ) {
								$payment_data['payment_notes'] .= " | ";
							}
							$payment_data['payment_notes'] .= $note->content;
						}
					}
				}
			}

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
		private function write_csv_file( $data, $destination ) {
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
		private function write_json_file( $data, $destination ) {
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
