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
 * WP Twitter Stream Db.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Db {

  /**
   * DB version
   */
  const VERSION = '1.0.0:9';

  /**
   * Prefixed name of the tweets table.
   * @var string
   */
  static public $tweets;

  /**
   * Prefixed name of the hashtags table.
   * @var string
   */
  static public $hashtags;

  /**
   * Prefixed name of the tweets_to_hashtags table.
   * @var string
   */
  static public $tw2ht;

  /**
   * Prefix table name.
   *
   * @param string $table
   *  The base name of the table.
   *
   * @return string
   *   The prefixed name of the table.
   */
  static public function table($table) {
    return WP_Twitter_Stream_Db::wpdb()->prefix . $table;
  }

  /**
   * Get wpdb.
   * @return wpdb
   */
  static public function wpdb() {
    /** @var wpdb $wpdb */
    global $wpdb;
    return $wpdb;
  }

  /**
   * Get defined DB schema for plugin.
   *
   * @see dbDelta()
   * @link http://codex.wordpress.org/Creating_Tables_with_Plugins
   * @return array
   */
  static public function schema() {
    $tweets = self::$tweets = WP_Twitter_Stream_Db::table('wts_tweets');
    $hashtags = self::$hashtags = WP_Twitter_Stream_Db::table('wts_hashtags');
    $tw2ht = self::$tw2ht = WP_Twitter_Stream_Db::table('wts_tweet_to_hashtag');
    return array(
      "CREATE TABLE {$tweets} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        twitter_id BIGINT NOT NULL,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        rt TINYINT(1) DEFAULT 0 NOT NULL,
        reply TINYINT(1) DEFAULT 0 NOT NULL,
        author_id BIGINT NOT NULL,
        author CHAR(100) NOT NULL,
        parser_version CHAR(10) NOT NULL,
        text TEXT NOT NULL,
        display TEXT NOT NULL,
        raw_data TEXT NOT NULL,
        PRIMARY KEY id (id),
        UNIQUE KEY twitter_id (twitter_id)
      );",

      "CREATE TABLE {$hashtags} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        hashtag CHAR(100) NOT NULL,
        PRIMARY KEY id (id),
        UNIQUE KEY hashtag (hashtag)
      );",

      "CREATE TABLE {$tw2ht} (
        tid mediumint(9) NOT NULL,
        hid mediumint(9) NOT NULL,
        PRIMARY KEY id (tid, hid)
      );",
    );
  }

  /**
   * Save tweet data into database.
   *
   * @param array $row
   *   The parsed tweet data.
   * @return bool|int
   *   If saving was successful will return the local tweet ID otherwise FALSE.
   */
  static public function save_tweet($row) {
    // TODO: now we assume the $row has the fields in the same order as the
    // TODO: format array. We have to create a new array with the proper order
    // TODO: of the fields.
    $res = self::wpdb()->insert(
      self::$tweets,
      $row,
      array(
        '%d', // twitter_id
        '%s', // time
        '%d', // rt
        '%d', // reply
        '%d', // author_id
        '%s', // author
        '%s', // parser_version
        '%s', // text
        '%s', // display
        '%s', // raw_data
      )
    );
    if ($res) {
      return self::wpdb()->insert_id;
    }
    return false;
  }

  /**
   * Save new hashtags and returns the hashtag IDs.
   *
   * @param array $hashtags
   *   The list of hastags.
   *
   * @return array
   *   A new array where the keys are the given hastags and the values are the
   *   IDs of the hastags.
   */
  static public function save_hashtags($hashtags) {
    if (!isset($hashtags) || !is_array($hashtags) || empty($hashtags)) {
      return array();
    }
    $hashtag_ids = array();
    $rows = self::get_hashtags_by_text($hashtags);
    foreach ($rows as $row) {
      $hashtag_ids[$row->hashtag] = $row->id;
    }
    foreach ($hashtags as $tag) {
      if (!isset($hashtag_ids[strtolower($tag)])) {
        $hashtag_ids[$tag] = self::save_hashtag($tag);
      }
    }
    return $hashtag_ids;
  }

  /**
   * Search hashtags by name
   *
   * @param array $hashtags
   *  Get hashtag records by hashtag text.
   *
   * @return array
   *   The found hashtag records.
   */
  static public function get_hashtags_by_text($hashtags) {
    $table = self::$hashtags;
    $where = join(', ', array_fill(0, count($hashtags), '%s'));
    $query = "SELECT * FROM {$table} WHERE hashtag IN ($where)";
    $rows = self::wpdb()->get_results(self::wpdb()->prepare($query, $hashtags));
    foreach ($rows as $row) {
      $rows[intval($row->id)] = $row;
      $rows[intval($row->id)]->id = intval($row->id);
    }
    return $rows;
  }

  /**
   * Save new hashtag
   *
   * @param String $tag
   *
   * @return bool|int
   *   If inserting failed returns FALSE, else returns the ID of the new hashtag
   */
  static public function save_hashtag($tag) {
    if (self::wpdb()->insert(self::$hashtags, array('hashtag' => $tag))) {
      return self::wpdb()->insert_id;
    }
    return FALSE;
  }

  /**
   * Connect tweet with hashtag.
   *
   * @param array $data
   *   The parsed tweet data.
   */
  static public function add_hashtags($data) {
    foreach ($data['hashtag_ids'] as $hid) {
      self::wpdb()->insert(self::$tw2ht, array(
        'tid' => $data['tweet_id'],
        'hid' => $hid,
      ));
    }
  }

  /**
   * Find the twitter_id of latest imported tweet.
   * @return null|int
   */
  static public function get_latest_tweet_id() {
    $table = self::$tweets;
    return self::wpdb()->get_var("SELECT twitter_id FROM {$table} ORDER BY time DESC LIMIT 1");
  }
}