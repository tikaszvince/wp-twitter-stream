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
 * WP Twitter Stream Tweet.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Tweet {

  /** @var array */
  protected $data;

  /** @var array */
  protected $row;

  /** @var int */
  protected $id;

  /** @var array */
  protected $hashtags;

  /** @var array */
  protected $hashtag_ids;

  /**
   * Constructor.
   *
   * @param null $id
   *   If this is a local tweet id tweet data will read from DB and tweet
   *   properties will set.
   */
  public function __construct($id = null) {
    if (isset($id)) {
      $this->row = WP_Twitter_Stream_Db::get_tweet($id);
      $this->id = $this->row['id'];
      $this->data = $this->get_data();
      $this->read_hashtags();
    }
  }

  /**
   * Read hashtags from DB.
   */
  protected function read_hashtags() {
    if ($this->id) {
      $hashtags = WP_Twitter_Stream_Db::get_hashtag_for_tweet($this->id);
      $this->hashtag_ids = array();
      $this->hashtags = array();
      foreach ($hashtags as $row) {
        $this->hashtag_ids[] = $row->id;
        $this->hashtags[] = $row->hashtag;
      }
    }
  }

  /**
   * Set tweet data from array.
   * @param array $data
   * @see WP_Twitter_Stream_Parser::get_parsed_row()
   */
  public function set_from_array($data) {
    $this->row = $data['tweet'];
    $this->data = $this->get_data();
    $this->hashtags = $data['hashtags'];
  }

  /**
   * Save new tweet data into DB.
   */
  public function insert() {
    // Save new hashtags if any and get the list of hashtag IDs and add the
    // hastag ID list to parsed data.
    $this->hashtag_ids = WP_Twitter_Stream_Db::save_hashtags($this->hashtags);

    // Save tweet and add the new local ID to parsed data.
    $this->id = WP_Twitter_Stream_Db::save_tweet($this->row);
    if ($this->id) {
      // Connect tweet with hashtags. We will use these connections in
      // filtered twitter stream widget.
      WP_Twitter_Stream_Db::add_hashtags($this->id, $this->hashtag_ids);
    }
  }

  /**
   * Returns the display of this tweet
   *
   * If the tweet is not exists returns NULL.
   * If this tweet was parsed with an older version re parse it.
   *
   * @param bool $force_re_parsing
   * @return null|string
   */
  public function display($force_re_parsing = false) {
    if ($this->is_deleted()) {
      return null;
    }

    $display = $this->row['display'];
    if (
      $this->row['parser_version'] != WP_Twitter_Stream_Parser::get_version()
      || $force_re_parsing
    ) {
      $parser = new WP_Twitter_Stream_Parser($this->data);
      $display = $parser->display();
      $row = $parser->get_parsed_row();
      WP_Twitter_Stream_Db::update_tweet_display($this->id, $row['tweet']);
    }
    return $display;
  }

  /**
   * Checks the tweet still exists.
   * @return bool
   */
  public function is_deleted() {
    if (empty($this->row['last_checked'])) {
      return true;
    }

    $deleted = false;
    if ($this->should_recheck()) {
      if ($deleted = !$this->check_still_exists()) {
        WP_Twitter_Stream_Db::hide($this->id);
      }
      else {
        WP_Twitter_Stream_Db::still_exists($this->id);
      }
    }
    return $deleted;
  }

  /**
   * Check the last existence examination was later then 30 minutes.
   * @return bool
   */
  protected function should_recheck() {
    $time_zone = new DateTimeZone(get_option('timezone_string'));
    $time = new DateTime($this->row['last_checked'], $time_zone);
    $now = new DateTime('now', $time_zone);
    $time->modify('+30 minute');
    return $time < $now;
  }

  /**
   * Check Twitter API tweet still exists.
   * @return bool
   */
  public function check_still_exists() {
    $api = WP_Twitter_Stream_Plugin::get_instance()->get_api();

    $url = 'https://api.twitter.com/1.1/statuses/show.json';
    $get = array(
      'id=' . $this->row['twitter_id']
    );
    $api
      ->setGetfield('?' . join('&', $get))
      ->buildOauth($url, 'GET');

    $response = json_decode($api->performRequest());
    if (
      isset($response->errors)
      && isset($response->errors[0])
      && isset($response->errors[0]->code)
      && $response->errors[0]->code == 34
    ) {
      return false;
    }
    return true;
  }

  /**
   * Get raw data got from Twitter API.
   * @return array|mixed
   */
  public function get_data() {
    return json_decode($this->row['raw_data']);
  }
}
