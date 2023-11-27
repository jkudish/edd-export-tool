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
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function payments( $args, $assoc_args ) {
			$this->version_check();
			$payments_data = $this->get_payments_data();
			if (empty($payments_data)) {
				return WP_CLI::error( __( 'No EDD payments found.', 'edd-export-tool' ) );
			}

			return WP_CLI\Utils\format_items( $assoc_args['output-format'], $payments_data, array_keys( $payments_data[0] ) );
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
