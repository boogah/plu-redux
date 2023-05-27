<?php
/**
 * Plugin Last Updated Redux
 *
 * PLU Redux is a fork and continuation of Pete Mall's original
 * Plugin Last Updated WordPress plugin. It displays the date a
 * plugin was last updated in the WordPress Plugin Directory in
 * your list of installed plugins. On any plugins that have not
 * been updated in over two years, a warning emoji is displayed
 * next to the "Last Updated" datestamp.
 *
 * @package Plugin Last Updated Redux
 * @author  Jason Cosper <boogah@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link    https://github.com/boogah/plu-redux
 *
 * @wordpress-plugin
 * Plugin Name:       Plugin Last Updated Redux
 * Version:	          2.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jason Cosper
 * Author URI:        https://jasoncosper.com/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Description:       Display the last updated date for plugins in the <a href="http://wordpress.org/plugins/">WordPress Plugin Directory</a>.
 * GitHub Plugin URI: boogah/plu-redux
 */
 
// If this file is called directly, abort.
if (! defined('WPINC') ) {
	die;
}

// This filter hook is used to add metadata to the plugin information row on the plugins page
// It allows plugins to add additional information to the plugin row displayed in the Plugins screen
add_filter( 'plugin_row_meta', 'plu_redux_plugin_meta', 10, 2 );

// This function adds a "Last Updated" meta field to the plugin information row
function plu_redux_plugin_meta( $plugin_meta, $plugin_file ) {
	// Extract the plugin's slug from the plugin filename
	list( $slug ) = explode( '/', $plugin_file );
	
	// Generate a unique hash of the plugin's slug
	$slug_hash = md5( $slug );
	
	// Get the last updated date from the cache
	$last_updated = get_transient( "plu_redux_{$slug_hash}" );
	
	// If the cache does not have the date, retrieve it from the WordPress API and cache it
	if ( false === $last_updated ) {
		$last_updated = plu_redux_get_last_updated( $slug );
		set_transient( "plu_redux_{$slug_hash}", $last_updated, 86400 ); // cache for one day
	}

	// If we have a last updated date, add it to the plugin meta array
	if ( $last_updated ) {
		// Check if last update was more than 2 years ago
		$two_years_ago = strtotime('-2 years'); // get a Unix timestamp for 2 years ago
		$last_updated_timestamp = strtotime($last_updated); // get a Unix timestamp for the last updated date
		$is_old = $last_updated_timestamp < $two_years_ago; // check if the last updated date is older than 2 years
		$warning = $is_old ? '⚠️ ' : ''; // if the last updated date is older than 2 years, add a warning symbol
		$plugin_meta['last_updated'] = $warning . 'Last Updated: ' . esc_html( $last_updated ); // add the last updated date to the plugin meta array
	}

	return $plugin_meta; // return the modified plugin meta array
}

// This function retrieves the last updated date for a plugin from the WordPress API
function plu_redux_get_last_updated( $slug ) {
	$request = wp_remote_post(
		'https://api.wordpress.org/plugins/info/1.0/', // WordPress API endpoint for plugin information
		array(
			'body' => array(
				'action' => 'plugin_information',
				'request' => serialize(
					(object) array(
						'slug' => $slug, // plugin slug
						'fields' => array( 'last_updated' => true ) // fields to retrieve
					)
				)
			)
		)
	);
	if ( 200 != wp_remote_retrieve_response_code( $request ) ) // if request failed, return false
		return false;

	$response = unserialize( wp_remote_retrieve_body( $request ) ); // unserialize the response
	// Return an empty but cachable response if the plugin isn't in the .org repo
	if ( empty( $response ) )
		return '';
	if ( isset( $response->last_updated ) )
		return sanitize_text_field( $response->last_updated );

	return false;
}

/**
 * WP-CLI command to display last updated dates of installed plugins.
 */
function plu_redux_last_updated_command() {
	$plugins = get_plugins();

	$table = new \cli\Table();
	$table->setHeaders(array('Plugin Name', 'Last Updated'));

	foreach ($plugins as $plugin_file => $plugin_info) {
			// Extract the plugin's slug from the plugin filename
			list($slug) = explode('/', $plugin_file);

			// Generate a unique hash of the plugin's slug
			$slug_hash = md5($slug);

			// Get the last updated date from the cache
			$last_updated = get_transient("plu_redux_{$slug_hash}");

			// If the cache does not have the date, retrieve it from the WordPress API and cache it
			if (false === $last_updated) {
					$last_updated = plu_redux_get_last_updated($slug);
					set_transient("plu_redux_{$slug_hash}", $last_updated, 86400); // cache for one day
			}

			if ($last_updated) {
					// Check if last update was more than 2 years ago
					$two_years_ago = strtotime('-2 years'); // get a Unix timestamp for 2 years ago
					$last_updated_timestamp = strtotime($last_updated); // get a Unix timestamp for the last updated date
					$is_old = $last_updated_timestamp < $two_years_ago; // check if the last updated date is older than 2 years
					$warning = $is_old ? '  ←' : ''; // if the last updated date is older than 2 years, add a warning symbol
					$table->addRow(array($plugin_info['Name'], $last_updated . $warning));
			}
	}

	$table->display();
}
if ( class_exists( 'WP_CLI' ) ) {
	\WP_CLI::add_command('plu list', 'plu_redux_last_updated_command');
}

// This filter hook is used to add a custom Site Health check
add_filter( 'site_status_tests', 'plu_redux_health_check' );

function plu_redux_health_check( $tests ) {
	$tests['direct']['plugin_health_check'] = array(
		'label' => __( 'Check for any plugins that have not been updated in 2 years' ),
		'test'    => 'perform_plugin_health_check',
	);

	return $tests;
}

function perform_plugin_health_check() {
	$plugins = get_plugins();
	$old_plugins = array();

	foreach ($plugins as $plugin_file => $plugin_info) {
		list($slug) = explode('/', $plugin_file);
		$slug_hash = md5($slug);
		$last_updated = get_transient("plu_redux_{$slug_hash}");

		if (false === $last_updated) {
			$last_updated = plu_redux_get_last_updated($slug);
			set_transient("plu_redux_{$slug_hash}", $last_updated, 86400);
		}

		if ($last_updated) {
			$two_years_ago = strtotime('-2 years');
			$last_updated_timestamp = strtotime($last_updated);
			$is_old = $last_updated_timestamp < $two_years_ago;

			if ($is_old) {
				$old_plugins[] = $plugin_info['Name'];
			}
		}
	}

	if (empty($old_plugins)) {
		$result = array(
			'label'    	  => __( 'All plugins have been updated within the last 2 years' ),
			'status'      => 'good',
			'badge'    	  => array(
			'label'       => __( 'Plugins' ),
			'color'       => 'blue',
			),
			'description' => sprintf( 
				'<p>' . __( 'All plugins have been updated within the last 2 years.' ) . '</p>'
			),
			'actions'     => '',
			'test'        => 'perform_plugin_health_check',
		);
	} else {
		$result = array(
			'label'       => __( 'Some of your plugins have not been updated in over 2 years' ),
			'status'      => 'critical',
			'badge'       => array(
			'label'       => __( 'Plugins' ),
			'color'       => 'red',
			),
			'description' => sprintf( 
				'<p>' . __( 'The following plugins have not been updated in over 2 years: <em>%s.</em> It is highly suggested you look for <a href="http://wordpress.org/plugins/">actively developed alternatives</a>.' ) . '</p>',
				implode(", ", $old_plugins)
			),
			'actions'     => '',
			'test'        => 'perform_plugin_health_check',
		);
	}

	return $result;
}
