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
 * @package PLU Redux
 * @author  Jason Cosper <boogah@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link    https://github.com/boogah/plu-redux
 *
 * @wordpress-plugin
 * Plugin Name:       PLU Redux
 * Version:           3.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jason Cosper
 * Author URI:        https://jasoncosper.com/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       plu-redux
 * Description:       Display the last updated date for plugins in the <a href="https://wordpress.org/plugins/">WordPress Plugin Directory</a>.
 * GitHub Plugin URI: boogah/plu-redux
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get the cached plugin info (last_updated and tested) for a plugin.
 *
 * Checks the .org repo first. If the plugin isn't listed there, falls back
 * to querying the Git hosting API when a Git Updater header is detected.
 *
 * @param string $slug        The plugin slug.
 * @param string $plugin_file Optional. Path to the plugin file relative to the plugins directory.
 * @return array|false Array with 'last_updated' (and optionally 'tested', 'source') keys, empty array if unknown, or false on error.
 */
function plu_redux_get_cached_plugin_info( $slug, $plugin_file = '' ) {
	$slug_hash   = md5( $slug );
	$plugin_info = get_transient( "plu_redux_{$slug_hash}" );

	// Check if we have the new format (array) or old format (string).
	if ( false === $plugin_info || ! is_array( $plugin_info ) ) {

		// Check for Git Updater headers first. Plugins that declare a Git
		// hosting URI are not in the .org repo, so skip the .org API call.
		if ( ! empty( $plugin_file ) ) {
			$git = plu_redux_detect_git_updater( $plugin_file );
			if ( $git ) {
				$host     = isset( $git['host'] ) ? $git['host'] : '';
				$branch   = isset( $git['branch'] ) ? $git['branch'] : '';
				$git_info = plu_redux_get_git_last_updated( $git['platform'], $git['slug'], $host, $branch );
				if ( is_array( $git_info ) && ! empty( $git_info['last_updated'] ) ) {
					$plugin_info = $git_info;
				} else {
					$plugin_info = array();
				}
				// Add +/- 1 hour jitter to spread out API requests for Git Updater plugins.
				$jitter = wp_rand( -HOUR_IN_SECONDS, HOUR_IN_SECONDS );
				set_transient( "plu_redux_{$slug_hash}", $plugin_info, DAY_IN_SECONDS + $jitter );
				return $plugin_info;
			}
		}

		$plugin_info = plu_redux_get_plugin_info( $slug );
		set_transient( "plu_redux_{$slug_hash}", $plugin_info, DAY_IN_SECONDS );
	}

	return $plugin_info;
}

/**
 * Check if a plugin's last updated date is older than 2 years.
 *
 * @param string $last_updated The last updated date string.
 * @return bool True if the plugin is outdated, false otherwise.
 */
function plu_redux_is_plugin_outdated( $last_updated ) {
	if ( ! $last_updated ) {
		return false;
	}

	$two_years_ago          = strtotime( '-2 years' );
	$last_updated_timestamp = strtotime( $last_updated );

	return $last_updated_timestamp < $two_years_ago;
}

/**
 * Check if a plugin's "tested up to" version is behind the current WordPress version.
 *
 * @param string $tested The "tested up to" version string.
 * @return bool True if the plugin is tested on an older major version, false otherwise.
 */
function plu_redux_is_tested_outdated( $tested ) {
	if ( ! $tested ) {
		return false;
	}

	global $wp_version;

	// Compare major.minor versions (e.g., 6.4 vs 6.5).
	$tested_parts  = explode( '.', $tested );
	$current_parts = explode( '.', $wp_version );

	$tested_major  = isset( $tested_parts[0] ) ? (int) $tested_parts[0] : 0;
	$tested_minor  = isset( $tested_parts[1] ) ? (int) $tested_parts[1] : 0;
	$current_major = isset( $current_parts[0] ) ? (int) $current_parts[0] : 0;
	$current_minor = isset( $current_parts[1] ) ? (int) $current_parts[1] : 0;

	// Outdated if tested major version is behind, or same major but minor is 2+ versions behind.
	if ( $tested_major < $current_major ) {
		return true;
	}

	if ( $tested_major === $current_major && $tested_minor < ( $current_minor - 1 ) ) {
		return true;
	}

	return false;
}

