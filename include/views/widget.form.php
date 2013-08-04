<?php
/**
 * Represents the view for the widget setting form.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

/**
 * Variables
 *
 * @var WP_Twitter_Stream_Widget $this
 * @var WP_Twitter_Stream_Widget $widget
 * @var array $instance Current settings
 * @var array $filter_modes Available filter modes
 * @var array $hashtags Available hashtags
 */

?>

<p>
  <label for="<?php echo $this->get_field_id('title'); ?>">
    <?php _e('Title:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <input class="widefat" type="text"
     id="<?php echo $this->get_field_id('title'); ?>"
     name="<?php echo $this->get_field_name('title'); ?>"
     value="<?php echo esc_attr($instance['title']); ?>" />
</p>

<p>
  <label for="<?php echo $this->get_field_id('count'); ?>">
    <?php _e('Count:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <input class="widefat" type="number"
     id="<?php echo $this->get_field_id('count'); ?>"
     name="<?php echo $this->get_field_name('count'); ?>"
     value="<?php echo intval($instance['count']); ?>" />
</p>

<p>
  <label for="<?php echo $this->get_field_id('filter_mode'); ?>">
    <?php _e('Hastag filter mode:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <select class="widefat wp-twitter-stream-filter-mode"
    id="<?php echo $this->get_field_id('filter_mode'); ?>"
    name="<?php echo $this->get_field_name('filter_mode'); ?>">
    <?php foreach ($filter_modes as $val => $label) : ?>
      <?php $selected = $val == $instance['filter_mode'] ? ' selected="selected"' : ''; ?>
      <?php echo '<option value="', $val, '"', $selected, '>', $label , '</option>'; ?>
    <?php endforeach; ?>
  </select>
</p>

<div class="wp-twitter-stream-hashtag-list<?php echo $instance['filter_mode'] == WP_Twitter_Stream_Widget::FILTER_MODE_ALL ? ' hidden' : ''; ?>">
  <p>
    <label for="<?php echo $this->get_field_id('hashtags'); ?>">
      <?php _e('Hastags:', WP_Twitter_Stream_Plugin::SLUG); ?>
    </label>
  </p>
  <div class="hashtag-list">
    <?php foreach ($hashtags as $tag) : ?>
      <?php $checked = in_array($tag->id, $instance['hashtags']) ? ' checked="checked"' : ''; ?>
      <label>
        <input class="checkbox" type="checkbox"
          name="<?php echo $this->get_field_name('hashtags'); ?>[]"
          value="<?php echo $tag->id ?>"<?php echo $checked ?>
          />
        <?php echo $tag->hashtag ?>
      </label><br/>
    <?php endforeach; ?>
  </div>
</div>
