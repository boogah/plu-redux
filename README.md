# Plugin Last Updated Redux

Displays a "Last Updated" date for all of your plugins installed by way of the WordPress Plugin Directory.

On plugins that have not been updated in over two years, a warning emoji is displayed next to the last updated date.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/plu-redux` directory via SFTP, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. That's it! The plugin will automatically display the last updated date for installed plugins available in the WordPress Plugin Directory.

Sure, here's an example section that you can add to the `README.md` file to document the new WP-CLI command:

## WP-CLI Command

PLU Redux includes a WP-CLI command that displays the "Last Updated" date of installed plugins. Here's how you use it:

```
wp plu list
```

This command will output a list of installed (WordPress Plugin Directory listed) plugins alongside their "Last Updated" date:

```
+-----------------------------------+---------------------------+
| Plugin Name                       | Last Updated              |
+-----------------------------------+---------------------------+
| Akismet Anti-Spam Spam Protection | 2023-04-05 10:17am GMT    |
| Lazy Load                         | 2018-11-22 04:43am GMT  ← |
| Proxy Cache Purge                 | 2022-06-09 10:57pm GMT    |
+-----------------------------------+---------------------------+
```

Plugins that have not been updated in over two years will be highlighted with an arrow (←) to the right of the "Last Updated" date.

## Frequently Asked Questions

### How does the plugin determine if a plugin hasn't been updated in over two years?

The plugin checks a public API — and caches the results in a transient for 24 hours — to see if the last updated date for a plugin installed on your site is older than two years. If it is, a warning emoji (⚠️) is displayed next to the "Last Updated" date.

### Can I customize the warning emoji or the text that's displayed?

Nope! The plugin does not currently support customization of the warning emoji or the text that's displayed.

Decisions, not options!

### Does the plugin work with any plugins that are not in the WordPress Plugin Directory?

Sadly, no. PLU Redux only works with plugins that are available in the WordPress Plugin Directory.

## Changelog

### 2.1.0
* Added a WP-CLI command.

### 2.0.0
* Brought code up to date.
* Added feature where a warning emoji is displayed next to plugins that have not been updated in over two years.

### 1.0.2
* See [Plugin Last Updated](https://wordpress.org/plugins/plugin-last-updated/) WordPress Plugin Directory listing for previous changelog entries.

## License

This plugin is released under the GPL-2.0+ license. See the [LICENSE.txt](LICENSE.txt) file for more details.
