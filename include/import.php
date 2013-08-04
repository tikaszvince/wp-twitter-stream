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
class WP_Twitter_Stream_Import {

  /** @var array */
  protected $options;

  /**
   * Set importer options.
   *
   * @param array $options
   *  The options array.
   * @return WP_Twitter_Stream_Import
   */
  public function set_options(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * Perform request to Twitter API and save tweets into database.
   */
  public function do_import() {
    // Perform request.
    $tweets = $this->get_tweets();
    foreach ($tweets as $tweet) {
      // We need a new parser.
      $parser = $this->get_parser($tweet);
      // Get tweet
      $tweet = $parser->get_tweet();
      $tweet->insert();
    }

    // Echo message.
    if (count($tweets)) {
      echo sprintf('Twitter Stream Import: %s new tweet(s) imported.', count($tweets));
    }
    else {
      echo 'Twitter Stream Import: No new tweets.';
    }
  }

  /**
   * Get tweets from Twitter API.
   * @return array
   */
  protected function get_tweets() {
    $api = WP_Twitter_Stream_Plugin::get_instance()->get_api();

    $timeline_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
    $get = array(
      'screen_name=' . $this->options['screen_name'],
      'exclude_replies=false',
      'count=100'
    );

    // If we have any old tweets find the last one, and add its id to the
    // query params. We do not want to reimport any tweets.
    if ($last_id = WP_Twitter_Stream_Db::get_latest_tweet_id()) {
      $get[] = 'since_id=' . $last_id;
    }

    $api
      ->setGetfield('?' . join('&', $get))
      ->buildOauth($timeline_url, 'GET');

    return json_decode($api->performRequest());
  }

  /**
   * @param stdClass $tweet
   *   Tweet object
   * @return WP_Twitter_Stream_Parser
   *   The parser.
   */
  protected function get_parser($tweet) {
    return new WP_Twitter_Stream_Parser($tweet);
  }
}
