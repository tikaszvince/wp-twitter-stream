<?php
/**
 * WP Twitter Stream.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

/**
 * WP Twitter Stream Cron.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Cron {

  /**
   * The Cron hook name.
   * @link http://codex.wordpress.org/Category:WP-Cron_Functions
   * @var string HOOK
   */
  const HOOK = 'wp_twitter_stream_plugin_cron_event';

  /**
   * Schedule next run.
   */
  public static function schedule() {
    add_action('cron_schedules', 'WP_Twitter_Stream_Cron::cron_schedules');

    add_action(WP_Twitter_Stream_Cron::HOOK, 'WP_Twitter_Stream_Cron::exec');
    WP_Twitter_Stream_Cron::re_schedule_event(null, isset($_GET['r']));
  }

  /**
   * Remove next run.
   */
  public static function clear_schedule() {
    wp_clear_scheduled_hook(WP_Twitter_Stream_Cron::HOOK);
  }

  /**
   * Reschedule next event.
   *
   * @param string $frequency
   *   Cron recurrence @{link|http://codex.wordpress.org/Function_Reference/wp_get_schedules}
   * @param bool $force
   *   Force reschedule.
   */
  public static function re_schedule_event($frequency = null, $force = false) {
    if (!isset($frequency)) {
      $frequency = 'hourly';
      if (
        ($op = get_option(WP_Twitter_Stream_Plugin::SLUG))
        && isset($op['update_frequency'])
      ) {
        $frequency = $op['update_frequency'];
      }
    }

    if ($force || !wp_next_scheduled(WP_Twitter_Stream_Cron::HOOK)) {
      wp_schedule_event(time(), $frequency, WP_Twitter_Stream_Cron::HOOK);
    }
  }

  /**
   * Run scheduled job.
   */
  public static function exec() {
    require_once 'import.php';
    $options = get_option(WP_Twitter_Stream_Plugin::SLUG);
    $import = new WP_Twitter_Stream_Import();
    $import->set_options(array(
      'oauth_access_token' => $options['oauth_access_token'],
      'oauth_access_token_secret' => $options['oauth_access_token_secret'],
      'consumer_key' => $options['consumer_key'],
      'consumer_secret' => $options['consumer_secret'],
      'screen_name' => $options['screen_name'],
    ));
    $import->doImport();
  }

  /**
   * Hook: Filter:cron_schedules
   *
   * @see wp_get_schedules()
   * @link http://codex.wordpress.org/Function_Reference/wp_get_schedules
   */
  public static function cron_schedules($schedules) {
    foreach (array(0.0167 * 2, 5, 10, 15, 30) as $min) {
      $schedules[$min . '_minutes'] = array(
        'interval' => round(60 * $min),
        'display' => sprintf(__('Every %s minutes'), $min),
      );
    }
    return $schedules;
  }
}
