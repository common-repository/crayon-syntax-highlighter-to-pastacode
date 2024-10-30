=== Crayon Syntax Highlighter to Pastacode ===
Contributors: willybahuaud
Tags: pastacode, crayon, syntax, highlighter, migration
Requires at least: 3.1
Tested up to: 4.6
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RP2CK8K32JDPE

The only use of this plugin is to convert Crayon Syntax Highlighter's tags into Pastacode shortcodes.

== Description ==

The only use of this plugin is to convert [Crayon Syntax Highlighter](https://fr.wordpress.org/plugins/crayon-syntax-highlighter/)'s tags into [Pastacode](https://fr.wordpress.org/plugins/pastacode/) shortcodes.

This migration tool has been designed to convert multiple format of `<pre>` tags through a large number of articles and pages, using a ajax crawl so you can run it on large websites.

A report is generated automatically, allowing you to inspect the modified contents and revert changes in case of broken tags.

External codes snippets embedded with Crayon are migrated if located on one of those providers : Github, Gist, Bitbucket, Bitbucketsnippets or Pastebin. Other external sources will not be migrated.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Launch tags conversion in submenu Tools > Migrate to Pastacode

== Changelog ==

= 1.0 =
* Initial release
