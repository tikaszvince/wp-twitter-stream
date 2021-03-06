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

// Register hooks that are fired when the plugin is activated, deactivated,
// and uninstalled, respectively.
register_activation_hook(__FILE__, array('WP_Twitter_Stream_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Twitter_Stream_Plugin', 'deactivate'));
if (WP_Twitter_Stream_Plugin::is_debug_mode_enabled()) {
  ini_set('display_errors', 1);
}

/**
 * Plugin class.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 */
class WP_Twitter_Stream_Plugin {

  /**
   * Unique identifier for your plugin.
   *
   * @since 1.0.0
   * @var string
   */
  const SLUG = 'wp-twitter-stream';

  /**
   * Plugin version, used for cache-busting of style and script file references.
   *
   * @since 1.0.0
   * @var string
   */
  const VERSION = '1.0.0';

  /**
   * Instance of this class.
   *
   * @since 1.0.0
   * @var WP_Twitter_Stream_Plugin
   */
  protected static $instance = null;

  /**
   * Slug of the plugin screen.
   *
   * @since 1.0.0
   * @var string
   */
  protected $plugin_screen_hook_suffix = null;

  /**
   * Option fields.
   * @var array
   */
  protected $fields = array(
    'oauth_access_token' => 'OAuth Access token',
    'oauth_access_token_secret' => 'OAuth Access token secret',
    'consumer_key' => 'Consumer key',
    'consumer_secret' => 'Consumer secret',
    'update_frequency' => 'Update frequency',
    'screen_name' => 'Users screen name',
    'debug_mode' => 'Enable debug mode',
  );

  /** @var TwitterAPIExchange */
  protected $api;

  /** @var array */
  protected $api_options;

  /**
   * Initialize the plugin by setting localization, filters, and administration functions.
   *
   * @since 1.0.0
   */
  private function __construct() {
    WP_Twitter_Stream_Cron::schedule();

    add_action('init', array($this, 'init'));
    add_action('plugins_loaded', array($this, 'plugins_loaded'));
    add_action('widgets_init', array($this, 'widgets_init'));

    add_action('admin_init', array($this, 'admin_init'));

    // Add the options page and menu item.
    add_action('admin_menu', array($this, 'admin_menu'));

    // Load admin style sheet and JavaScript.
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

    // Load public-facing style sheet and JavaScript.
    add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
  }

  /**
   * Return an instance of this class.
   *
   * @since 1.0.0
   * @return WP_Twitter_Stream_Plugin
   *   A single instance of this class.
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if (null == self::$instance) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Get instance of Twitter API.
   * @return TwitterAPIExchange
   * @throws Exception
   */
  public function get_api() {
    if (isset($this->api)) {
      return $this->api;
    }
    require_once dirname(__FILE__) . '/../libs/twitter-api-php/TwitterAPIExchange.php';
    return $this->api = new TwitterAPIExchange($this->get_api_options());
  }

  /**
   * Get API options.
   * @return array
   */
  public function get_api_options() {
    $options = get_option(WP_Twitter_Stream_Plugin::SLUG);
    $this->api_options = array(
      'oauth_access_token' => $options['oauth_access_token'],
      'oauth_access_token_secret' => $options['oauth_access_token_secret'],
      'consumer_key' => $options['consumer_key'],
      'consumer_secret' => $options['consumer_secret'],
      'screen_name' => $options['screen_name'],
    );
    return $this->api_options;
  }

  /**
   * Fired when the plugin is activated.
   *
   * @since 1.0.0
   * @param boolean $network_wide
   *   True if WPMU superadmin uses "Network Activate" action,
   *   false if WPMU is disabled or plugin is activated on an individual blog.
   */
  public static function activate($network_wide) {
    WP_Twitter_Stream_Cron::getInstance()->activate();
    WP_Twitter_Stream_Cron::re_schedule_event();
  }

  /**
   * Fired when the plugin is deactivated.
   *
   * @since 1.0.0
   * @param boolean $network_wide
   *   True if WPMU superadmin uses "Network Deactivate" action,
   *   false if WPMU is disabled or plugin is deactivated on an individual blog.
   */
  public static function deactivate($network_wide) {
    WP_Twitter_Stream_Cron::getInstance()->deactivate();
    WP_Twitter_Stream_Cron::clear_schedule();
  }

  /**
   * Action: Hook:plugin_loaded
   */
  public function plugins_loaded() {
    WP_Twitter_Stream_Install::get_instance()->update();
  }

  /**
   * Action: Hook:init
   */
  public function init() {
    // Load plugin text domain
    $this->load_plugin_textdomain();
  }

  /**
   * Action: Hook:widgets_init
   */
  public function widgets_init() {
    register_widget('WP_Twitter_Stream_Widget');
  }

  /**
   * Action: Hook:admin_init
   */
  public function admin_init() {
    $this->register_options();
  }

  /**
   * Load the plugin text domain for translation.
   *
   * @since 1.0.0
   */
  public function load_plugin_textdomain() {
    $domain = self::SLUG;
    $locale = apply_filters('plugin_locale', get_locale(), $domain);

    load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
    load_plugin_textdomain($domain, FALSE, dirname(dirname(__FILE__)) . '/lang/');
  }

