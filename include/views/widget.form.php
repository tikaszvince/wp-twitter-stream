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
 * @var array $template_candidates Template candidates.
 * @var array $templates Available templates.
 */

?>

<p>
  <label for="<?php echo $this->get_field_id('title'); ?>">
    <?php _e('Title:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <input class="widefat" type="text"
     id="<?php echo $this->get_field_id('title'); ?>"
     name="<?php echo $this->get_field_name('title'); ?>"
     value="<?php echo esc_attr($this->instance_settings['title']); ?>" />
</p>

<p>
  <label for="<?php echo $this->get_field_id('count'); ?>">
    <?php _e('Count:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <input class="widefat" type="number"
     id="<?php echo $this->get_field_id('count'); ?>"
     name="<?php echo $this->get_field_name('count'); ?>"
     value="<?php echo intval($this->instance_settings['count']); ?>" />
</p>

<h3><?php _e('Filter tweets', WP_Twitter_Stream_Plugin::SLUG); ?></h3>

<p>
  <label for="<?php echo $this->get_field_id('media_filter'); ?>">
    <?php _e('Media filter mode:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <select class="widefat wp-twitter-stream-media-filter-mode"
    id="<?php echo $this->get_field_id('media_filter'); ?>"
    name="<?php echo $this->get_field_name('media_filter'); ?>">
    <?php foreach ($media_filter_modes as $val => $label) : ?>
      <?php $selected = $val == $this->instance_settings['media_filter'] ? ' selected="selected"' : ''; ?>
      <?php echo '<option value="', $val, '"', $selected, '>', $label , '</option>'; ?>
    <?php endforeach; ?>
  </select>
</p>

<p>
  <label for="<?php echo $this->get_field_id('filter_mode'); ?>">
    <?php _e('Hastag filter mode:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <select class="widefat wp-twitter-stream-filter-mode"
    id="<?php echo $this->get_field_id('filter_mode'); ?>"
    name="<?php echo $this->get_field_name('filter_mode'); ?>">
    <?php foreach ($filter_modes as $val => $label) : ?>
      <?php $selected = $val == $this->instance_settings['filter_mode'] ? ' selected="selected"' : ''; ?>
      <?php echo '<option value="', $val, '"', $selected, '>', $label , '</option>'; ?>
    <?php endforeach; ?>
  </select>
</p>

<div class="wp-twitter-stream-hashtag-list<?php echo $this->instance_settings['filter_mode'] == WP_Twitter_Stream_Query::FILTER_MODE_ALL ? ' hidden' : ''; ?>">
  <p>
    <label for="<?php echo $this->get_field_id('hashtags'); ?>">
      <?php _e('Hastags:', WP_Twitter_Stream_Plugin::SLUG); ?>
    </label>
  </p>
  <div class="hashtag-list">
    <?php foreach ($hashtags as $tag) : ?>
      <?php $checked = in_array($tag->id, $this->instance_settings['hashtags']) ? ' checked="checked"' : ''; ?>
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

<h3><?php _e('Templates', WP_Twitter_Stream_Plugin::SLUG); ?></h3>

<p>
  <label for="<?php echo $this->get_field_id('id'); ?>">
    <?php _e('Widget machine name:', WP_Twitter_Stream_Plugin::SLUG); ?>
  </label>
  <input class="widefat" type="text"
    id="<?php echo $this->get_field_id('id'); ?>"
    name="<?php echo $this->get_field_name('id'); ?>"
    value="<?php echo esc_attr($this->instance_settings['id']); ?>" />
</p>

<div class="description">
  <strong><?php _e('Available template candidates', WP_Twitter_Stream_Plugin::SLUG); ?></strong>
  <ol>
    <?php foreach ($template_candidates as $template) : ?>
      <li><?php echo $template ?></li>
    <?php endforeach; ?>
  </ol>
</div>

<?php if (count($templates) > 1) : ?>
  <p>
    <label for="<?php echo $this->get_field_id('template'); ?>">
      <?php _e('Template file:', WP_Twitter_Stream_Plugin::SLUG); ?>
    </label>
    <select class="widefat"
      id="<?php echo $this->get_field_id('template'); ?>"
      name="<?php echo $this->get_field_name('template'); ?>">
      <?php foreach ($templates as $file) : ?>
        <?php $selected = $file == $this->instance_settings['template'] ? ' selected="selected"' : ''; ?>
        <?php echo '<option value="', $file, '"', $selected, '>', $file , '</option>'; ?>
      <?php endforeach; ?>
    </select>
  </p>
<?php endif; ?>

<?php if (WP_Twitter_Stream_Plugin::is_debug_mode_enabled()) : ?>
  <h3><?php _e('Development', WP_Twitter_Stream_Plugin::SLUG); ?></h3>
  <p>
    <label>
      <?php $checked = $this->instance_settings['dump_query'] ? ' checked="checked"' : ''; ?>
      <input type="hidden" name="<?php echo $this->get_field_name('dump_query'); ?>" value="0" />
      <input class="checkbox" type="checkbox"
        name="<?php echo $this->get_field_name('dump_query'); ?>"
        value="1"<?php echo $checked ?>
        />
      <?php _e('Print SQL query', WP_Twitter_Stream_Plugin::SLUG) ?>
      </label>
    <br/>
    <label>
      <?php $checked = $this->instance_settings['dump_settings'] ? ' checked="checked"' : ''; ?>
      <input type="hidden" name="<?php echo $this->get_field_name('dump_settings'); ?>" value="0" />
      <input class="checkbox" type="checkbox"
        name="<?php echo $this->get_field_name('dump_settings'); ?>"
        value="1"<?php echo $checked ?>
        />
      <?php _e('Print Widget settings', WP_Twitter_Stream_Plugin::SLUG) ?>
      </label>
    <br/>
    <label>
      <?php $checked = $this->instance_settings['force_re_parsing'] ? ' checked="checked"' : ''; ?>
      <input type="hidden" name="<?php echo $this->get_field_name('force_re_parsing'); ?>" value="0" />
      <input class="checkbox" type="checkbox"
        name="<?php echo $this->get_field_name('force_re_parsing'); ?>"
        value="1"<?php echo $checked ?>
        />
      <?php _e('Force re parsing tweet', WP_Twitter_Stream_Plugin::SLUG) ?>
      </label>
  </p>
<?php endif; ?>