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
 * WP Twitter Stream Install.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Install {

  /** @var  WP_Twitter_Stream_Install */
  static protected $instance;

  /** @var string */
  protected $installed_version;

  /**
   * Return an instance of this class.
   *
   * @since 1.0.0
   * @return WP_Twitter_Stream_Install
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
   * Initialize the installer.
   *
   * @since 1.0.0
   */
  private function __construct() {
    $this->installed_version = get_option(WP_Twitter_Stream_Plugin::SLUG . '_db_version');
    WP_Twitter_Stream_Db::schema();
  }

  /**
   * Installs table or upgrades it.
   */
  public function activate() {
    if ($this->installed_version !== WP_Twitter_Stream_Db::VERSION) {
      $version = $this->install();
      add_option(WP_Twitter_Stream_Plugin::SLUG . '_db_version', $version);
      update_option(WP_Twitter_Stream_Plugin::SLUG . '_db_version', $version);
    }
  }

  /**
   * Deactivate tasks.
   */
  public function deactivate() {
    // TODO drop tables.
  }

  /**
   * Creates table.
   *
   * @return string
   *   New installed version.
   */
  private function install() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach (WP_Twitter_Stream_Db::schema() as $table) {
      dbDelta($table);
    }
    return WP_Twitter_Stream_Db::VERSION;
  }

  /**
   * Update tasks.
   */
  public function update() {
    $this->activate();
  }
}