/**
 * Retrieve plugin info from the WordPress API.
 *
 * @param string $slug The plugin slug.
 * @return array|false Array with 'last_updated' and 'tested' keys, empty array if not in repo, or false on error.
 */
function plu_redux_get_plugin_info( $slug ) {
	$request = wp_remote_get(
		add_query_arg(
			array(
				'action' => 'plugin_information',
				'slug'   => sanitize_key( $slug ),
			),
			'https://api.wordpress.org/plugins/info/1.2/'
		),
		array(
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $request ) ) {
		return false;
	}

	if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$response = json_decode( wp_remote_retrieve_body( $request ) );

	// Return an empty but cacheable response if the plugin isn't in the .org repo.
	if ( empty( $response ) || isset( $response->error ) ) {
		return array();
	}

	return array(
		'last_updated' => isset( $response->last_updated ) ? sanitize_text_field( $response->last_updated ) : '',
		'tested'       => isset( $response->tested ) ? sanitize_text_field( $response->tested ) : '',
	);
}

/**
 * Git Updater header keys mapped to their hosting platform.
 *
 * @var array<string, string>
 */
define(
	'PLU_REDUX_GIT_HEADERS',
	array(
		'GitHub Plugin URI'    => 'github',
		'GitLab Plugin URI'    => 'gitlab',
		'Bitbucket Plugin URI' => 'bitbucket',
		'Gitea Plugin URI'     => 'gitea',
		'Gist Plugin URI'      => 'gist',
	)
);

/**
 * Parse an owner/repo pair from a Git Updater header value.
 *
 * Accepts full URLs (https://github.com/owner/repo) or shorthand (owner/repo).
 *
 * @param string $raw The raw header value.
 * @return string|false "owner/repo" on success, false on failure.
 */
function plu_redux_parse_git_slug( $raw ) {
	$raw = trim( $raw );

	if ( empty( $raw ) ) {
		return false;
	}

	// Full URL: pull the path and grab the first two segments.
	if ( false !== strpos( $raw, '://' ) ) {
		$path = trim( wp_parse_url( $raw, PHP_URL_PATH ), '/' );
	} else {
		$path = trim( $raw, '/' );
	}

	// Strip a trailing .git if present.
	$path = preg_replace( '/\.git$/', '', $path );

	if ( substr_count( $path, '/' ) < 1 ) {
		return false;
	}

	$parts = explode( '/', $path );
	return $parts[0] . '/' . $parts[1];
}

/**
 * Detect a Git Updater header in a plugin file and return its platform and identifier.
 *
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @return array|false Array with 'platform', 'slug', and optionally 'host'/'branch' keys, or false if not a Git Updater plugin.
 */
