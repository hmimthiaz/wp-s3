=== Wordpress Amazon S3 Plugin ===
Tags: Media, Amazon, S3, CDN, Admin, Uploads, Mirror
Contributors: imthiaz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FMMYTX4JXJHF8
Requires at least: 2.3
Tested up to: 3.0
Stable tag: 1.0

== Description ==
WP-S3 copies media files used in your blog post to Amazon S3 cloud. Uses only filters to replace the media urls in the post if media is available in the S3 cloud. Wordpress cron functionality is used for batching media upload to  S3. This plugin is very safe and will not modify anything in your database.

== Installation ==
1. Copy plugin files to wordpress wp-content/plugins folder
2. Make sure you create a folder named 's3temp' in your media upload folder and make it writable. 
3. Activate the plugin
4. Goto Amazon s3 page under plugins and set up your Amazon S3 credentials
5. This plugin will not create any S3 buckets. You have to create the bucket with public read access and use the same
6. The plugin will not work until all the configs are completed
7. If anything goes wrong just de-active the plugin and blog should go back to its old state

== Frequently Asked Questions ==

= If I de-activate this plugin will it affect my blog? =
No. This plugin does not change any content in your blog. All modification are done using wordpress plugin filters on the fly.

= Should I modify any code in wordpress? =
Not needed. You have to just upload the files

= Can I manage my files in Amazon S3? =
No. You cannot manage the files in Amazon S3 using this plugin.

== Changelog ==

= Version: 1.0 Dated: 20-June-2010 =
* First version of the plugin

== Upgrade Notice ==
No upgrade notices available

== Screenshots ==
No screenshots available