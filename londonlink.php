<?php
/*
Plugin Name: QEventBookings
Plugin URI: https://michaelwood.me.uk
Description: Plugin to manage simple booking forms
Author: Michael Wood
Version: 3.1
Author URI: https://michaelwood.me.uk
*/


require_once('mustache/mustache.php');


require_once('config.php');
require_once('export-data.php');
require_once('event-settings.php');
require_once('booking-record.php');
require_once('booking-form.php');
require_once('delete-data.php');
require_once('update-admin-notes.php');
require_once('widget.php');

//error_reporting(E_ALL);

function llg_db_connection (){
  $config = config ();

  $llg_db_connection = new mysqli($config['host'],
    $config['user'],
    $config['pass'],
    $config['database']);

  if ($llg_db_connection->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    exit ();
  }
  return $llg_db_connection;
}

function llg_validate_pass($db, $event_id, $password){

  if(!isset($password)){
    return False;
  }

  $pass = mysqli_real_escape_string($db, $password);
  $event_id = mysqli_real_escape_string($db, $event_id);

  $select_password = 'SELECT COUNT(password) FROM events WHERE id="'.$event_id.'" AND password=SHA2("'.$pass.'", 256) LIMIT 1';
  $pass_res = mysqli_query($db, $select_password) or die (mysqli_error($db));

  if (mysqli_fetch_assoc($pass_res)['COUNT(password)'] != 1){

    header('location:'.$_SERVER['REQUEST_URI'].'&bad_pass=1');
    exit();
  } else {
    return True;
  }

  /* not that we're going to reach here but just in case */
  return False;
}

function verify_domain() {
  $config = config ();

  if ($_SERVER['HTTP_HOST'] == $config['domain'])
    return true;

  deactivate_plugins( plugin_basename( __FILE__ ) );
  wp_redirect(admin_url('plugins.php?deactivate=true&plugin_status=all&paged=1'));
  exit();
}

function llg_register_admin_page(){
  add_menu_page ('Qevent Bookings', 'QEvents', 'manage_options', 'llg_booking_admin', 'llg_admin_page');
  add_submenu_page ('llg_booking_admin', 'Add an event', 'Add an event', 'manage_options', 'llg_new_event', 'llg_admin_add_event_page');
  add_submenu_page ('llg_booking_admin', 'Forms', 'Forms', 'manage_options', 'llg_forms', 'llg_admin_forms_page');
  add_submenu_page ('llg_booking_admin', 'Emails', 'Emails', 'manage_options', 'llg_emails', 'llg_admin_emails_page');
  add_submenu_page ('llg_booking_admin',  'Event details', NULL, 'manage_options', 'llg_event_details', 'llg_admin_event_details_page');
}

function llg_form_shortcode_handler ($attr, $content, $tag){
  if ($attr && array_key_exists ('event', $attr))
    $form = booking_form_get_string ($attr['event']);
  else
    $form = booking_form_get_string (null);

  return $form;
}


function llg_next_events_shortcode_handler($attr, $content, $tag){
  $widget = new NextEventWidget();
  $widget->widget();
}

function llg_can_do_this(){
  return (check_admin_referer('llg_event_dash', 'llg_event_dash_csrf') &&
    current_user_can('publish_posts'));
}

function llg_process_post ()
{
  if (!isset($_POST['llg_post_action'])){
    return;
  }
  /* N.b These posts requests come from anywhere */
  switch ($_POST['llg_post_action']) {

  case 'save_booking':
    save_booking ();
    break;

  case 'download_data':
    if (llg_can_do_this()) {
      export_data ();
    }
    break;

  case 'update_event':
    if (llg_can_do_this()) {
      update_event ();
    }
    break;

  case 'insert_event':
    if (llg_can_do_this()) {
      insert_event();
    }
    break;

  case 'delete_data':
    if (llg_can_do_this()) {
      delete_data ();
    }
    break;

  case 'update_admin_notes':
    if (llg_can_do_this()){
      update_admin_notes();
    }
    break;

  case 'update_form_template':
    if (llg_can_do_this()){
      update_form_template();
    }
    break;

  case 'new_form_template':
    if(llg_can_do_this()){
      new_form_template();
    }
    break;

  case 'new_email_template':
    if(llg_can_do_this()){
      new_email_template();
    }
    break;

  case 'update_email_template':
    if(llg_can_do_this()){
      update_email_template();
    }
    break;



  default:
    echo "E222";
    exit();
  }
}

function llg_enqueue_scripts(){
  wp_register_script('llg-custom-script', plugins_url('/js/llg-form-utils.js', __FILE__), array('jquery'));
  wp_register_style('llg-custom-style', plugins_url('/css/llg-style.css', __FILE__));

  wp_enqueue_script('llg-custom-script');
  wp_enqueue_style('llg-custom-style');

  wp_register_script('EasyAutocomplete-script', plugins_url('/js/EasyAutocomplete-1.3.5/jquery.easy-autocomplete.js', __FILE__), array('jquery'));
  wp_register_style('EasyAutocomplete-style', plugins_url('/js/EasyAutocomplete-1.3.5/easy-autocomplete.min.css', __FILE__));

  wp_enqueue_script('EasyAutocomplete-script');
  wp_enqueue_style('EasyAutocomplete-style');
}

function llg_enqueue_admin_scripts(){
  wp_register_script('llg-admin-scripts', plugins_url('/js/llg-admin-page.js', __FILE__), array('jquery'));
  wp_enqueue_script('llg-admin-scripts');
}

function llg_register_widgets(){
  register_widget ('NextEventWidget');
}

function llg_admin_init(){
}

/* https://core.trac.wordpress.org/ticket/23318 */
function llg_remove_update_notify($value) {
  unset($value->response[ plugin_basename(__FILE__) ]);
  return $value;
}

function llg_admin_head(){
  /* We want this registered but not viewable as it doesn't make sense
   * to navigate to the event details page until you select one
  */
  remove_submenu_page('llg_booking_admin', 'llg_event_details');
}

/* Adds [londonlinkbookingform] */
add_shortcode ('qform', 'llg_form_shortcode_handler');
add_shortcode ('qformnextevents', 'llg_next_events_shortcode_handler');

add_action('admin_menu', 'llg_register_admin_page');
add_action('wp_enqueue_scripts', 'llg_enqueue_scripts');
add_action('init', 'llg_process_post');
add_action('admin_enqueue_scripts', 'llg_enqueue_scripts');
add_action('admin_enqueue_scripts', 'llg_enqueue_admin_scripts');
add_action('admin_init', 'llg_admin_init');
add_action('widgets_init', 'llg_register_widgets');
add_action('admin_head', 'llg_admin_head');

add_filter('site_transient_update_plugins', 'llg_remove_update_notify');
?>