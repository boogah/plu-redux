# PLU Redux

Ever wonder when a plugin was last updated? Or if it's even been tested with your version of WordPress? Yeah, me too.

PLU Redux displays the "Last Updated" date and "Tested up to" version for all your plugins from the WordPress Plugin Directory. If a plugin hasn't been updated in over two years - or hasn't been tested with a recent WordPress version - you'll get a warning emoji.

**New in 3.0:** Full support for plugins managed by [Git Updater](https://git-updater.com/). GitHub, GitLab, Bitbucket, Gitea, and Gist are all covered.

## Installation

1. Upload the plugin files to `/wp-content/plugins/plu-redux` via SFTP, or just install it through the WordPress plugins screen.
2. Activate the plugin.
3. That's it. Seriously.

No settings to configure, no options to tweak.

Decisions, not options.

## Usage

Once activated, head over to your Plugins screen. You'll see "Last Updated" and "Tested up to" info in the metadata for each plugin.

Warning emojis appear when:
- A plugin hasn't been updated in over two years
- A plugin's "Tested up to" version is significantly behind your current WordPress version

Consider them gentle nudges.

## Git Updater Support

PLU Redux automatically detects plugins using [Git Updater](https://git-updater.com/) headers and queries the appropriate API for last updated info.

Supported platforms:

| Platform | Header | Notes |
|----------|--------|-------|
| GitHub | `GitHub Plugin URI` | Checks releases first, falls back to last push |
| GitLab | `GitLab Plugin URI` | Uses `last_activity_at` |
| Bitbucket | `Bitbucket Plugin URI` | Uses `updated_on` |
| Gitea | `Gitea Plugin URI` | Self-hosted; requires full URL |
| Gist | `Gist Plugin URI` | Uses gist `updated_at` |

If a plugin has a Git Updater header, PLU Redux skips the WordPress.org API entirely and goes straight to the git host. This avoids false matches when a plugin slug happens to collide with something in the .org directory.

PLU Redux also respects the `Primary Branch` header. When there's no release, it falls back to the last commit on the specified branch rather than just any repo activity.

**Note:** "Tested up to" is only available for WordPress.org plugins. Git-hosted plugins will only show "Last updated."

## Site Health Checks

PLU Redux adds two checks to Tools → Site Health:

1. **Outdated plugins** - Flags any plugins not updated in 2+ years (critical status)
2. **Untested plugins** - Flags plugins not tested with recent WordPress versions (recommended status)

## WP-CLI Command

There's a WP-CLI command too:

```
wp plu list
```

This outputs a table of your installed plugins with their last updated dates and tested versions:

```
+-----------------------------------+---------------------------+-----------------+
| Plugin Name                       | Last Updated              | Tested Up To    |
+-----------------------------------+---------------------------+-----------------+
| Akismet Anti-Spam Spam Protection | 2023-04-05 10:17am GMT    | 6.5             |
| Lazy Load                         | 2018-11-22 04:43am GMT <- | 5.2 <- (WP 6.5) |
| Git Updater                       | 2024-01-15 03:22pm GMT    | -               |
+-----------------------------------+---------------------------+-----------------+
```

Arrows (`<-`) call out plugins that need attention. A `-` in the "Tested Up To" column means the plugin isn't in the WordPress.org directory (likely a Git Updater plugin).

## FAQ

### How does it know if a plugin is old?

The plugin checks the WordPress.org API (or the appropriate git hosting API) and caches the results for 24 hours. If the last updated date is older than two years, boom - warning emoji.

### How does the "Tested up to" check work?

Same API call. If a plugin's "Tested up to" version is a full major version behind (or more than one minor version behind on the same major), you'll see a warning. So if you're on WP 6.5 and a plugin says it's tested up to 5.9, that's a flag.

### Why don't my Git Updater plugins show "Tested up to"?

Git hosting APIs don't have that info. Only WordPress.org tracks "Tested up to" versions. For git-hosted plugins, you'll just see the last updated date.

### Can I customize the warnings or thresholds?

Nope.

*shrug* Decisions, not options!

### Does this work with premium plugins?

Only if they use Git Updater headers pointing to a public repository. Private repos or plugins with no Git Updater headers won't show any info.

## Changelog

### 3.0.0

* **New:** Git Updater support - plugins with `GitHub Plugin URI`, `GitLab Plugin URI`, `Bitbucket Plugin URI`, `Gitea Plugin URI`, or `Gist Plugin URI` headers now show last updated dates from their respective APIs.
* Git Updater plugin cache includes ±1 hour jitter to spread out API requests.
* Respects `Primary Branch` header when falling back from releases to branch commits.
* **New:** Added "Tested up to" version display in plugin metadata.
* **New:** Added Site Health check for plugins not tested with recent WordPress versions.
* **New:** WP-CLI command now shows "Tested up to" column.
* **Security:** Replaced `serialize()`/`unserialize()` with JSON API (v1.2) to prevent potential object injection vulnerabilities.
* **Security:** Added proper output escaping with `esc_html()` and `esc_attr__()`.
* **Security:** Added `is_wp_error()` check and input sanitization on API requests.
* Refactored caching to store both `last_updated` and `tested` fields.
* Added text domain (`plu-redux`) to all translatable strings for proper i18n support.
* Added uninstall hook to clean up transients when the plugin is deleted.
* Updated code to follow WordPress Coding Standards.
* Replaced magic number `86400` with `DAY_IN_SECONDS` constant.
* Added PHPDoc blocks to all functions.
* Fixed HTTP to HTTPS for wordpress.org links.

### 2.2.3

* Added `aria-label` to warning emoji to improve accessibility. Special thanks to Dale Reardon for the heads up!

### 2.2.2

* Making an attempt to meet WP Plugin Directory requirements.
* Added some documentation around the site health check to the README.

### 2.2.1

* Plugin now uses site's preferred date format.
* Cleaned up some wonky formatting.

### 2.2.0

* Added a Site Health check that lists any outdated plugins.

### 2.1.1

* Fixed bug that would prevent plugin from being installable when WP-CLI is not available.
* Added Git Updater header.

### 2.1.0

* Added a WP-CLI command.

### 2.0.0

* Brought code up to date.
* Added feature where a warning emoji is displayed next to plugins that have not been updated in over two years.

### 1.0.2

* See [Plugin Last Updated](https://wordpress.org/plugins/plugin-last-updated/) WordPress Plugin Directory listing for previous changelog entries.

## License

This plugin is released under the GPL-2.0+ license. See the [LICENSE](LICENSE) file for more details.

---

P.S. If you find this plugin useful, that's great! If you run into issues, [open an issue on GitHub](https://github.com/boogah/plu-redux/issues). Enjoy!
