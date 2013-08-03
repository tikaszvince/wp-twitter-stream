<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

// If uninstall, not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