function plu_redux_detect_git_updater( $plugin_file ) {
	$full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

	if ( ! is_readable( $full_path ) ) {
		return false;
	}

	// get_plugin_data doesn't read custom headers by default. Read the file manually.
	$file_contents = file_get_contents( $full_path, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( false === $file_contents ) {
		return false;
	}

	// Check for Primary Branch header.
	$primary_branch = '';
	if ( preg_match( '/\*\s*Primary Branch:\s*(.+)/i', $file_contents, $branch_matches ) ) {
		$primary_branch = trim( $branch_matches[1] );
	}

	foreach ( PLU_REDUX_GIT_HEADERS as $header => $platform ) {
		if ( preg_match( '/\*\s*' . preg_quote( $header, '/' ) . ':\s*(.+)/i', $file_contents, $matches ) ) {
			$raw_value = trim( $matches[1] );

			// Gist uses a different format: just the gist ID or full URL.
			if ( 'gist' === $platform ) {
				$gist_id = plu_redux_parse_gist_id( $raw_value );
				if ( $gist_id ) {
					return array(
						'platform' => 'gist',
						'slug'     => $gist_id,
					);
				}
				continue;
			}

			// Gitea is self-hosted, so we need the host from the URL.
			if ( 'gitea' === $platform ) {
				$gitea_info = plu_redux_parse_gitea_url( $raw_value );
				if ( $gitea_info ) {
					return array(
						'platform' => 'gitea',
						'slug'     => $gitea_info['slug'],
						'host'     => $gitea_info['host'],
						'branch'   => $primary_branch,
					);
				}
				continue;
			}

			// Standard platforms: GitHub, GitLab, Bitbucket.
			$slug = plu_redux_parse_git_slug( $raw_value );
			if ( $slug ) {
				return array(
					'platform' => $platform,
					'slug'     => $slug,
					'branch'   => $primary_branch,
				);
			}
		}
	}

	return false;
}

/**
 * Parse a Gist ID from a Gist Plugin URI header value.
 *
 * Accepts full URLs (https://gist.github.com/user/id) or just the gist ID.
 *
 * @param string $raw The raw header value.
 * @return string|false The gist ID on success, false on failure.
 */
function plu_redux_parse_gist_id( $raw ) {
	$raw = trim( $raw );

	if ( empty( $raw ) ) {
		return false;
	}

	// Full URL: extract the last path segment (the gist ID).
	if ( false !== strpos( $raw, '://' ) ) {
		$path  = trim( wp_parse_url( $raw, PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		// Gist URLs are /username/gist_id or just /gist_id.
		$gist_id = end( $parts );
	} else {
		// Assume it's just the gist ID.
		$gist_id = $raw;
	}

	// Gist IDs are alphanumeric.
	if ( preg_match( '/^[a-f0-9]+$/i', $gist_id ) ) {
		return $gist_id;
	}

	return false;
}

/**
 * Parse a Gitea URL to extract the host and owner/repo.
 *
 * @param string $raw The raw header value (must be a full URL for Gitea).
 * @return array|false Array with 'host' and 'slug' keys, or false on failure.
 */
function plu_redux_parse_gitea_url( $raw ) {
	$raw = trim( $raw );

	if ( empty( $raw ) || false === strpos( $raw, '://' ) ) {
		return false;
	}

	$host = wp_parse_url( $raw, PHP_URL_HOST );
	$path = trim( wp_parse_url( $raw, PHP_URL_PATH ), '/' );

	if ( empty( $host ) || empty( $path ) ) {
		return false;
	}

	// Strip a trailing .git if present.
	$path = preg_replace( '/\.git$/', '', $path );

	if ( substr_count( $path, '/' ) < 1 ) {
		return false;
	}

	$parts = explode( '/', $path );
	$slug  = $parts[0] . '/' . $parts[1];

	return array(
		'host' => $host,
		'slug' => $slug,
	);
}

/**
 * Query a Git hosting API for the last updated date of a repository.
 *
 * @param string $platform One of 'github', 'gitlab', 'bitbucket', 'gitea', or 'gist'.
 * @param string $slug     The "owner/repo" string (or gist ID for gist platform).
 * @param string $host     Optional. The host for self-hosted platforms like Gitea.
 * @param string $branch   Optional. The primary branch name for branch-specific queries.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_git_last_updated( $platform, $slug, $host = '', $branch = '' ) {
	switch ( $platform ) {
		case 'github':
			return plu_redux_get_github_last_updated( $slug, $branch );
		case 'gitlab':
			return plu_redux_get_gitlab_last_updated( $slug, $branch );
		case 'bitbucket':
			return plu_redux_get_bitbucket_last_updated( $slug, $branch );
		case 'gitea':
			return plu_redux_get_gitea_last_updated( $slug, $host, $branch );
		case 'gist':
			return plu_redux_get_gist_last_updated( $slug );
		default:
			return false;
	}
}

/**
 * Get the last updated date from the GitHub API.
 *
 * Tries the latest release first, falls back to the primary branch's last commit.
 *
 * @param string $slug   The "owner/repo" string.
 * @param string $branch Optional. The primary branch name.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_github_last_updated( $slug, $branch = '' ) {
	$slug = sanitize_text_field( $slug );

	// Try latest release first.
	$request = wp_remote_get(
		"https://api.github.com/repos/{$slug}/releases/latest",
		array(
			'timeout'    => 15,
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
			'user-agent' => 'PLU-Redux',
		)
	);

	if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
		$release = json_decode( wp_remote_retrieve_body( $request ) );
		if ( ! empty( $release->published_at ) ) {
			return array(
				'last_updated' => sanitize_text_field( $release->published_at ),
				'source'       => 'github',
			);
		}
	}

	// Fall back to primary branch's last commit.
	if ( ! empty( $branch ) ) {
		$branch  = sanitize_text_field( $branch );
		$request = wp_remote_get(
			"https://api.github.com/repos/{$slug}/branches/{$branch}",
			array(
				'timeout'    => 15,
				'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
				'user-agent' => 'PLU-Redux',
			)
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$branch_data = json_decode( wp_remote_retrieve_body( $request ) );
			if ( ! empty( $branch_data->commit->commit->committer->date ) ) {
				return array(
					'last_updated' => sanitize_text_field( $branch_data->commit->commit->committer->date ),
					'source'       => 'github',
				);
			}
		}
	}

	// Final fallback: repo's default branch via repo endpoint.
	$request = wp_remote_get(
		"https://api.github.com/repos/{$slug}",
		array(
			'timeout'    => 15,
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
			'user-agent' => 'PLU-Redux',
		)
	);

	if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$repo = json_decode( wp_remote_retrieve_body( $request ) );

	// If no branch was specified, try the repo's default branch.
	if ( empty( $branch ) && ! empty( $repo->default_branch ) ) {
		$default_branch = sanitize_text_field( $repo->default_branch );
		$request        = wp_remote_get(
			"https://api.github.com/repos/{$slug}/branches/{$default_branch}",
			array(
				'timeout'    => 15,
				'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
				'user-agent' => 'PLU-Redux',
			)
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$branch_data = json_decode( wp_remote_retrieve_body( $request ) );
			if ( ! empty( $branch_data->commit->commit->committer->date ) ) {
				return array(
					'last_updated' => sanitize_text_field( $branch_data->commit->commit->committer->date ),
					'source'       => 'github',
				);
			}
		}
	}

	// Ultimate fallback: repo pushed_at (any branch).
	if ( empty( $repo->pushed_at ) ) {
		return false;
	}

	return array(
		'last_updated' => sanitize_text_field( $repo->pushed_at ),
		'source'       => 'github',
	);
}

/**
 * Get the last updated date from the GitLab API.
 *
 * @param string $slug   The "owner/repo" string.
 * @param string $branch Optional. The primary branch name.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_gitlab_last_updated( $slug, $branch = '' ) {
	$encoded = rawurlencode( sanitize_text_field( $slug ) );

	// If branch specified, get the branch's last commit.
	if ( ! empty( $branch ) ) {
		$branch  = sanitize_text_field( $branch );
		$request = wp_remote_get(
			"https://gitlab.com/api/v4/projects/{$encoded}/repository/branches/{$branch}",
			array( 'timeout' => 15 )
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$branch_data = json_decode( wp_remote_retrieve_body( $request ) );
			if ( ! empty( $branch_data->commit->committed_date ) ) {
				return array(
					'last_updated' => sanitize_text_field( $branch_data->commit->committed_date ),
					'source'       => 'gitlab',
				);
			}
		}
	}

	// Fall back to project's last_activity_at.
	$request = wp_remote_get(
		"https://gitlab.com/api/v4/projects/{$encoded}",
		array( 'timeout' => 15 )
	);

	if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$project = json_decode( wp_remote_retrieve_body( $request ) );

	if ( empty( $project->last_activity_at ) ) {
		return false;
	}

	return array(
		'last_updated' => sanitize_text_field( $project->last_activity_at ),
		'source'       => 'gitlab',
	);
}

/**
 * Get the last updated date from the Bitbucket API.
 *
 * @param string $slug   The "owner/repo" string.
 * @param string $branch Optional. The primary branch name.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_bitbucket_last_updated( $slug, $branch = '' ) {
	$slug = sanitize_text_field( $slug );

	// If branch specified, get the branch's last commit.
	if ( ! empty( $branch ) ) {
		$branch  = sanitize_text_field( $branch );
		$request = wp_remote_get(
			"https://api.bitbucket.org/2.0/repositories/{$slug}/refs/branches/{$branch}",
			array( 'timeout' => 15 )
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$branch_data = json_decode( wp_remote_retrieve_body( $request ) );
			if ( ! empty( $branch_data->target->date ) ) {
				return array(
					'last_updated' => sanitize_text_field( $branch_data->target->date ),
					'source'       => 'bitbucket',
				);
			}
		}
	}

	// Fall back to repo's updated_on.
	$request = wp_remote_get(
		"https://api.bitbucket.org/2.0/repositories/{$slug}",
		array( 'timeout' => 15 )
	);

	if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$repo = json_decode( wp_remote_retrieve_body( $request ) );

	if ( empty( $repo->updated_on ) ) {
		return false;
	}

	return array(
		'last_updated' => sanitize_text_field( $repo->updated_on ),
		'source'       => 'bitbucket',
	);
}

/**
 * Get the last updated date from a Gitea instance API.
 *
 * @param string $slug   The "owner/repo" string.
 * @param string $host   The Gitea instance hostname.
 * @param string $branch Optional. The primary branch name.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_gitea_last_updated( $slug, $host, $branch = '' ) {
	if ( empty( $host ) ) {
		return false;
	}

	$slug = sanitize_text_field( $slug );
	$host = sanitize_text_field( $host );

	// If branch specified, get the branch's last commit.
	if ( ! empty( $branch ) ) {
		$branch  = sanitize_text_field( $branch );
		$request = wp_remote_get(
			"https://{$host}/api/v1/repos/{$slug}/branches/{$branch}",
			array( 'timeout' => 15 )
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$branch_data = json_decode( wp_remote_retrieve_body( $request ) );
			if ( ! empty( $branch_data->commit->timestamp ) ) {
				return array(
					'last_updated' => sanitize_text_field( $branch_data->commit->timestamp ),
					'source'       => 'gitea',
				);
			}
		}
	}

	// Fall back to repo's updated_at.
	$request = wp_remote_get(
		"https://{$host}/api/v1/repos/{$slug}",
		array( 'timeout' => 15 )
	);

	if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$repo = json_decode( wp_remote_retrieve_body( $request ) );

	if ( empty( $repo->updated_at ) ) {
		return false;
	}

	return array(
		'last_updated' => sanitize_text_field( $repo->updated_at ),
		'source'       => 'gitea',
	);
}

/**
 * Get the last updated date from the GitHub Gists API.
 *
 * @param string $gist_id The gist ID.
 * @return array|false Array with 'last_updated' and 'source' keys, or false on error.
 */
function plu_redux_get_gist_last_updated( $gist_id ) {
	$gist_id = sanitize_text_field( $gist_id );

	$request = wp_remote_get(
		"https://api.github.com/gists/{$gist_id}",
		array(
			'timeout'    => 15,
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
			'user-agent' => 'PLU-Redux',
		)
	);

	if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return false;
	}

	$gist = json_decode( wp_remote_retrieve_body( $request ) );

	if ( empty( $gist->updated_at ) ) {
		return false;
	}

	return array(
		'last_updated' => sanitize_text_field( $gist->updated_at ),
		'source'       => 'gist',
	);
}

/**
 * Add plugin info meta fields to the plugin information row.
 *
 * @param array  $plugin_meta An array of the plugin's metadata.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @return array Modified plugin metadata array.
 */
function plu_redux_plugin_meta( $plugin_meta, $plugin_file ) {
	// Extract the plugin's slug from the plugin filename.
	list( $slug ) = explode( '/', $plugin_file );

	$plugin_info = plu_redux_get_cached_plugin_info( $slug, $plugin_file );

	if ( ! is_array( $plugin_info ) || empty( $plugin_info ) ) {
		return $plugin_meta;
	}

	// Build PLU fields and append them to the last core meta item so
	// WordPress does not insert a pipe separator before the line break.
	$plu_parts = array();

	// Last updated date.
	if ( ! empty( $plugin_info['last_updated'] ) ) {
		$is_old  = plu_redux_is_plugin_outdated( $plugin_info['last_updated'] );
		$warning = $is_old ? '<span role="img" aria-label="' . esc_attr__( 'warning', 'plu-redux' ) . '">&#x26A0;&#xFE0F;</span> ' : '';

		$plu_parts[] = $warning . esc_html__( 'Last updated:', 'plu-redux' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $plugin_info['last_updated'] ) ) );
	}

	// Tested up to version.
	if ( ! empty( $plugin_info['tested'] ) ) {
		$is_outdated = plu_redux_is_tested_outdated( $plugin_info['tested'] );
		$warning     = $is_outdated ? '<span role="img" aria-label="' . esc_attr__( 'warning', 'plu-redux' ) . '">&#x26A0;&#xFE0F;</span> ' : '';

		$plu_parts[] = $warning . esc_html__( 'Tested up to:', 'plu-redux' ) . ' ' . esc_html( $plugin_info['tested'] );
	}

	if ( ! empty( $plu_parts ) ) {
		$last_key                  = array_key_last( $plugin_meta );
		$plugin_meta[ $last_key ] .= '<br />' . implode( ' | ', $plu_parts );
	}

	return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'plu_redux_plugin_meta', 10, 2 );

