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
  const VERSION = '1.0.0:11';

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
   * Tweet table field formats.
   * @var array
   */
  static protected $field_format = array(
    'twitter_id' => '%d',
    'time' => '%s',
    'last_checked' => '%s',
    'rt' => '%d',
    'reply' => '%d',
    'author_id' => '%d',
    'author' => '%s',
    'parser_version' => '%s',
    'text' => '%s',
    'display' => '%s',
    'raw_data' => '%s',
  );

  static protected $cache = array();

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
        last_checked datetime,
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
   * @param array $data
   *   The parsed tweet data.
   * @return bool|int
   *   If saving was successful will return the local tweet ID otherwise FALSE.
   */
  static public function save_tweet($data) {
    $row = array();
    $formats = array();
    foreach (self::$field_format as $col => $format) {
      if (!isset($data[$col])) {
        continue;
      }
      $row[$col] = $data[$col];
      $formats[] = $format;
    }

    if (self::wpdb()->insert(self::$tweets, $row, $formats)) {
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
   * Get full list of hashtags.
   * @return array.
   */
  static public function get_hashtags() {
    $table = self::$hashtags;
    $query = "SELECT * FROM {$table} ORDER BY hashtag";
    return self::wpdb()->get_results($query, OBJECT);
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
   * @param int $tid
   *   Tweet id.
   * @param array $hashtag_ids
   *   List of hashtag IDs.
   */
  static public function add_hashtags($tid, $hashtag_ids) {
    foreach ($hashtag_ids as $hid) {
      self::wpdb()->insert(self::$tw2ht, array(
        'tid' => $tid,
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
    $sql = "
      SELECT
        twitter_id
      FROM
        {$table}
      WHERE
        last_checked IS NOT NULL
        AND last_checked <> '0000-00-00 00:00:00'
      ORDER BY time DESC
      LIMIT 1";
    return self::wpdb()->get_var($sql);
  }

  /**
   * Get a row from tweets table.
   *
   * @param int $id
   *   The local tweet id.
   *
   * @return array|NULL
   */
  static public function get_tweet($id) {
    if (isset(self::$cache[__FUNCTION__][$id])) {
      return self::$cache[__FUNCTION__][$id];
    }

    $table = self::$tweets;
    $query = self::wpdb()->prepare("SELECT * FROM {$table} WHERE id = %s", intval($id));
    self::$cache[__FUNCTION__][$id] = self::wpdb()->get_row($query, ARRAY_A);
    return self::$cache[__FUNCTION__][$id];
  }

  /**
   * Get tweets hashtags.
   *
   * @param int $id
   *   The local tweet id.
   *
   * @return array|NULL
   */
  static public function get_hashtag_for_tweet($id) {
    if (isset(self::$cache[__FUNCTION__][$id])) {
      return self::$cache[__FUNCTION__][$id];
    }

    $tw2ht = self::$tw2ht;
    $hashtags = self::$hashtags;
    $sql = "
      SELECT
        h.*
      FROM
        {$tw2ht} AS c
        LEFT JOIN {$hashtags} AS h ON (h.id = c.hid)
      WHERE
        c.tid = %s
      ORDER BY h.id";
    $query = self::wpdb()->prepare($sql, intval($id));
    self::$cache[__FUNCTION__][$id] = self::wpdb()->get_results($query, OBJECT);
    return self::$cache[__FUNCTION__][$id];
  }

  /**
   * Updates the display value of the tweet after new parser version run.
   * @param int $id
   *   The local tweet id.
   * @param array $row
   *   The new values array with two key:
   *   - display: the new display value
   *   - parser_version: the version string of the new parser.
   */
  static public function update_tweet_display($id, $row) {
    $data = array(
      'display' => $row['display'],
      'parser_version' => $row['parser_version'],
    );
    $where = array('id' => $id);
    $format = array('%s', '%s');
    $where_format = array('%d');
    self::wpdb()->update(self::$tweets, $data, $where, $format, $where_format);
  }

  /**
   * Read tweets from DB.
   *
   * @param array $instance
   *   Query settings contains the following keys:
   *   - count: (optional) The query limit. Default: 10.
   *
   * @return mixed
   */
  static public function get_tweets($instance) {
    $tweets = self::$tweets;
    $sql = "
      SELECT
        *
      FROM
        {$tweets}
      WHERE
        last_checked IS NOT NULL
      ORDER BY time DESC
      LIMIT %d";

    $count = 10;
    if (isset($instance['count']) && ($_count = intval($instance['count'])) > 0) {
      $count = $_count;
    }

    $query = self::wpdb()->prepare($sql, $count);
    $result = self::wpdb()->get_results($query, ARRAY_A);
    foreach ($result as $row) {
      self::$cache['get_tweet'][$row['id']] = $row;
    }
    return $result;
  }

  /**
   * Mark tweet with given id as deleted.
   * @param int $id
   */
  static public function hide($id) {
    $table = self::$tweets;
    $sql = "
      UPDATE
        {$table}
      SET
        last_checked = NULL
      WHERE id = %d";
    $query = self::wpdb()->prepare($sql, $id);
    self::wpdb()->query($query);
  }

  /**
   * Update last_checked value of the tweet with given id.
   * @param int $id
   */
  static public function still_exists($id) {
    $time_zone = new DateTimeZone(get_option('timezone_string'));
    $now = new DateTime('now', $time_zone);
    $table = self::$tweets;
    $sql = "
      UPDATE
        {$table}
      SET
        last_checked = %s
      WHERE id = %d";
    $query = self::wpdb()->prepare($sql, $now->format('Y-m-d H:i:s'), $id);
    self::wpdb()->query($query);
  }
}