  /**
   * Register and enqueue admin-specific style sheet.
   *
   * @since 1.0.0
   * @return null
   *   Return early if no settings page is registered.
   */
  public function admin_enqueue_scripts() {
    wp_enqueue_style(
      self::SLUG . '-admin-styles',
      plugins_url('../css/admin.css', __FILE__),
      array(),
      self::VERSION
    );

    wp_enqueue_script(
      self::SLUG . '-admin-script',
      plugins_url('../js/admin.js', __FILE__),
      array('jquery'),
      self::VERSION
    );
  }

  /**
   * Register and enqueue public-facing style sheet.
   *
   * @since 1.0.0
   */
  public function wp_enqueue_scripts() {
    wp_enqueue_style(
      self::SLUG . '-plugin-styles',
      plugins_url('../css/public.css', __FILE__),
      array(),
      self::VERSION
    );

    wp_enqueue_script(
      self::SLUG . '-plugin-script',
      plugins_url('../js/public.js', __FILE__),
      array('jquery'),
      self::VERSION
    );
  }

  /**
   * Register the administration menu for this plugin into the WordPress Dashboard menu.
   *
   * @since 1.0.0
   */
  public function admin_menu() {
    $this->plugin_screen_hook_suffix = add_options_page(
      __('WP Twitter Stream', self::SLUG),
      __('Twitter Stream', self::SLUG),
      'administrator',
      self::SLUG,
      array($this, 'display_plugin_admin_page')
    );
  }

  protected function register_options() {
    add_settings_section(
      self::SLUG,
      'OAuth settings',
      array($this, 'settings_section_oauth'),
      self::SLUG
    );

    if (false === get_option(self::SLUG)) {
      add_option(self::SLUG, array_fill_keys(array_keys($this->fields), ''));
    }

    register_setting(self::SLUG, self::SLUG);
    foreach ($this->fields as $id => $label) {
      add_settings_field(
        $id,
        $label,
        array($this, '_setting_' . $id),
        self::SLUG,
        self::SLUG
      );
    }

    if (!empty($_POST)) {
      WP_Twitter_Stream_Cron::clear_schedule();
    }
    else {
      WP_Twitter_Stream_Cron::re_schedule_event();
    }
  }

  /**
   * Render the settings page for this plugin.
   *
   * @since 1.0.0
   */
  public function display_plugin_admin_page() {
    include_once 'views/admin.php';
  }

  /**
   * Displays setting section header
   * @see add_settings_section()
   */
  public function settings_section_oauth() {
    include 'views/admin/settings.help.php';
  }

  /**
   * Displays setting field for oauth_access_token.
   */
  public function _setting_oauth_access_token() {
    $this->_setting__field_input('oauth_access_token');
  }

  /**
   * Displays setting field for oauth_access_token_secret.
   */
  public function _setting_oauth_access_token_secret() {
    $this->_setting__field_input('oauth_access_token_secret');
  }

  /**
   * Displays setting field for consumer_key.
   */
  public function _setting_consumer_key() {
    $this->_setting__field_input('consumer_key');
  }

  /**
   * Displays setting field for consumer_secret.
   */
  public function _setting_consumer_secret() {
    $this->_setting__field_input('consumer_secret');
  }

  /**
   * Displays setting field for screen_name.
   */
  public function _setting_screen_name() {
    $this->_setting__field_input('screen_name');
  }

  /**
   * Displays setting field for update_frequency.
   */
  public function _setting_update_frequency() {
    $values = array();
    foreach (wp_get_schedules() as $name => $setting) {
      $values[$name] = $setting['display'];
    }
    $this->_setting__field_select('update_frequency', $values);
  }

  /**
   * Displays setting field for update_frequency.
   */
  public function _setting_debug_mode() {
    $this->_setting__field_checkbox('debug_mode');
  }

  /**
   * Displays a setting field for option with given name.
   */
  public function _setting__field_input($option_name) {
    $options = get_option(self::SLUG);
    $value = esc_attr($options[$option_name]);
    echo '<input type="text" name="', self::SLUG, '[', esc_attr($option_name), ']" value="', $value, '" size="60" />';
  }

  /**
   * Displays a select list field for option with give name.
   * @param $option_name
   * @param $values
   */
  public function _setting__field_select($option_name, $values) {
    $options = get_option(self::SLUG);
    $value = esc_attr($options[$option_name]);
    echo '<select name="', self::SLUG, '[', esc_attr($option_name), ']">';
    foreach ($values as $val => $label) {
      $selected = $val == $value ? ' selected="selected"' : '';
      echo '<option value="', $val, '"', $selected, '>', $label, '</option>';
    }
    echo '</select>';
  }

  /**
   * Displays a checkbox field for option with given name.
   */
  public function _setting__field_checkbox($option_name) {
    $options = get_option(self::SLUG);
    $value = esc_attr($options[$option_name]);
    $checked = intval($value) ? ' checked="checked"' : '';
    echo
      '<input type="hidden" name="', self::SLUG, '[', esc_attr($option_name), ']" value="0" />',
      '<input type="checkbox" name="', self::SLUG, '[', esc_attr($option_name), ']" value="1"', $checked, '/>'
    ;
  }

  /**
   * Get debug mode option value.
   * @return bool
   */
  static public function is_debug_mode_enabled() {
    global $current_user;
    if (!$current_user) {
      return false;
    }
    if (!current_user_can('administrator')) {
      return false;
    }
    $options = get_option(self::SLUG);
    return intval(esc_attr($options['debug_mode'])) === 1;
  }
}
