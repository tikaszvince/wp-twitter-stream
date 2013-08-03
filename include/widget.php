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

    /**
     * @var String $before_widget
     * @var String $after_widget
     */
    echo $before_widget;

    include 'views/widget.php';

    echo $after_widget;
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

    // TODO: Here is where you update your widget's old values with the new, incoming values

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
    // TODO: Define default values for your variables
    $instance = wp_parse_args(
      (array) $instance
    );

    // TODO: Store the values of the widget in their own variable

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
}