/**
 * WP-CLI command to display plugin info for installed plugins.
 *
 * @return void
 */
function plu_redux_list_command() {
	$plugins = get_plugins();

	if ( ! class_exists( '\cli\Table' ) ) {
		WP_CLI::error( __( 'Required cli\Table class not found.', 'plu-redux' ) );
		return;
	}

	global $wp_version;

	$table = new \cli\Table();
	$table->setHeaders( array( 'Plugin Name', 'Last Updated', 'Tested Up To' ) );

	foreach ( $plugins as $plugin_file => $plugin_info ) {
		// Extract the plugin's slug from the plugin filename.
		list( $slug ) = explode( '/', $plugin_file );

		$info = plu_redux_get_cached_plugin_info( $slug, $plugin_file );

		if ( ! is_array( $info ) || empty( $info ) ) {
			continue;
		}

		$last_updated = ! empty( $info['last_updated'] ) ? $info['last_updated'] : '-';
		$tested       = ! empty( $info['tested'] ) ? $info['tested'] : '-';

		// Add warnings.
		$updated_warning = '';
		$tested_warning  = '';

		if ( ! empty( $info['last_updated'] ) && plu_redux_is_plugin_outdated( $info['last_updated'] ) ) {
			$updated_warning = ' <-';
		}

		if ( ! empty( $info['tested'] ) && plu_redux_is_tested_outdated( $info['tested'] ) ) {
			$tested_warning = ' <- (WP ' . $wp_version . ')';
		}

		$table->addRow(
			array(
				$plugin_info['Name'],
				$last_updated . $updated_warning,
				$tested . $tested_warning,
			)
		);
	}

	$table->display();
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'plu list', 'plu_redux_list_command' );
}

