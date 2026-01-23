<?php

include_once ('config.php');
include_once ('confirmation-email.php');

function exit_with_error($err_msg, $details=null){
  if (config()['debug'] == true){
    $err_msg .= ' '.$details;
  }

  header("X-llg-booking:" . $err_msg);
  http_response_code(500);
  exit();
}

/* save_booking:
 * Requires POST _wpnonce, event_id, form_data
 */
function save_booking(){
  $config = config ();

  if (!isset($_POST['_wpnonce']) ||
    !isset($_POST['event_id']) ||
    !isset($_POST['form_data'])
  ){
    exit_with_error("E99");
  }

  /* CSRF */
  if (!wp_verify_nonce($_POST['_wpnonce'])){
    exit_with_error("E100");
  }

  /* We do this because PHP and everything inbetween likes to mess with
   * the content, see also magic quotes, $_POST sanitisation, encoding issues
   * etc..
   */
  $raw_post = file_get_contents("php://input");

  preg_match('/(\{{1}.+\})/', $raw_post, $matches);

  /* Take the 1st match and remove the form_data portion */
  $json = substr($matches[0], strlen("form_data="));

  $form_data = json_decode($matches[0], true);

  if (!$form_data){
    exit_with_error("E101", 'JSON '.json_last_error_msg());
  }


  /* Test the anti spam answer */
  if (trim(strtolower($form_data['anti_spam'])) != strtolower($config['antispam'])){
    exit_with_error("E102");
  }

  $db = llg_db_connection();

  $event_id = mysqli_real_escape_string($db, $_POST['event_id']);

  $select_booking_det = 'SELECT `name`, `booking_person_email`, `password`, `email_id` FROM `events` WHERE id='.$event_id.' LIMIT 1';

  $res = mysqli_query($db, $select_booking_det) or exit_with_error("E105", mysqli_error($db) . $select_booking_det);
  $event_details = mysqli_fetch_assoc($res);

  $pw = $event_details['password'];
  $salt = file_get_contents($config['saltfile'], FILE_USE_INCLUDE_PATH);

  if ($salt === false){
    exit_with_error("E103");
  }

  $pw .= $salt;

  $json_string_booking = json_encode($form_data);
  $json_string_booking = mysqli_real_escape_string($db, $json_string_booking);

  $insert_booking = 'INSERT INTO bookings (`event_id`, `data`) VALUES('.$event_id.',
    AES_ENCRYPT("'.$json_string_booking.'", "'.$pw.'"))';

  mysqli_query($db, $insert_booking) or exit_with_error("E104");
  $booking_id = mysqli_insert_id($db);

  send_confirmation_email($form_data, $event_details, $booking_id);

  echo $booking_id;
  exit();
}

?>