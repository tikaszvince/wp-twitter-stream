=== WP Twitter Stream ===
Contributors: tikaszvince
Tags: twitter, twitter api, twitter stream, twitter widget
Requires at least: 3.5.1
Tested up to: 3.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Synchronise all of your tweets into your blogs database.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload files to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Login to <a href="https://dev.twitter.com/">https://dev.twitter.com/</a>
1. Add a new application at <a href="https://dev.twitter.com/apps">https://dev.twitter.com/apps</a>.
Do not forget to setup Callback URL to the blog home!
1. On the new app page you can see these data
  >- Consumer key
  >- Consumer secret
  >- Access token
  >- Access token secret
1. In WordPress admin go to "Settings" > "Twitter Stream" and
  >- setup the values you just see on your Twitter App page,
  >- the update frequency
  >- and the user's screen name
1. Setup Twitter Widget
1. To make sure all of your tweets are synchronised setup cron to your blog. For
more instruction see <a href="http://wp.tutsplus.com/articles/insights-into-wp-cron-an-introduction-to-scheduling-tasks-in-wordpress/">WPTuts+ article</a>
about setting up WordPress cron.