/**
 * Register custom Site Health checks.
 *
 * @param array $tests An associative array of direct and async tests.
 * @return array Modified tests array.
 */
function plu_redux_health_check( $tests ) {
	$tests['direct']['plu_redux_outdated_check'] = array(
		'label' => __( 'Check for plugins not updated in 2 years', 'plu-redux' ),
		'test'  => 'plu_redux_perform_outdated_check',
	);

	$tests['direct']['plu_redux_tested_check'] = array(
		'label' => __( 'Check for plugins not tested with current WordPress version', 'plu-redux' ),
		'test'  => 'plu_redux_perform_tested_check',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'plu_redux_health_check' );

/**
 * Perform the outdated plugin health check for Site Health.
 *
 * @return array The test result array.
 */
function plu_redux_perform_outdated_check() {
	$plugins     = get_plugins();
	$old_plugins = array();

	foreach ( $plugins as $plugin_file => $plugin_info ) {
		list( $slug ) = explode( '/', $plugin_file );

		$info = plu_redux_get_cached_plugin_info( $slug, $plugin_file );

		if ( is_array( $info ) && ! empty( $info['last_updated'] ) && plu_redux_is_plugin_outdated( $info['last_updated'] ) ) {
			$old_plugins[] = $plugin_info['Name'];
		}
	}

	if ( empty( $old_plugins ) ) {
		return array(
			'label'       => __( 'All plugins have been updated within the last 2 years', 'plu-redux' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Plugins', 'plu-redux' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'All plugins have been updated within the last 2 years.', 'plu-redux' )
			),
			'actions'     => '',
			'test'        => 'plu_redux_perform_outdated_check',
		);
	}

	return array(
		'label'       => __( 'Some plugins have not been updated in over 2 years', 'plu-redux' ),
		'status'      => 'critical',
		'badge'       => array(
			'label' => __( 'Plugins', 'plu-redux' ),
			'color' => 'red',
		),
		'description' => sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: Comma-separated list of outdated plugin names. */
				__( 'The following plugins have not been updated in over 2 years: <em>%s.</em> Consider looking for <a href="https://wordpress.org/plugins/">actively developed alternatives</a>.', 'plu-redux' ),
				esc_html( implode( ', ', $old_plugins ) )
			)
		),
		'actions'     => '',
		'test'        => 'plu_redux_perform_outdated_check',
	);
}

