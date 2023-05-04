# Plugin Last Updated Redux

Displays a "Last Updated" date for all of your plugins installed by way of the WordPress Plugin Directory.

On plugins that have not been updated in over two years, a warning emoji is displayed next to the last updated date.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/plu-redux` directory via SFTP, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. That's it! The plugin will automatically display the last updated date for installed plugins available in the WordPress Plugin Directory.

## Frequently Asked Questions

### How does the plugin determine if a plugin hasn't been updated in over two years?

The plugin checks a public API — and caches the results in a transient for 24 hours — to see if the last updated date for a plugin installed on your site is older than two years. If it is, a warning emoji (⚠️) is displayed next to the "Last Updated" date.

### Can I customize the warning emoji or the text that's displayed?

Nope! The plugin does not currently support customization of the warning emoji or the text that's displayed.

Decisions, not options!

### Does the plugin work with any plugins that are not in the WordPress Plugin Directory?

Sadly, no. PLU Redux only works with plugins that are available in the WordPress Plugin Directory.

## Changelog

### 2.0.0
* Brought code up to date.
* Added feature where a warning emoji is displayed next to plugins that have not been updated in over two years.

### 1.0.2
* See [Plugin Last Updated](https://wordpress.org/plugins/plugin-last-updated/) WordPress Plugin Directory listing for previous changelog entries.

## License

This plugin is released under the GPL-2.0+ license. See the [LICENSE.txt](LICENSE.txt) file for more details.
