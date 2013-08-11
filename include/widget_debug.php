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
 * WP Twitter Stream Widget Debug.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Widget_Debug {

  const DUMP_TWEET_DISABLE = 0;
  const DUMP_TWEET_OBJECT = 1;
  const DUMP_TWEET_RAW_DATA = 2;
  const DUMP_TWEET_RAW_DATA_ENTITES = 3;

  /** @var  WP_Twitter_Stream_Widget */
  protected $widget;

  /** @var array */
  protected $instance_settings;

  /**
   * Constructor
   * @param WP_Twitter_Stream_Widget $widget
   */
  public function __construct(WP_Twitter_Stream_Widget $widget) {
    $this->widget = $widget;
    $this->instance_settings = $this->widget->get_instance_settings();
  }

  /**
   * Get debug.
   * @return bool|string
   * @todo Refactor this mess!
   */
  public function get_debug() {
    if (!WP_Twitter_Stream_Plugin::is_debug_mode_enabled()) {
      return false;
    }

    $out = '';
    if ($this->instance_settings['dump_settings']) {
      $dump = new WP_Twitter_Stream_Dump($this->instance_settings);
      $out .=
        '<section class="instance">' .
        '<h4>' . __('Instance settings', WP_Twitter_Stream_Plugin::SLUG) . '</h4>' .
        $dump->output() .
        '</section>';
    }

    if ($this->instance_settings['dump_query']) {
      $queries = array();
      foreach ($this->widget->get_queries() as $query) {
        $queries[] = (string) $query['query'];
      }

      $dump = new WP_Twitter_Stream_Dump($queries);
      $out .=
        '<section class="query">' .
        '<h4>' . __('Query', WP_Twitter_Stream_Plugin::SLUG) . '</h4>' .
        $dump->output() .
        '</section>';
    }

    if ($this->instance_settings['dump_templates']) {
      $used_template = '<b>' . $this->widget->get_template_to_use() . '</b>';
      if ($this->widget->get_template_to_use() == 'views/widget.php') {
        $used_template .=
          ' <small><em>' .
          __('default template deliverd by plugin', WP_Twitter_Stream_Plugin::SLUG) .
          '</em></small>';
      }

      $dump = new WP_Twitter_Stream_Dump($this->widget->get_template_names());
      $out .=
        '<section class="templates">' .
        '<h4>' . __('Template candidates', WP_Twitter_Stream_Plugin::SLUG) . '</h4>' .
        $dump->output() .
        '<h4>' . __('Used template', WP_Twitter_Stream_Plugin::SLUG) . '</h4> ' .
        $used_template .
        '</section>';

    }

    if ($this->instance_settings['dump_tweet_objects']) {
      $dump = false;
      if (self::DUMP_TWEET_OBJECT === $this->instance_settings['dump_tweet_objects']) {
        $dump = new WP_Twitter_Stream_Dump($this->widget->get_tweets());
      }
      elseif (self::DUMP_TWEET_RAW_DATA === $this->instance_settings['dump_tweet_objects']) {
        $tweets = array();
        foreach ($this->widget->get_tweets() as $tweet) {
          /** @var WP_Twitter_Stream_Tweet $tweet */
          $tweets[] = $tweet->get_data();
        }
        $dump = new WP_Twitter_Stream_Dump($tweets);
      }

      if ($dump) {
        $out .=
          '<section class="instance">' .
          '<h4>' . __('Tweet objects', WP_Twitter_Stream_Plugin::SLUG) . '</h4>' .
          $dump->output() .
          '</section>';
      }
    }

    if ($out) {
      $out =
        '<footer class="debug">' .
        '<h3>' . __('Debug', WP_Twitter_Stream_Plugin::SLUG) . '</h3>' .
        $out .
        '</footer>';
    }
    return $out;
  }
}