/**
 * Perform the "tested up to" health check for Site Health.
 *
 * @return array The test result array.
 */
function plu_redux_perform_tested_check() {
	$plugins          = get_plugins();
	$untested_plugins = array();

	global $wp_version;

	foreach ( $plugins as $plugin_file => $plugin_info ) {
		list( $slug ) = explode( '/', $plugin_file );

		$info = plu_redux_get_cached_plugin_info( $slug, $plugin_file );

		if ( is_array( $info ) && ! empty( $info['tested'] ) && plu_redux_is_tested_outdated( $info['tested'] ) ) {
			$untested_plugins[] = sprintf(
				/* translators: 1: Plugin name, 2: Tested up to version. */
				__( '%1$s (tested up to %2$s)', 'plu-redux' ),
				$plugin_info['Name'],
				$info['tested']
			);
		}
	}

	if ( empty( $untested_plugins ) ) {
		return array(
			'label'       => __( 'All plugins are tested with a recent WordPress version', 'plu-redux' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Plugins', 'plu-redux' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'All plugins are tested with a recent version of WordPress.', 'plu-redux' )
			),
			'actions'     => '',
			'test'        => 'plu_redux_perform_tested_check',
		);
	}

	return array(
		'label'       => __( 'Some plugins are not tested with the current WordPress version', 'plu-redux' ),
		'status'      => 'recommended',
		'badge'       => array(
			'label' => __( 'Plugins', 'plu-redux' ),
			'color' => 'orange',
		),
		'description' => sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: 1: Current WordPress version, 2: Comma-separated list of plugin names with their tested versions. */
				__( 'You are running WordPress %1$s. The following plugins have not been tested with recent versions: <em>%2$s.</em> They may still work, but proceed with caution.', 'plu-redux' ),
				esc_html( $wp_version ),
				esc_html( implode( ', ', $untested_plugins ) )
			)
		),
		'actions'     => '',
		'test'        => 'plu_redux_perform_tested_check',
	);
}

/**
 * Clean up plugin transients on uninstall.
 *
 * @return void
 */
function plu_redux_uninstall() {
	global $wpdb;

	// Delete all PLU Redux transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_plu_redux_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_plu_redux_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
register_uninstall_hook( __FILE__, 'plu_redux_uninstall' );
