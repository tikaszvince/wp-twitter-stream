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
 * WP Twitter Stream Widget.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Widget extends WP_Widget {

  /** Define filter mode constants */
  const FILTER_MODE_ALL = 0;
  const FILTER_MODE_EXCLUDE = 1;
  const FILTER_MODE_INCLUDE = 2;

  /**
   * Tweets to display
   * @var array
   */
  protected $tweets = array();

  /**
   * Read tries counter.
   * @see WP_Twitter_Stream_Widget::get_tweets()
   * @var int
   */
  protected $read_tries = 0;

  /**
   * Default settings
   * @var array
   */
  protected $default_settings = array(
    'count' => 10,
    'id' => null,
    'template' => null,
    'title' => null,
    'filter_mode' => self::FILTER_MODE_ALL,
    'hashtags' => array(),
  );

  /**
   * WP_Widget constructor.
   */
  public function WP_Twitter_Stream_Widget() {
    $this->__construct();
  }

  /**
   * Specifies the classname and description, instantiates the widget,
   * loads localization files, and includes necessary stylesheets and JavaScript.
   */
  public function __construct() {
    parent::__construct(
      WP_Twitter_Stream_Plugin::SLUG,
      __('Twitter Stream', WP_Twitter_Stream_Plugin::SLUG),
      array(
        'classname' => 'WP_Twitter_Stream_Widget',
        'description' => __('Displays twitter stream.', WP_Twitter_Stream_Plugin::SLUG)
      )
    );

    // Register site styles and scripts
    add_action('wp_enqueue_scripts', array($this, 'register_widget_styles'));
    add_action('wp_enqueue_scripts', array($this, 'register_widget_scripts'));
  }

  /**
   * Outputs the content of the widget.
   *
   * @param array $args The array of form elements
   * @param array $instance The current instance of the widget
   */
  public function widget($args, $instance) {
    extract($args, EXTR_SKIP);

    $this->reset();
    $tweets = $this->get_tweets($instance);
    $templates = $this->get_template_names($instance);
    if (!$template_file = locate_template($templates)) {
      $template_file = 'views/widget.php';
    }
    $widget = $this;
    $display_title = $this->display_title($args, $instance);

    require $template_file;
  }

  /**
   * Processes the widget's options to be saved.
   *
   * @param array $new_instance
   *   The previous instance of values before the update.
   * @param array $old_instance
   *   The new instance of values to be generated via the update.
   * @return array
   *   The new settings.
   */
  public function update($new_instance, $old_instance) {
    $instance = $old_instance;

    foreach ($this->default_settings as $field => $default) {
      $instance[$field] = isset($new_instance[$field]) ? $new_instance[$field] : false;
      if (!$instance[$field]) {
        $instance[$field] = $default;
      }
    }

    $instance['count'] = intval($instance['count']);
    if (!$instance['count']) {
      $instance['count'] = $this->default_settings['count'];
    }

    $instance['title'] = strip_tags($new_instance['title']);
    if (!$instance['title']) {
      $instance['title'] = null;
    }

    $instance['filter_mode'] = intval($instance['filter_mode']);

    return $instance;
  }

  /**
   * Generates the administration form for the widget.
   *
   * @param array $instance
   *   The array of keys and values for the widget.
   * @return void
   */
  public function form($instance) {
    $instance = wp_parse_args(
      (array) $instance,
      $this->default_settings
    );

    $filter_modes = array(
      self::FILTER_MODE_ALL => __('Show All', WP_Twitter_Stream_Plugin::SLUG),
      self::FILTER_MODE_INCLUDE => __('Show tweets with hastags', WP_Twitter_Stream_Plugin::SLUG),
      self::FILTER_MODE_EXCLUDE => __('Hide tweets with hastags', WP_Twitter_Stream_Plugin::SLUG),
    );
    $hashtags = $this->get_hashtags();
    $widget = $this;
    $templates = $this->get_template_names($instance);

    // Display the admin form
    include 'views/widget.form.php';
  }

  /**
   * Registers and enqueues widget-specific styles.
   */
  public function register_widget_styles() {
    wp_enqueue_style(
      'widget-' . WP_Twitter_Stream_Plugin::SLUG,
      plugins_url('wp-twitter-stream/css/widget.css')
    );
  }

  /**
   * Registers and enqueues widget-specific scripts.
   */
  public function register_widget_scripts() {
    wp_enqueue_script(
      'widget-' . WP_Twitter_Stream_Plugin::SLUG,
      plugins_url('wp-twitter-stream/js/widget.js'),
      array('jquery')
    );
  }

  /**
   * Get tweets to display
   *
   * @param array $instance
   *   Widget instance settings:
   *
   * @return array
   *   List of tweets to display.
   *
   * @see WP_Twitter_Stream_Db::get_tweets()
   */
  public function get_tweets($instance) {
    // Will read until tweet count reach the number the widget perform, but
    // we will try to fill the list only 3 times.
    $count = 10;
    if (isset($instance['count']) && ($_count = intval($instance['count'])) > 0) {
      $count = $_count;
    }

    $max_read = 3;
    do {
      $this->read_tries++;
      $this->read_tweets($instance);
    } while ($this->read_tries < $max_read && $count > count($this->tweets));

    return $this->tweets;
  }

  /**
   * Reset tweets to display.
   */
  protected function reset() {
    $this->tweets = array();
    $this->read_tries = 0;
  }

  /**
   * Read tweets from DB.
   *
   * @param array $instance
   *   Widget settings.
   *
   * @see WP_Twitter_Stream_Db::get_tweets()
   */
  protected function read_tweets($instance) {
    $results = WP_Twitter_Stream_Db::get_tweets($instance);
    foreach ($results as $row) {
      $tweet = new WP_Twitter_Stream_Tweet($row['id']);
      if ($tweet->is_deleted()) {
        continue;
      }
      $this->tweets[] = $tweet;
    }
  }

  /**
   * Get candidate template names.
   *
      * @param array $instance The current instance of the widget
   *
   * @return array
   *   The list of template names.
   */
  protected function get_template_names($instance) {
    $templates = array(
      'widget-twitter-stream.php',
      'widget-twitter-stream--number-' . $this->number. '.php',
    );
    $id = $this->number;
    if (isset($instance['id']) && trim(esc_attr($instance['id']))) {
      $id = trim(esc_attr($instance['id']));
    }
    $templates[] = 'widget-twitter-stream-' . $id . '.php';
    if (isset($instance['template']) && $instance['template']) {
      $templates[] = $instance['template'];
    }
    return array_reverse($templates);
  }

  /**
   * Render widget title.
   *
   * @param array $args The array of form elements
   * @param array $instance The current instance of the widget
   *
   * @return string
   *   The rendered widget title.
   */
  protected function display_title($args, $instance) {
    if (isset($instance['title']) && $instance['title']) {
      return $args['before_title'] . $instance['title'] . $args['after_title'];
    }
    return '';
  }

  /**
   * Get full list of hashtags.
   * @return array
   */
  protected function get_hashtags() {
    return WP_Twitter_Stream_Db::get_hashtags();
  }
}
