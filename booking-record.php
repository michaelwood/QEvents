<?php

include_once ('config.php');

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

  $select_booking_det = 'SELECT `name`, `booking_person_email`, `password` FROM `events` WHERE id='.$event_id.' LIMIT 1';

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

  $booking_person_email = $event_details['booking_person_email'];


  $subject = 'Booking received for: '.$event_details['name'];

  if (isset($form_data['primary_email'])) {
    $mail_to = filter_var($form_data['primary_email'], FILTER_SANITIZE_EMAIL);
  }

  if (isset($form_data['participant_email'])) {
    $participant_email = filter_var($form_data['participant_email'], FILTER_SANITIZE_EMAIL);
    $mail_cc = $participant_email.','.$booking_person_email;
  } else {
    $mail_cc = $booking_person_email;
  }

  if (isset($form_data['full_name'])){
    $participant_name = 'for '.filter_var($form_data['full_name'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
  } else {
    $participant_name = '';
  }

  /* TODO split out emailer functions */

  $mail_body = 'Hello,'."\n\n";
  $mail_body .= 'We have received your booking '.$participant_name.'';
  $mail_body .= "\n\n";
  $mail_body .= 'The booking reference is #'.$booking_id.'. Important - please keep a note of this reference and use it as a reference in any applicable payments.';
  $mail_body .= "\n\n";
  $mail_body .= 'If there any problems please don\'t hesitate to contact the bookings person for this event (CC d)';
  $mail_body .= "\n\n";
  $mail_body .= 'Thank you';
  $mail_body .= "\n\n";
  $mail_body .= '---';
  $mail_body .= "\n";
  $mail_body .= 'http://'.$config['domain'].'';

  $headers = 'From: '.$config['from'].''."\r\n";
  $headers .= 'Cc:'.$mail_cc."\r\n";
  $headers .= 'Bcc: '.$config['admin_email']."\r\n";
  $headers .= "Content-type: text/plain; charset=iso-8859-1\r\n";
  $headers .= 'Reply-To:'.$booking_person_email;

  try {
    mail ($mail_to, $subject, $mail_body, $headers, '-f '.$config['from']);
  } catch (Exception $e){
    exit_with_error('Mailing exception: '.$e->get_message().'');
  }

  echo $booking_id;
  exit();
}

?>