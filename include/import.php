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

require_once dirname(__FILE__) . '/../twitter-api-php/TwitterAPIExchange.php';

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
  /** @var TwitterAPIExchange */
  protected $api;

  /**
   * Set importer options.
   *
   * @param array $options
   *  The options array.
   * @return WP_Twitter_Stream_Import
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * Perform request to Twitter API and save tweets into database.
   */
  public function doImport() {
    // Perform request.
    $tweets = $this->getTweets();
    foreach ($tweets as $tweet) {
      // We need a new parser.
      $parser = $this->getParser($tweet);
      $data = $parser->get_parsed_row();

      // Save new hashtags if any and get the list of hashtag IDs and add the
      // hastag ID list to parsed data.
      $data['hashtag_ids'] = WP_Twitter_Stream_Db::save_hashtags($data['hashtags']);

      // Save tweet and add the new local ID to parsed data.
      $data['tweet_id'] = WP_Twitter_Stream_Db::save_tweet($data['tweet']);
      if ($data['tweet_id']) {
        // Connect tweet with hashtags. We will use these connections in
        // filtered twitter stream widget.
        WP_Twitter_Stream_Db::add_hashtags($data['tweet_id'], $data['hashtag_ids']);
      }
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
  protected function getTweets() {
    $api = $this->getApi();

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
   * @return TwitterAPIExchange
   * @throws Exception
   */
  protected function getApi() {
    if (isset($this->api)) {
      return $this->api;
    }

    if (!isset($this->options)) {
      throw new Exception('Importer options are missing');
    }

    return $this->api = new TwitterAPIExchange($this->options);
  }

  /**
   * @param stdClass $tweet
   *   Tweet object
   * @return WP_Twitter_Stream_Parser
   *   The parser.
   */
  protected function getParser($tweet) {
    return new WP_Twitter_Stream_Parser($tweet);
  }
}
