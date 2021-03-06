<?php
/*
Plugin Name: Google Maps Widget
Plugin URI: http://www.googlemapswidget.com/
Description: Display a single-image super-fast loading Google map in a widget. A larger, full featured map is available on click in a lightbox. Includes shortcode support and numerous options.
Author: Web factory Ltd
Version: 2.92
Author URI: http://www.webfactoryltd.com/
Text Domain: google-maps-widget
Domain Path: lang

  Copyright 2012 - 2016  Web factory Ltd  (email : gmw@webfactoryltd.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if (!defined('ABSPATH')) {
  die();
}


define('GMW_OPTIONS', 'gmw_options');
define('GMW_CRON', 'gmw_cron');
define('GMW_PLUGIN_DIR', plugin_dir_path(__FILE__));


require_once GMW_PLUGIN_DIR . 'gmw-widget.php';
require_once GMW_PLUGIN_DIR . 'gmw-tracking.php';


class GMW {
  static $version = '2.92';

  // hook everything up
  static function init() {
    GMW_tracking::init();

    if (is_admin()) {
      // check if minimal required WP version is used
      self::check_wp_version(3.3);

      // check some variables
      self::upgrade();

      // aditional links in plugin description
      add_filter('plugin_action_links_' . basename(dirname(__FILE__)) . '/' . basename(__FILE__),
                 array(__CLASS__, 'plugin_action_links'));
      add_filter('plugin_row_meta', array(__CLASS__, 'plugin_meta_links'), 10, 2);

      // enqueue admin scripts
      add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
      add_action('customize_controls_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));

      // JS dialog markup
      add_action('admin_footer', array(__CLASS__, 'admin_dialogs_markup'));

      // register AJAX endpoints
      add_action('wp_ajax_gmw_subscribe', array(__CLASS__, 'email_subscribe'));
      add_action('wp_ajax_gmw_activate', array(__CLASS__, 'activate_via_code'));

      // handle dismiss button for all notices
      add_action('admin_action_gmw_dismiss_notice', array(__CLASS__, 'dismiss_notice'));

      // display various notices
      self::add_notices();
    } else {
      // enqueue frontend scripts
      add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
      add_action('wp_footer', array(__CLASS__, 'dialogs_markup'));
    }

    // add shortcode support
    self::add_shortcode();
  } // init


  // some things have to be loaded earlier
  static function plugins_loaded() {
    load_plugin_textdomain('google-maps-widget', false, basename(dirname(__FILE__)) . '/lang');
    add_filter('cron_schedules', array('GMW_tracking', 'register_cron_intervals'));
  } // plugins_loaded


  // initialize widgets
  static function widgets_init() {
    register_widget('GoogleMapsWidget');
  } // widgets_init


  // add widgets link to plugins page
  static function plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('widgets.php') . '" title="' . __('Configure Google Maps Widget', 'google-maps-widget') . '">' . __('Widgets', 'google-maps-widget') . '</a>';
    array_unshift($links, $settings_link);

    return $links;
  } // plugin_action_links


  // add links to plugin's description in plugins table
  static function plugin_meta_links($links, $file) {
    $documentation_link = '<a target="_blank" href="http://www.googlemapswidget.com/documentation/" title="' . __('View Google Maps Widget documentation', 'google-maps-widget') . '">'. __('Documentation', 'google-maps-widget') . '</a>';
    $support_link = '<a target="_blank" href="http://wordpress.org/support/plugin/google-maps-widget" title="' . __('Problems? We are here to help!', 'google-maps-widget') . '">' . __('Support', 'google-maps-widget') . '</a>';
    $review_link = '<a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/google-maps-widget" title="' . __('If you like it, please review the plugin', 'google-maps-widget') . '">' . __('Review the plugin', 'google-maps-widget') . '</a>';
    $donate_link = '<a target="_blank" href="https://gum.co/gmw-wp" title="' . __('If you feel we deserve it, buy us coffee', 'google-maps-widget') . '">' . __('Donate', 'google-maps-widget') . '</a>';
    $activate_link = '<a href="' . esc_url(admin_url('widgets.php?gmw_open_promo_dialog')) . '">' . __('Activate premium features for <b>FREE</b>', 'google-maps-widget') . '</a>';

    if ($file == plugin_basename(__FILE__)) {
      $links[] = $documentation_link;
      $links[] = $support_link;
      $links[] = $review_link;
      $links[] = $donate_link;
      if (!self::is_activated()) {
        $links[] = $activate_link;
      }
    }

    return $links;
  } // plugin_meta_links


  // check if user has the minimal WP version required by the plugin
  static function check_wp_version($min_version) {
    if (!version_compare(get_bloginfo('version'), $min_version,  '>=')) {
        add_action('admin_notices', array(__CLASS__, 'notice_min_version_error'));
    }
  } // check_wp_version


  // display error message if WP version is too low
  static function notice_min_version_error() {
    echo '<div class="error"><p>' . sprintf(__('Google Maps Widget <b>requires WordPress version 3.3</b> or higher to function properly. You are using WordPress version %s. Please <a href="%s">update it</a>.', 'google-maps-widget'), get_bloginfo('version'), admin_url('update-core.php')) . '</p></div>';
  } // notice_min_version_error


  // print dialogs markup in footer
  static function dialogs_markup() {
       $out = '';
       $widgets = GoogleMapsWidget::$widgets;

       if (!$widgets) {
         wp_dequeue_script('gmw');
         wp_dequeue_script('gmw-fancybox');
         return;
       }

       foreach ($widgets as $widget) {
         if ($widget['bubble']) {
           $iwloc = 'addr';
         } else {
           $iwloc = 'near';
         }
         if ($widget['ll']) {
           $ll = '&amp;ll=' . $widget['ll'];
         } else {
           $ll = '';
         }

         $lang = substr(@$_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
         if (!$lang) {
           $lang = 'en';
         }

         $map_url = '//maps.google.com/maps?hl=' . $lang . '&amp;ie=utf8&amp;output=embed&amp;iwloc=' . $iwloc . '&amp;iwd=1&amp;mrt=loc&amp;t=' . $widget['type'] . '&amp;q=' . urlencode(remove_accents($widget['address'])) . '&amp;z=' . urlencode($widget['zoom']) . $ll;

         $out .= '<div class="gmw-dialog" style="display: none;" data-map-height="' . $widget['height'] . '" data-map-width="' . $widget['width'] . '" data-map-skin="' . $widget['skin'] . '" data-map-iframe-url="' . $map_url . '" id="gmw-dialog-' . $widget['id'] . '" title="' . esc_attr($widget['title']) . '">';
         if ($widget['header']) {
          $out .= '<div class="gmw-header">' . wpautop(do_shortcode($widget['header'])) . '</div>';
         }
         $out .= '<div class="gmw-map"></div>';
         if ($widget['footer']) {
          $out .= '<div class="gmw-footer">' . wpautop(do_shortcode($widget['footer'])) . '</div>';
         }
         $out .= "</div>\n";
       } // foreach $widgets

       echo $out;
  } // dialogs_markup


  // check availability and register shortcode
  static function add_shortcode() {
    global $shortcode_tags;

    if (isset($shortcode_tags['gmw'])) {
      add_action('admin_notices', array(__CLASS__, 'notice_sc_conflict_error'));
    } else {
      add_shortcode('gmw', array(__CLASS__, 'do_shortcode'));
    }
  } // add_shortcode


  // display notice if shortcode name is already taken
  static function notice_sc_conflict_error() {
    if (!self::is_activated()) {
      return;
    }

    echo '<div class="error"><p><strong>' . __('Google Maps Widget shortcode is not active!', 'google-maps-widget') . '</strong>' . __(' Shortcode <i>[gmw]</i> is already in use by another plugin or theme. Please deactivate that theme or plugin.', 'google-maps-widget') . '</p></div>';
  } // notice_sc_conflict_error


  // handle dismiss button for all notices
  // todo - convert all notices
  static function dismiss_notice() {
    $options = get_option(GMW_OPTIONS, array());

    if (isset($_GET['notice']) && $_GET['notice'] == 'upgrade') {
      $options['dismiss_notice_upgrade'] = true;
      update_option(GMW_OPTIONS, $options);
    }
    if (isset($_GET['notice']) && $_GET['notice'] == 'rate') {
      $options['dismiss_notice_rate'] = true;
      update_option(GMW_OPTIONS, $options);
    }
    
    if ($_GET['redirect']) {
      wp_redirect($_GET['redirect']);
    } else {
      wp_redirect(admin_url());
    }
    exit;
  } // dismiss_notice


  // maybe show some notices
  static function add_notices() {
    $options = get_option(GMW_OPTIONS, array());
    $notice = false;
    
    if (empty($options['dismiss_notice_upgrade']) && !self::is_activated()) {
      add_action('admin_notices', array(__CLASS__, 'notice_activate_extra_features'));
      $notice = true;
    } // show upgrade notice

    if (!$notice && empty($options['dismiss_notice_rate']) &&
        GMW_tracking::count_active_widgets() > 0 &&
        (current_time('timestamp') - $options['first_install']) > (DAY_IN_SECONDS * 3)) {
      add_action('admin_notices', array(__CLASS__, 'notice_rate_plugin'));
      $notice = true;
    } // show rate notice
    
    if (!$notice && !isset($options['allow_tracking']) && ((current_time('timestamp') - $options['first_install']) > (DAY_IN_SECONDS * 5))) {
      add_action('admin_notices', array('GMW_tracking', 'tracking_notice'));
      $notice = true;
    } // show tracking notice
  } // add_notices


  // display message to get extra features for GMW
  static function notice_activate_extra_features() {
    $activate_url = admin_url('widgets.php?gmw_open_promo_dialog');
    $dismiss_url = add_query_arg(array('action' => 'gmw_dismiss_notice', 'notice' => 'upgrade', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));

    // todo detect WP version and add support for "is-dismissible"
    // todo remove style from HTML
    echo '<div id="gmw_activate_notice" class="updated notice"><p>' . __('<b>Google Maps Widget</b> has extra premium features you can get for <b style="color: #d54e21;">FREE</b>. This is a limited time offer so act now!', 'google-maps-widget');

    echo '<br /><a href="' . esc_url($activate_url) . '" style="vertical-align: baseline; margin-top: 15px;" class="button-primary">' . __('Activate extra features for <b>FREE</b>', 'google-maps-widget') . '</a>';
    echo '&nbsp;&nbsp;<a href="' . esc_url($dismiss_url) . '">' . __('Dismiss notice', 'google-maps-widget') . '</a>';
    echo '</p></div>';
  } // notice_activate_extra_features
 
  
  // display message to get extra features for GMW
  static function notice_rate_plugin() {
    $rate_url = 'https://wordpress.org/support/view/plugin-reviews/google-maps-widget?rate=5#postform';
    $dismiss_url = add_query_arg(array('action' => 'gmw_dismiss_notice', 'notice' => 'rate', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));

    // todo detect WP version and add support for "is-dismissible"
    // todo remove style from HTML
    echo '<div id="gmw_rate_notice" class="updated notice"><p>' . __('Hi! We saw you\'ve been using <b>Google Maps Widget</b> for some time and wanted to ask for your help to make the plugin even better.<br>We don\'t need money :), just a minute of your time to rate the plugin. Thank you!', 'google-maps-widget');

    echo '<br /><a target="_blank" href="' . esc_url($rate_url) . '" style="vertical-align: baseline; margin-top: 15px;" class="button-primary">' . __('Help us out &amp; rate the plugin', 'google-maps-widget') . '</a>';
    echo '&nbsp;&nbsp;<a href="' . esc_url($dismiss_url) . '" class="">' . __('Dismiss notice', 'google-maps-widget') . '</a>';
    echo '</p></div>';
  } // notice_rate_plugin


  // enqueue frontend scripts if necessary
  static function enqueue_scripts() {
    if (is_active_widget(false, false, 'googlemapswidget', true)) {
      wp_enqueue_style('gmw', plugins_url('/css/gmw.css', __FILE__), array(), GMW::$version);
      wp_enqueue_script('gmw-colorbox', plugins_url('/js/jquery.colorbox.min.js', __FILE__), array('jquery'), GMW::$version, true);
      wp_enqueue_script('gmw', plugins_url('/js/gmw.js', __FILE__), array('jquery'), GMW::$version, true);
    }
  } // enqueue_scripts


  // enqueue CSS and JS scripts on widgets page
  static function admin_enqueue_scripts() {
    global $wp_customize;

    if (self::is_plugin_admin_page() || isset($wp_customize)) {
      wp_enqueue_script('jquery-ui-tabs');
      wp_enqueue_script('jquery-ui-dialog');
      wp_enqueue_script('gmw-cookie', plugins_url('js/jquery.cookie.js', __FILE__), array('jquery'), GMW::$version, true);
      wp_enqueue_script('gmw-admin', plugins_url('js/gmw-admin.js', __FILE__), array('jquery'), GMW::$version, true);

      wp_enqueue_style('wp-jquery-ui-dialog');
      wp_enqueue_style('gmw-admin', plugins_url('css/gmw-admin.css', __FILE__), array(), GMW::$version);

      $js_localize = array('subscribe_ok' => __('Check your inbox. Email with activation code is on its way.', 'google-maps-widget'),
                           'subscribe_duplicate' => __('You are already subscribed to our list. One activation code is valid for all sites so just use the code you already have.', 'google-maps-widget'),
                           'subscribe_error' => __('Something is not right on our end. Sorry :( Try again later.', 'google-maps-widget'),
                           'activate_ok' => __('Superb! Extra features are active ;)', 'google-maps-widget'),
                           'dialog_title' => __('Google Maps Widget <b>Extra Features</b>', 'google-maps-widget'),
                           'undocumented_error' => __('An undocumented error has occured. Please refresh the page and try again.', 'google-maps-widget'),
                           'id_base' => 'googlemapswidget');
      wp_localize_script('gmw-admin', 'gmw', $js_localize);
    } // if
  } // admin_enqueue_scripts


  // check if plugin's admin page is shown
  static function is_plugin_admin_page() {
    $current_screen = get_current_screen();

    if ($current_screen->id == 'widgets') {
      return true;
    } else {
      return false;
    }
  } // is_plugin_admin_page


  // check if activate-by-subscribing features have been activated
  static function is_activated($activation_type = '') {
    $options = get_option(GMW_OPTIONS);
    
    if (!isset($options['activated']) || $options['activated'] !== true) {
      return false; 
    } else {
      if ($activation_type == 'pro') {
        return strlen($options['activation_code']) === 35;
      } else {
        return true;        
      }
    }
  } // is_activated


  // echo markup for promo dialog; only on widgets page
  static function admin_dialogs_markup() {
    if (!self::is_plugin_admin_page()) {
      return false;
    }

    $current_user = wp_get_current_user();
    if (empty($current_user->user_firstname)) {
      $name = $current_user->display_name;
    } else {
      $name = $current_user->user_firstname;
    }

    $out = '<div id="gmw_promo_dialog">';
    
    $out .= '<div id="gmw_dialog_intro">
             <div class="content">
                <h3 class="center boxed-h">' . __('Choose your prefered way of activating extra features:', 'google-maps-widget') . '</h3>';
    $out .= '<div class="gmw-left-box gmw-content-box">
             <h3>Support</h3>
             <p>development &amp; help us maintain GMW</p>
             <i class="dashicons dashicons-heart"></i>
             <ul>
               <li>Premium email support for 1 year</li>
               <li>Access to all new extra features</li>
               <li>No annoying emails</li>
               <li>No ads in the plugin</li>
             </ul>
             <a href="https://gum.co/gmw-wp" data-noprevent="1" class="gmw_goto_activation button-primary" target="_blank">Donate</a>
             </div>';
    $out .= '<div class="gmw-right-box gmw-content-box gmw-content-box-alternate">
             <h3>Subscribe</h3>
             <p>and receive promotional e-mails</p>
             <i class="dashicons dashicons-email-alt"></i>
             <ul>
               <li>Community based support</li>
               <li>Access to new extra features</li>
               <li>Receive promotional emails</li>
               <li>Ads in the plugin</li>
             </ul>
             <br>
             <a href="#" class="gmw_goto_subscribe button-secondary">Subscribe</a>
             </div>';
    $out .= '<p class="clear center gmw-footer-intro">Already have an activation code? <a href="#" class="gmw_goto_activation">Enter it here</a></p>';
    $out .= '</div></div>'; // dialog intro
    
    $out .= '<div id="gmw_dialog_subscribe">
             <div class="content">
             <h3>' . __('Fill out the form and get extra features for <b>FREE</b> instantly!', 'google-maps-widget') . '</h3>';
    $out .= '<p class="input_row">
               <input value="' . $name . '" type="text" id="gmw_name" name="gmw_name" placeholder="Your name">
               <span class="error name" style="display: none;">Please enter your name.</span>
             </p>';
    $out .= '<p class="input_row">
               <input value="' . $current_user->user_email . '" type="text" name="gmw_email" id="gmw_email" placeholder="Your email address">
               <span style="display: none;" class="error email">Please double check your email address.</span>
             </p>';
    $out .= '<p class="center">
               <a id="gmw_subscribe" href="#" class="button button-primary">Activate extra features</a></p>
               <p class="center">Already have an activation code? <a href="#" class="gmw_goto_activation">Enter it here</a></p>
             </div>';
    $out .= '<div class="footer">
                <p><b>Still not sure?</b></p>
                  <ul>
                    <li>We\'ll never share your email address</li>
                    <li>We won\'t spam you or overwhelm with emails</li>
                    <li>You\'ll get discounts for our premium WP plugins</li>
                  </ul>
                </div>';
    $out .= '</div>'; // dialog subscribe
    
    $out .= '<div id="gmw_dialog_activate">
               <div class="content">';
    $out .= '<p class="input_row">
               <input type="text" id="gmw_code" name="gmw_code" placeholder="Please enter the activation code">
               <span style="display: none;" class="error gmw_code">Please double check the activation code.</span></p>
               <p class="center">
                 <a href="#" class="button button-primary" id="gmw_activate">Activate extra features</a>
               </p>
               <p class="center">If you don\'t have an activation code - <a href="#" class="gmw_goto_intro">Get it now</a></p>
             </div>';
    $out .= '<div class="footer">
               <p><b>FAQ</b></p>
               <ul class="gmw-faq-ul">
                 <li>Donated and haven\'t received the code? <a href="mailto:gmw@webfactoryltd.com?subject=Lost%20activation%20code">Email us</a></li>
                 <li>Didn\'t receive the email? Check your SPAM folder.</li>
                 <li>Code is valid for an unlimited number of plugin installations.</li>
               </ul>
             </div>';
    $out .= '</div>'; // activate screen
    
    $out .= '</div>'; // dialog

    echo $out;
  } // admin_dialogs_markup


  // send user's email to MailChimp via our server
  static function email_subscribe() {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    if (defined('WPLANG')) {
      $lang = strtolower(substr(WPLANG, 0, 2));
    } else {
      $lang = 'en';
    }

    $res = wp_remote_post('http://www.googlemapswidget.com/subscribe.php', array('body' => array('name' => $name, 'email' => $email, 'lang' => $lang, 'ip' => $_SERVER['REMOTE_ADDR'], 'site' => get_home_url())));

    // something's wrong with our server
    if ($res['response']['code'] != 200 || is_wp_error($res)) {
      wp_send_json_error('unknown');
    }

    if ($res['body'] == 'ok') {
      wp_send_json_success();
    } elseif ($res['body'] == 'duplicate') {
      wp_send_json_error('duplicate');
    } else {
      wp_send_json_error('unknown');
    }
  } // email_subscribe


  // check activation code and save if valid
  static function activate_via_code() {
    $code = trim($_POST['code']);

    if (self::validate_activation_code($code) !== false) {
      $options = get_option(GMW_OPTIONS);
      $options['activation_code'] = $code;
      $options['activated'] = true;
      update_option(GMW_OPTIONS, $options);

      wp_send_json_success();
    } else {
      wp_send_json_error();
    }
  } // email_activate


  // check if activation code for additional features is valid
  static function validate_activation_code_format($code) {
    // old key format
    if (strlen($code) == 6) {
      if (($code[0] + $code[5]) != 9 || preg_match('/[0-9a-f]+/i', $code, $matches) != 1) {
        return false;
      }
      return true;
    } // old key format
    
    // new key format
    if (strlen($code) == 35) {
      if (preg_match('/^[a-z0-9]{8}-[a-z0-9]{8}-[a-z0-9]{8}-[a-z0-9]{8}$/i', $code, $matches) != 1) {
        return false;
      }  
      return true;
    } // new key format
    
    return false;
  } // validate_activation_code_format
  
  
  // check if activation code for additional features is valid
  static function validate_activation_code($code) {
    $code = trim($code);
    
    if (self::validate_activation_code_format($code)) {
      return true;        
    } else {
      return false;
    }
  } // validate_activation_code


  // helper function for creating dropdowns
  static function create_select_options($options, $selected = null, $output = true) {
    $out = "\n";

    foreach ($options as $tmp) {
      if ($selected == $tmp['val']) {
        $out .= "<option selected=\"selected\" value=\"{$tmp['val']}\">{$tmp['label']}&nbsp;</option>\n";
      } else {
        $out .= "<option value=\"{$tmp['val']}\">{$tmp['label']}&nbsp;</option>\n";
      }
    } // foreach

    if ($output) {
      echo $out;
    } else {
      return $out;
    }
  } // create_select_options


  // fetch coordinates based on the address
  static function get_coordinates($address, $force_refresh = false) {
    $address_hash = md5('gmw' . $address);

    if ($force_refresh || ($coordinates = get_transient($address_hash)) === false) {
      $url = 'http://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($address) . '&sensor=false';
      $result = wp_remote_get($url);

      if (!is_wp_error($result) && $result['response']['code'] == 200) {
        $data = new SimpleXMLElement($result['body']);

        if ($data->status == 'OK') {
          $cache_value['lat']     = (string) $data->result->geometry->location->lat;
          $cache_value['lng']     = (string) $data->result->geometry->location->lng;
          $cache_value['address'] = (string) $data->result->formatted_address;

          // cache coordinates for 3 months
          set_transient($address_hash, $cache_value, 3600*24*30*3);
          $data = $cache_value;
        } elseif (!$data->status) {
          return false;
        } else {
          return false;
        }
      } else {
         return false;
      }
    } else {
       // data is cached, get it
       $data = get_transient($address_hash);
    }

    return $data;
  } // get_coordinates


  // shortcode support for any GMW instance
  static function do_shortcode($atts, $content = null) {
    if (!self::is_activated()) {
      return '';
    }

    global $wp_widget_factory;
    $out = '';
    $atts = shortcode_atts(array('id' => 0), $atts);
    $id = (int) $atts['id'];
    $widgets = get_option('widget_googlemapswidget');

    if (!$id || empty($widgets[$id])) {
      $out .= '<span class="gmw-error">' . __('Google Maps Widget shortcode error - please double-check the widget ID.', 'google-maps-widget') . '</span>';
    } else {
      $widget_args = $widgets[$id];
      $widget_instance['widget_id'] = 'googlemapswidget-' . $id;
      $widget_instance['widget_name'] = 'Google Maps Widget';

      $out .= '<span class="gmw-shortcode-widget">';
      ob_start();
      the_widget('GoogleMapsWidget', $widget_args, $widget_instance);
      $out .= ob_get_contents();
      ob_end_clean();
      $out .= '</span>';
    }
    
    return $out;
  } // do_shortcode


  // activate doesn't get fired on upgrades so we have to compensate
  public static function upgrade() {
    $options = get_option(GMW_OPTIONS);

    if (!isset($options['first_version']) || !isset($options['first_install'])) {
      $options['first_version'] = GMW::$version;
      $options['first_install'] = current_time('timestamp');
      update_option(GMW_OPTIONS, $options);
    }
  } // upgrade


  // write down a few things on plugin activation
  // NO DATA is sent anywhere unless user explicitly agrees to it!
  static function activate() {
    $options = get_option(GMW_OPTIONS);

    if (!isset($options['first_version']) || !isset($options['first_install'])) {
      $options['first_version'] = GMW::$version;
      $options['first_install'] = current_time('timestamp');
      $options['last_tracking'] = false;
      update_option(GMW_OPTIONS, $options);
    }
  } // activate


  // clean up on deactivation
  static function deactivate() {
    $options = get_option(GMW_OPTIONS);

    if (isset($options['allow_tracking']) && $options['allow_tracking'] === true) {
      GMW_tracking::clear_cron();
    }
  } // deactivate


  // clean up on uninstall / delete
  static function uninstall() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
      return;
    }

    delete_option(GMW_OPTIONS);
  } // uninstall
} // class GMW


// hook everything up
register_activation_hook(__FILE__, array('GMW', 'activate'));
register_deactivation_hook(__FILE__, array('GMW', 'deactivate'));
register_uninstall_hook(__FILE__, array('GMW', 'uninstall'));
add_action('init', array('GMW', 'init'));
add_action('plugins_loaded', array('GMW', 'plugins_loaded'));
add_action('widgets_init', array('GMW', 'widgets_init'));