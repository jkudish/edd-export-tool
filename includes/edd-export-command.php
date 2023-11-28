<?php

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class EDD_Export_Command extends WP_CLI_Command {

		/**
		 * Start date as a \EDD\Utils\Date (Carbon) object, or null
		 *
		 * @since 1.0
		 * @var \EDD\Utils\Date|null
		 */
		private $start;

		/**
		 * End date as a \EDD\Utils\Date (Carbon) object, or null
		 *
		 * @since 1.0
		 * @var \EDD\Utils\Date|null
		 */
		private $end;

		/**
		 * The path to the export file
		 *
		 * @since 1.0
		 * @var string
		 */
		private $file_path;

		/**
		 * Export EDD payment data.
		 *
		 * ## OPTIONS
		 *
		 * [--output-format=<format>]
		 * : Output format (WP CLI table, csv or json).
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
		 * [--per_page=<number>]
		 * : Number of orders to retrieve per page/query. Defaults to 1000. Use this to control the number of orders retrieved per page/query. This is useful for large number of orders where memory limits are exceeded. If you are running into memory limits, try reducing this number.
		 *
		 * [--max=<number>]
		 * : Max number of orders to retrieve. Use this to control the total number of orders exported. This has a default value of 100 when using the shell output formats. Otherwise, no default value is applied
		 *
		 * [--destination=<destination>]
		 * : Path to the destination directory for the resulting CSV or JSON file. Defaults to the wp-uploads/edd-exports folder.
		 *
		 * [--fields=<fields>]
		 * : Array of fields to include in the export. Defaults to: customer_id, customer_email, payment_id, sequence_id, transaction_id, payment_date, payment_status, payment_amount, payment_gateway. Optional fields that you can include: customer_name, customer_phone, payment_notes, address1, address2, city, region, country, postal_code, phone
		 *
		 * [--days=<days>]
		 * : Export data for the last X days. Defaults to 30. Superseded by start/end dates.
		 *
		 * [--start=<date>]
		 * : Export data after specified date/time. Defaults to 30 days ago. Supersedes days argument.
		 *
		 * [--end=<date>]
		 * : Export data before specified date/time. Defaults to now. Supersedes days argument.
		 *
		 * [--status=<status>]
		 * : Filter by payment status
		 *
		 * [--minamount=<amount>]
		 * : Filter by minimum purchase amount (total)
		 *
		 * [--maxamount=<amount>]
		 * : Filter by maximum purchase amount (total)
		 *
		 *  ## EXAMPLES
		 *
		 *      # Export default fields to a CLI table
		 *      $ wp edd-export payments
		 *
		 *      # Customize fields to include in the export
		 *      $ wp edd-export payments '--fields=["customer_id","customer_email","payment_id","customer_name","payment_notes"]'
		 *
		 *      # Export to CSV in the shell and override number of days
		 *      $ wp edd-export payments --output-format=csv --days=45
		 *
		 *      # Export only failed orders between a certain date range to CSV file
		 *      $ wp edd-export payments --output-format=csv-file --start="2023-11-01" --end="2023-11-27 17:00:00" --status=failed
		 *
		 *      # Export to JSON file and specify custom destination, and filter orders by min/max total amount ($20 min, $110 max)
		 *      $ wp edd-export payments --output-format=json-file --destination=/path/to/destination --minamount=20 --maxamount=110
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
				'days'          => 30,
				'start'         => null,
				'end'           => null,
				'per_page'      => 1000,
				'minamount'     => null,
				'maxamount'     => null,
				'status'        => null,
				'max'           => in_array( $assoc_args['output-format'], array(
					'table',
					'csv',
					'json'
				) ) ? 100 : null,
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

			$writing_to_file = in_array( $args['output-format'], array(
				'csv-file',
				'json-file',
			) );

			$query = $this->determine_payment_query( $args );
			$count = edd_count_orders( $query );
			if ( empty( $count ) ) {
				return WP_CLI::warning( __( 'No EDD payments found for specified arguments.', 'edd-export-tool' ) );
			}

			$max   = isset( $args['max'] ) ? $args['max'] : $count;
			$pages = ceil( $max / $args['per_page'] );

			if ( $count > $max ) {
				WP_CLI::warning( sprintf( __( 'Exporting %d of %d payments.', 'edd-export-tool' ), $max, $count ) );
			}

			if ( $args['per_page'] > $max ) {
				$query['number'] = $max;
			}

			if ( $writing_to_file ) {
				$progress = WP_CLI\Utils\make_progress_bar( __( 'Exporting payments', 'edd-export-tool' ), $max, 10 );
			}

			foreach ( range( 1, $pages ) as $page ) {
				$query['offset'] = ( $page * $args['per_page'] ) - $args['per_page'];
				$page            = $this->export_payment_page( $page, $args, $query );
				if ( ! empty( $progress ) ) {
					$progress->tick( $args['per_page'] );
				}
			}

			if ( $writing_to_file ) {
				return WP_CLI::success( sprintf( '%s %s', __( 'Payments exported to file:', 'edd-export-tool' ), $this->file_path ) );
			}
		}

		/**
		 * Export a single page of payments
		 *
		 * @param int $page The page number
		 * @param array $args The arguments passed to the command
		 * @param array $query The query to run
		 *
		 * @return array $payments_data The data for the export
		 * @since 1.0
		 *
		 */
		private function export_payment_page( $page, array $args, array $query ) {
			$payments_data = $this->get_payments_data( $query, $args );
			switch ( $args['output-format'] ) {
				case 'csv':
					return WP_CLI\Utils\format_items( 'csv', $payments_data, array_keys( $payments_data[0] ) );

				case 'json':
					return WP_CLI\Utils\format_items( 'json', $payments_data, array_keys( $payments_data[0] ) );

				case 'csv-file':
					$this->write_to_csv_file( $page, $payments_data, $args['destination'] );
					break;

				case 'json-file':
					$this->write_to_json_file( $page, $payments_data, $args['destination'] );
					break;

				case 'table':
				default:
					return WP_CLI\Utils\format_items( 'table', $payments_data, array_keys( $payments_data[0] ) );
			}
		}

		/**
		 * Get the payments data.
		 *
		 * @param array $query The query to run
		 * @param array $args The arguments passed to the command
		 *
		 * @return array $data The data for the export
		 * @since 1.0
		 *
		 */
		private function get_payments_data( array $query, array $args ) {
			$data   = array();
			$orders = edd_get_orders( $query );

			foreach ( $orders as $order ) {
				/** @var EDD\Orders\Order $order */
				$data[] = $this->get_payment_data( $order, $args['fields'], $args['output-format'] );
			}

			$data = apply_filters( 'edd_export_tool_get_payments_data', $data );

			return $data;
		}

		/**
		 * Get the payments query.
		 *
		 * @param array $args The arguments passed to the command
		 *
		 * @return array $query The query for the specified arguments
		 * @since 1.0
		 *
		 */
		private function determine_payment_query( array $args ) {
			$query = array(
				'order'   => 'ASC',
				'orderby' => 'date_created',
				'type'    => 'sale',
				'number'  => $args['per_page'],
			);

			if (!empty($args['status'])) {
				$query['status'] = $args['status'];
			}

			$query['date_query'] = $this->get_date_query( $args['days'], $args['start'], $args['end'] );

			// there's no "total" query arg in the \EDD\Database\Queries\Order class, so we have to use a filter to apply a custom where clause
			if ( is_numeric( $args['minamount'] ) ) {
				add_filter( 'edd_orders_query_clauses', function ( $clauses ) use ( $args ) {
					global $wpdb;
					$clauses['where'] .= ' ' . $wpdb->prepare( 'AND total >= %s', $args['minamount'] );

					return $clauses;
				} );
			}

			if ( is_numeric( $args['maxamount'] ) ) {
				add_filter( 'edd_orders_query_clauses', function ( $clauses ) use ( $args ) {
					global $wpdb;
					$clauses['where'] .= ' ' . $wpdb->prepare( 'AND total <= %s', $args['maxamount'] );

					return $clauses;
				} );
			}

			return $query;
		}

		/**
		 * Gets the date query.
		 *
		 * @param int $days The number of days to include in the export. Superseded by start/end.
		 * @param string $start The start date for the export. Supersedes days.
		 * @param string $end The end date for the export. Supersedes days
		 *
		 * @return array
		 * @since 1.0
		 */
		protected function get_date_query( $days = 30, $start = null, $end = null ) {
			$this->start = isset( $start ) ? \EDD\Utils\Date::parse( $start ) : \EDD\Utils\Date::now()->subDays( $days );
			$this->end   = isset( $end ) ? \EDD\Utils\Date::parse( $end ) : \EDD\Utils\Date::now();

			if ( $this->end->isBefore( $this->start ) ) {
				return WP_CLI::error( __( 'Start date must be before end date.', 'edd-export-tool' ) );
			}

			$date_query = array(
				'after'     => '',
				'before'    => '',
				'inclusive' => true,
			);

			if ( ! empty( $this->start ) && $this->start instanceof \EDD\Utils\Date ) {
				$date_query['after'] = edd_get_utc_date_string( $this->start->toDateTimeString() );
			}

			if ( ! empty( $this->end ) && $this->end instanceof \EDD\Utils\Date ) {
				$date_query['before'] = edd_get_utc_date_string( $this->end->toDateTimeString() );
			}

			return array( $date_query );
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
		 * @param int $page The page number
		 * @param array $data The data to write to the CSV file
		 * @param string $destination The path to the destination directory for the resulting CSV file
		 *
		 * @return mixed WP_CLI::error if the file could not be opened for writing, otherwise the path to the CSV file
		 * @since 1.0
		 */
		private function write_to_csv_file( $page, $data, $destination ) {
			$file_path = $this->determine_file_path( $destination, 'csv' );
			$file      = $this->get_file( $file_path, $page );
			if ( ! $file ) {
				return WP_CLI::error( sprintf( '%s %s', __( 'Could not open csv file for writing:', 'edd-export-tool' ), $file_path ) );
			}

			if ( $page < 2 ) {
				WP_CLI\Utils\write_csv( $file, $data, array_keys( $data[0] ) );
			} else {
				WP_CLI\Utils\write_csv( $file, $data );
			}

			// memory cleanup
			unset( $data );
			fclose( $file );

			return $file_path;
		}

		/**
		 * Write the JSON file
		 *
		 * @param int $page The page number
		 * @param array $data The data to write to the CSV file
		 * @param string $destination The path to the destination directory for the resulting CSV file
		 *
		 * @return mixed WP_CLI::error if the file could not be opened for writing, otherwise the path to the JSON file
		 * @since 1.0
		 */
		private function write_to_json_file( $page, $data, $destination ) {
			$file_path = $this->determine_file_path( $destination, 'json' );
			$file      = $this->get_file( $file_path, $page );
			if ( ! $file ) {
				return WP_CLI::error( sprintf( '%s %s', __( 'Could not open json file for writing:', 'edd-export-tool' ), $file_path ) );
			}

			// if we are on page 2 or greater, we need to append to the existing data.
			if ( $page > 1 ) {
				$json          = file_get_contents( $file_path );
				$existing_data = json_decode( $json, true );
				$data          = array_merge( $existing_data, $data );
			}

			$json = json_encode( $data, JSON_PRETTY_PRINT );
			file_put_contents( $file_path, $json );

			// memory cleanup
			unset( $data );
			fclose( $file );

			return $file_path;
		}

		/**
		 * Determine the file path for the export file
		 *
		 * @param string $destination The path to the destination directory for the resulting file
		 * @param string $type The type of file to export (csv or json)
		 *
		 * @return string $file_path The path to the export file
		 * @since 1.0
		 */
		private function determine_file_path( $destination, $type = 'csv' ) {
			if ( empty( $this->file_path ) ) {
				$this->file_path = trailingslashit( $destination ) . 'edd-payments-from-' . $this->start->toDateString() . '-to-' . $this->end->toDateString() . '.' . $type;
			}

			return $this->file_path;
		}

		/**
		 * Retrieve the file data is written to
		 *
		 * @param string $file_path The path to the file
		 * @param int $page The page number
		 *
		 * @return false|resource file pointer resource on success, or false on error.
		 * @since 1.0
		 */
		private function get_file( $file_path, $page = 1 ) {
			$dir = dirname( $file_path );
			if ( ! is_dir( $dir ) ) {
				@mkdir( $dir, 0775, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}


			// make sure we start with a blank file on page 1.
			if ( file_exists( $file_path ) && $page < 2 ) {
				@unlink( $file_path );
			}

			// a+: Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it.
			return @fopen( $file_path, 'a+' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
