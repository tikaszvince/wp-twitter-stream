<?php
/**
 * WP Twitter Stream.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @wordpress-plugin
 * Plugin Name: WP Twitter Stream
 * Plugin URI:  http://vince.tikasz.hu
 * Version:     1.0.0
 * Author:      Vince Tikász
 * Author URI:  http://vince.tikasz.hu
 * Text Domain: wp-twitter-stream
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /lang
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die('Do not call this file directly');
}

require_once 'include/plugin.php';
require_once 'include/db.php';
require_once 'include/install.php';
require_once 'include/tweet.php';
require_once 'include/widget.php';
require_once 'include/cron.php';
require_once 'include/parser.php';

WP_Twitter_Stream_Plugin::get_instance();
