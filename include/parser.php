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
 * WP Twitter Stream Parser.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Parser {

  /**
   * The original tweet data.
   * @var stdClass
   */
  protected $data;

  /**
   * The displayed tweet data.
   *
   * If current tweet is a retweet this will hold the data of the original tweet.
   * @var stdClass
   */
  protected $tweet;

  /**
   * The text of the tweet.
   * @var string
   */
  protected $text;

  /**
   * The replaced version of the tweet.
   * @var string
   */
  protected $replaced;

  /**
   * Collection of entities.
   * @var array
   */
  protected $replacements = array();

  /**
   * List of hashtags
   * @var array
   */
  protected $hashtags;

  /**
   * Collection of additional contents, photo, video, etc.
   * @var array
   */
  protected $additional_content = array();

  /**
   * Original tweet data.
   * @param stdClass $tweet
   */
  public function __construct($tweet) {
    $this->data = $tweet;
    $this->tweet = $this->data;
    if ($this->retweeted()) {
      $this->tweet = $tweet->retweeted_status;
    }
    $this->get_text();
  }

  /**
   * Get parsers version.
   * @return string
   */
  static public function get_version() {
    return WP_Twitter_Stream_Plugin::VERSION . ':1a';
  }

  /**
   * Builds tweet object from parsed data.
   * @return WP_Twitter_Stream_Tweet
   */
  public function get_tweet() {
    $tweet = new WP_Twitter_Stream_Tweet();
    $tweet->set_from_array($this->get_parsed_row());
    return $tweet;
  }

  /**
   * Get parsed tweet data.
   * @return array
   */
  public function get_parsed_row() {
    $time_zone = new DateTimeZone(get_option('timezone_string'));
    $time = new DateTime($this->data->created_at);
    $time->setTimezone($time_zone);
    $checked = new DateTime('now', $time_zone);
    return array(
      'tweet' => array(
        'twitter_id' => $this->tweet->id,
        'time' => $time->format('Y-m-d H:i:s'),
        'last_checked' => $checked->format('Y-m-d H:i:s'),
        'rt' => $this->retweeted(),
        'reply' => $this->is_reply(),
        'author_id' => $this->tweet->user->id,
        'author' => $this->tweet->user->screen_name,
        'parser_version' => $this->get_version(),
        'text' => $this->get_text(),
        'display' => $this->display(),
        'raw_data' => json_encode($this->data),
      ),
      'hashtags' => $this->get_hashtags(),
    );
  }

  /**
   * Get display of this tweet.
   * @return string
   */
  public function display() {
    if ($this->replaced) {
      return $this->replaced;
    }
    $this->get_text();
    $this->collect_replacements();
    return $this->replaced = $this->replace();
  }

  /**
   * Get additional contents.
   * @return array
   */
  public function get_additionals() {
    $this->display();
    return $this->additional_content;
  }

  /**
   * Get tweet content.
   * @return array
   *   The return array has two key:
   *   - display: the replaced content of the tweet. Every hashtag, link,
   *     username mention replaced with proper link.
   *     {@link|WP_Twitter_Stream_Parser::display()}
   *   - additional: the additional embedded contents.
   */
  public function get_content() {
    return array(
      'display' => $this->display(),
      'additional' => $this->additional_content,
    );
  }

  /**
   * Is this tweet a retweet?
   * @return bool
   */
  protected function retweeted() {
    return (
      isset($this->data->retweeted_status)
      && !empty($this->data->retweeted_status)
    );
  }

  /**
   * Checks is tweet a reply to an other.
   * @return bool
   */
  protected function is_reply() {
    return (
      $this->data->in_reply_to_status_id
      || $this->data->in_reply_to_status_id_str
      || $this->data->in_reply_to_user_id
      || $this->data->in_reply_to_user_id_str
      || $this->data->in_reply_to_screen_name
    );
  }

  /**
   * Get the original text of the tweet.
   * @return string
   */
  protected function get_text() {
    if (!isset($this->text)) {
      $this->text = $this->tweet->text;
    }
    return $this->text;
  }

  /**
   * Get list of hashtags.
   * @return array
   */
  protected function get_hashtags() {
    if (isset($this->hashtags) && !empty($this->hashtags)) {
      return $this->hashtags;
    }

    foreach ($this->tweet->entities->hashtags as $entity) {
      $this->hashtags[] = strtolower($entity->text);
    }
    return $this->hashtags;
  }

  /**
   * Collects replacements for display.
   * @return array
   */
  protected function collect_replacements() {
    if (isset($this->replacements) && !empty($this->replacements)) {
      return $this->replacements;
    }

    foreach ($this->tweet->entities as $type => $entities) {
      if (empty($entities)) {
        continue;
      }

      switch ($type) {
        case 'urls':
          $this->collect_url_entities();
          break;

        case 'hashtags':
          $this->collect_hashtag_entities();
          break;

        case 'symbols':
          $this->collect_symbol_entities();
          break;

        case 'user_mentions':
          $this->collect_user_mention_entities();
          break;

        case 'media':
          $this->collect_media_entities();
          break;
      }
    }

    if (count($this->replacements) > 1) {
      usort($this->replacements, array($this, 'sort_replacements'));
    }
    return $this->replacements;
  }

  /**
   * Collect url entities for replacement.
   */
  protected function collect_url_entities() {
    foreach ($this->tweet->entities->urls as $url) {
      $this->replacements[] = array(
        'search' => $url->url,
        'replace' => $this->url_link($url),
        'indices' => $url->indices,
      );
    }
  }

  /**
   * Collect hashtag entities for replacement.
   */
  protected function collect_hashtag_entities() {
    foreach ($this->tweet->entities->hashtags as $hashtag) {
      $this->replacements[] = array(
        'search' => '#' . $hashtag->text,
        'replace' => $this->hashtag_link($hashtag),
        'indices' => $hashtag->indices,
      );
    }
  }

  /**
   * Collect symbol entities for replacement.
   * @todo Missing functionality
   */
  protected function collect_symbol_entities() {
    // TODO
    foreach ($this->tweet->entities->symbols as $symbol) {

    }
  }

  /**
   * Collect user mention entities for replacement.
   */
  protected function collect_user_mention_entities() {
    foreach ($this->tweet->entities->user_mentions as $mention) {
      $this->replacements[] = array(
        'search' => '@' . $mention->screen_name,
        'replace' => $this->mention_link($mention),
        'indices' => $mention->indices,
      );
    }
  }

  /**
   * Collect media entities for replacement.
   */
  protected function collect_media_entities() {
    foreach ($this->tweet->entities->media as $media) {
      // TODO
    }
  }

  /**
   * Sorting function.
   */
  protected function sort_replacements($a, $b) {
    $ina = $a['indices'];
    $inb = $b['indices'];

    if ($ina[0] == $inb[0]) {
      if ($ina[1] == $inb[1]) {
        return 0;
      }
      return ($ina[1] < $inb[1]) ? -1 : 1;
    }
    return ($ina[0] < $inb[0]) ? -1 : 1;
  }

  /**
   * Replace replacements with links.
   * @return string
   */
  protected function replace() {
    $text = $this->get_text();
    $count = count($this->replacements);
    if ($count) {
      // Replacements are sorted by position. We replace substrings one by one.
      // We replace them back forward so we can use indices got from twitter api.
      for ($i = $count -1; $i >= 0; $i--) {
        $replace = $this->replacements[$i];
        $start = $replace['indices'][0];
        $end = $replace['indices'][1] - $start;
        $text = $this->substr_replace($text, $replace['replace'], $start, $end);
      }
    }
    return $text;
  }

  /**
   * Creates a link
   *
   * @param string $url
   *   The link URL
   * @param string $text
   *   The link label.
   * @param string $class
   *   The class for link.
   * @param string null $title
   *   The value of title attribute.
   *
   * @return string
   */
  protected function _link($url, $text, $class, $title = null) {
    if (isset($title)) {
      $title = ' title="' . esc_attr($title) . '"';
    }
    return '<a target="_blank" class="' . $class . '" href="' . $url . '"' . $title . '>' . $text . '</a>';
  }

  /**
   * Creates an url link for replacement.
   * @param stdClass $url
   * @return string
   */
  protected function url_link($url) {
    return $this->_link($url->expanded_url, $url->display_url, 'link', $url->expanded_url);
  }

  /**
   * Creates a hashtag link for replacement.
   * @param stdClass $hashtag
   * @return string
   */
  protected function hashtag_link($hashtag) {
    return $this->_link(
      'https://twitter.com/search?q=%23' . $hashtag->text . '&src=hash',
      '#' . $hashtag->text,
      'hashtag hashtag-' . $hashtag->text
    );
  }

  /**
   * Creates a mention link for replacement.
   * @param stdClass $mention
   * @return string
   */
  protected function mention_link($mention) {
    return $this->_link(
      'https://twitter.com/' . $mention->screen_name,
      '@' . $mention->screen_name,
      'mention',
      $mention->name
    );
  }

  /**
   * Multibyte safe substr_replace implementation
   *
   * @link http://docs.php.net/substr_replace#90146
   * @link http://docs.php.net/substr_replace
   *
   * @param string $string
   *   The input string
   * @param string $replacement
   *   The replacement string
   * @param int $start
   *   If start is positive, the replacing will begin at the start'th offset
   *   into string.
   *   If start is negative, the replacing will begin at the start'th character
   *   from the end of string.
   * @param int $length
   *   If given and is positive, it represents the length of the portion of
   *   string which is to be replaced. If it is negative, it represents the
   *   number of characters from the end of string at which to stop replacing.
   *   If it is not given, then it will default to strlen( string );
   *   i.e. end the replacing at the end of string. Of course, if length is
   *   zero then this function will have the effect of inserting replacement
   *   into string at the given start offset.
   * @param string $encoding
   *   The encoding parameter is the character encoding. If it is omitted,
   *   the internal character encoding value will be used.
   *
   * @return mixed|string
   */
  protected function substr_replace($string, $replacement, $start, $length = null, $encoding = null) {
    if (extension_loaded('mbstring') !== true) {
      return !isset($length)
        ? substr_replace($string, $replacement, $start)
        : substr_replace($string, $replacement, $start, $length);
    }

    $string_length = !isset($encoding)
      ? mb_strlen($string)
      : mb_strlen($string, $encoding);

    if ($start < 0) {
      $start = max(0, $string_length + $start);
    }
    elseif ($start > $string_length) {
      $start = $string_length;
    }

    if ($length < 0) {
      $length = max(0, $string_length - $start + $length);
    }
    elseif (!isset($length) || $length > $string_length) {
      $length = $string_length;
    }

    if ($start + $length > $string_length) {
      $length = $string_length - $start;
    }

    if (!isset($encoding)) {
      $before_replacement = mb_substr($string, 0, $start);
      $after_replacement = mb_substr($string, $start + $length, $string_length - $start - $length);
    }
    else {
      $before_replacement = mb_substr($string, 0, $start, $encoding);
      $after_replacement = mb_substr($string, $start + $length, $string_length - $start - $length, $encoding);
    }

    return $before_replacement . $replacement . $after_replacement;
  }
}
