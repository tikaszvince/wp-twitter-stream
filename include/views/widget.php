<?php
/**
 * Represents the view for the public-facing component of the widget.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

/**
 * Widget instance variables
 *
 * @var String $name Sidebar name.
 * @var string $id Sidebar id
 * @var String $description Sidebar description
 * @var string $class Sidebar class
 * @var string $before_widget String to display before widget
 * @var string $after_widget String to display after widget
 * @var string $before_title String to display before widget title
 * @var string $after_title String to display after widget title
 * @var string $widget_id The HTML id of the widget
 * @var string widget_name The registered name of the widget
 */

/**
 * Widget variables
 * @var WP_Twitter_Stream_Widget $widget Current widget instance
 * @var array $tweets List of WP_Twitter_Stream_Tweet objects.
 * @var string $display_title
 * @var array $templates list of candidate template files.
 * @var string $template_file The used template path.
 * @var string $debug_info Debug infos.
 */

echo $before_widget;
  echo $display_title;

  echo '<ol class="twitter-stream">';
  foreach ($tweets as $tweet) {
    /** @var WP_Twitter_Stream_Tweet $tweet */
    echo
      '<li>',
        $tweet->display($this->get_force_re_parsing()),
      '</li>';
  }
  echo '</ol>';

echo $debug_info;
echo $after_widget;
