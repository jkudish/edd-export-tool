## Easy Digital Downloads (EDD) Export Tool

A WP-CLI command that facilitates the extraction of payment data from Easy Digital Downloads (EDD) platform. The command provides the capability to export payment data from a specified time frame, enabling users to generate outputs in either CSV or JSON formats. The command can output its data directly to the shell or save it to a specified file location. The tool offers customization options for selecting the data fields to be included in the output, along with various filtering mechanisms based on payment criteria.

### License

* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

### Installation

1. Upload the plugin files to the `/wp-content/plugins/edd-export-tool` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' menu in WordPress

### Usage

Use the `wp edd-export` command to export payment data via the WP-CLI interface.

Available paramaters:

* `--output-format=<format>`: Output format (WP CLI table, csv or json).
  * default: `table`
  * options:
  *   - `table`
  *   - `csv`
  *   - `json`
  *   - `csv-file`
  *   - `json-file`


* `--per_page=<number>`: Number of orders to retrieve per page/query. Defaults to 1000. Use this to control the number of orders retrieved per page/query. This is useful for large number of orders where memory limits are exceeded. If you are running into memory limits, try reducing this number.

* `--max=<number>`: Max number of orders to retrieve. Use this to control the total number of orders exported. This has a default value of 100 when using the shell output formats. Otherwise, no default value is applied

* `--destination=<destination>`: Path to the destination directory for the resulting CSV or JSON file. Defaults to the `wp-uploads/edd-exports` folder which will be created if it doesn't exist.

* `--fields=<fields>`: Array of fields to include in the export. Defaults to: `customer_id, customer_email, payment_id, sequence_id, transaction_id, payment_date, payment_status, payment_amount, payment_gateway`. Optional fields that you can include: `customer_name, customer_phone, payment_notes, address1, address2, city, region, country, postal_code, phone`

* `--days=<days>`: Export data for the last X days. Defaults to 30. Superseded by start/end dates.

* `--start=<date>`: Export data after specified date/time. Defaults to 30 days ago. Supersedes days argument.

* `--end=<date>`: Export data before specified date/time. Defaults to now. Supersedes days argument.

* `--status=<status>`: Filter by payment status

* `--minamount=<amount>`: Filter by minimum purchase amount (total)

* `--maxamount=<amount>`: Filter by maximum purchase amount (total)

* `--customer_ids=<customer_ids>`: Filter by an array of customer IDs

* `--emails=<emails>`: Filter by an array of emails

* `--product_id=<product_id>`: Filter by a specific product ID

* `--product_price_id=<product_price_id>`: Filter by a specific product price ID


#### EXAMPLES


1. Export default fields to a CLI table:
	```
	$ wp edd-export payments
	```
1. Customize fields to include in the export and filter by customer IDs
	```
	$ wp edd-export payments '--fields=["customer_id","customer_email","payment_id","customer_name","payment_notes"]' '--customer_ids=[1,2]'
 	```
1. Export specific customer's (by e-mail) orders to CSV in the shell and override number of days to include
	```
	$ wp edd-export payments --output-format=csv --days=45 '--emails=["joey@test.com"]
 	```
1. Export only failed orders between a certain date range to CSV file
	```
	$ wp edd-export payments --output-format=csv-file --start="2023-11-01" --end="2023-11-27 17:00:00" --status=failed
	```
1. Export specific product sales to JSON file and specify custom destination, and filter orders by min/max total amount ($20 min, $110 max)
	```
	$ wp edd-export payments --output-format=json-file --destination=/path/to/destination --minamount=20 --maxamount=110 --product_id=123
	```

#### Automatic exporting via wp-cron

To set up the WP-CLI command to run automatically via WP Cron, you can create a custom WordPress cron event and schedule it to execute the WP-CLI command.

1. Create a Custom WP Cron Event: in a custom plugin file or in your theme's `functions.php` file, hook into the `init` action to schedule your custom cron event.
	```php
	add_action('init', 'edd_schedule_export_cron');
	function edd_schedule_export_cron() {
		if (!wp_next_scheduled('edd_export_task')) {
			wp_schedule_event(time(), 'daily', 'edd_export_task');
		}
	}
	```

2. Hook the WP cron event to the WP-CLI command: add an action hook to link your custom cron event with the export task.
	```php
	add_action('edd_export_task', function(){
		// run the WP-CLI command, adjust the arguments as needed
		WP_CLI::runcommand('edd-export payments --output-format=csv-file --destination=/path/to/destination --minamount=20 --maxamount=110 --product_id=123');
	});
	```

This code tells WordPress to execute the WP-CLI command when the `edd_export_task` schedule is run. You can adjust the frequency of the cron event by changing the second parameter of the `wp_schedule_event` function. For example, to run the command every 6 hours, you would change the second parameter to `6_hours`. For more information on the `wp_schedule_event` function, see the [WordPress Codex](https://developer.wordpress.org/reference/functions/wp_schedule_event/).
