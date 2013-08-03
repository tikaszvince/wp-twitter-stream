<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

?>
<div class="wrap">

  <?php screen_icon(); ?>
  <h2><?php echo esc_html(get_admin_page_title()); ?></h2>

  <div class="form-wrap">
    <div class="postbox-container" style="width:100%">
      <form action="options.php" method="post">
        <?php settings_fields(WP_Twitter_Stream_Plugin::SLUG); ?>
        <?php do_settings_sections(WP_Twitter_Stream_Plugin::SLUG); ?>
        <?php submit_button(); ?>
      </form>
    </div>
  </div>


</div>
