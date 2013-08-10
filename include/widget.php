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
    'template' => 'auto',
    'title' => null,
    'filter_mode' => WP_Twitter_Stream_Query::FILTER_MODE_ALL,
    'hashtags' => array(),
    'dump_query' => false,
  );

  /**
   * Current instance settings
   * @var array
   */
  public $instance_settings;

  /**
   * Current instance args
   * @var array
   */
  public $instance_args;

  /**
   * DB Queries
   * @var array
   */
  protected $queries = array();

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
    $this->instance_settings = wp_parse_args(
      (array) $instance,
      $this->default_settings
    );
    $this->instance_args = $args;

    extract($this->instance_args, EXTR_SKIP);

    $this->reset();
    $tweets = $this->get_tweets();
    $templates = $this->get_template_names();

    if (
      $this->instance_settings['template'] == 'auto'
      || !$template_file = locate_template($templates)
    ) {
      $template_file = 'views/widget.php';
    }
    $widget = $this;
    $display_title = $this->display_title();

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
    $instance['dump_query'] = (bool) $instance['dump_query'];

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
    $this->instance_settings = wp_parse_args(
      (array) $instance,
      $this->default_settings
    );

    $filter_modes = array(
      WP_Twitter_Stream_Query::FILTER_MODE_ALL => __('Show All', WP_Twitter_Stream_Plugin::SLUG),
      WP_Twitter_Stream_Query::FILTER_MODE_INCLUDE => __('Show tweets with hastags', WP_Twitter_Stream_Plugin::SLUG),
      WP_Twitter_Stream_Query::FILTER_MODE_EXCLUDE => __('Hide tweets with hastags', WP_Twitter_Stream_Plugin::SLUG),
    );
    $hashtags = $this->get_hashtags();
    $widget = $this;
    $template_candidates = $this->get_template_names();
    $templates = $this->get_templates($template_candidates);

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
   * @return array
   *   List of tweets to display.
   *
   * @see WP_Twitter_Stream_Db::get_tweets()
   */
  public function get_tweets() {
    // Will read until tweet count reach the number the widget perform, but
    // we will try to fill the list only 3 times.
    $count = 10;
    if (
      isset($this->instance_settings['count'])
      && ($_count = intval($this->instance_settings['count'])) > 0
    ) {
      $count = $_count;
    }

    $max_read = 3;
    do {
      $this->read_tries++;
      $this->read_tweets();
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
   * @see WP_Twitter_Stream_Db::get_tweets()
   */
  protected function read_tweets() {
    $query = WP_Twitter_Stream_Db::get_tweets($this->instance_settings);
    $this->queries[] = $query;
    foreach ($query['result'] as $row) {
      $tweet = new WP_Twitter_Stream_Tweet($row['id']);
      if ($tweet->is_deleted()) {
        continue;
      }
      $this->tweets[] = $tweet;

      if (count($this->tweets) >= $this->instance_settings['count']) {
        break;
      }
    }
  }

  /**
   * Get candidate template names.
   *
   * @return array
   *   The list of template names.
   */
  protected function get_template_names() {
    $templates = array(
      'widget-twitter-stream.php',
      'widget-twitter-stream--number-' . $this->number. '.php',
    );
    $id = $this->number;
    if (isset($this->instance_settings['id']) && trim(esc_attr($this->instance_settings['id']))) {
      $id = trim(esc_attr($this->instance_settings['id']));
    }
    $templates[] = 'widget-twitter-stream-' . $id . '.php';
    if (
      isset($this->instance_settings['template'])
      && $this->instance_settings['template']
      && $this->instance_settings['template'] != 'auto'
    ) {
      $templates[] = $this->instance_settings['template'];
    }
    return array_reverse($templates);
  }

  /**
   * Find available template files.
   *
   * @param array $template_candidates
   *   List of template candidates.
   *
   * @return array
   *   List of available templates.
   */
  protected function get_templates($template_candidates) {
    // Use auto discovery to find template file to use.
    $files = array(
      'auto' => __('Use auto discovery', WP_Twitter_Stream_Plugin::SLUG),
    );

    // If theme has a default template we could use it.
    if (locate_template('widget-twitter-stream.php')) {
      $files['system_default'] = __('Use system default template', WP_Twitter_Stream_Plugin::SLUG);
    }

    foreach ($template_candidates as $template) {
      if ($file = locate_template($template)) {
        if ('widget-twitter-stream.php' == $template) {
          continue;
        }
        // If candidate is available could use it.
        $files[$template] = $template;
      }
    }

    // If theme define multiple templates we could use them too
    $theme = glob(TEMPLATEPATH . '/wp-twitter-stream/*');
    foreach ($theme as $file) {
      $files['wp-twitter-stream/' . basename($file)] = basename($file);
    }

    return $files;
  }

  /**
   * Render widget title.
   *
   * @return string
   *   The rendered widget title.
   */
  protected function display_title() {
    if (isset($this->instance_settings['title']) && $this->instance_settings['title']) {
      return $this->instance_args['before_title'] . $this->instance_settings['title'] . $this->instance_args['after_title'];
    }
    return '';
  }

  /**
   * Get full list of hashtags.
   * @return array
   */
  protected function get_hashtags() {
    $tags = WP_Twitter_Stream_Db::get_hashtags();
    usort($tags, array($this, '_sort_tags'));
    return $tags;
  }

  /**
   * Compare function
   * @link http://docs.php.net/usort
   */
  protected function _sort_tags($a, $b) {
    $a_selected = in_array($a->id, $this->instance_settings['hashtags']);
    $b_selected = in_array($b->id, $this->instance_settings['hashtags']);
    if ($a_selected != $b_selected) {
      return $a_selected && !$b_selected ? -1 : 1;
    }
    return strcmp($a->hashtag, $b->hashtag);
  }
}
