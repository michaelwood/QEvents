<?php

include_once ('config.php');

function send_confirmation_email($form_data, $event_details, $booking_id){
  $config = config ();

  $db = llg_db_connection();

  $booking_person_email = $event_details['booking_person_email'];

  if (isset($form_data['primary_email'])) {
    $mail_to = filter_var($form_data['primary_email'], FILTER_SANITIZE_EMAIL);
  } else {
    $mail_to = $event_details['booking_person_email'];
  }

  if (isset($form_data['participant_email'])) {
    $participant_email = filter_var($form_data['participant_email'], FILTER_SANITIZE_EMAIL);
    $mail_cc = $participant_email.','.$booking_person_email;
  } else {
    $mail_cc = $booking_person_email;
  }

  if (isset($event_details['email_id'])) {
    $select_email_template = 'SELECT `id`, `template` FROM `emails` WHERE id='.$event_details['email_id'].' LIMIT 1';
    $res = mysqli_query($db, $select_email_template) or exit_with_error("E155", mysqli_error($db) . $select_email_template);
    $template = mysqli_fetch_assoc($res)["template"];
  }

  $default_template = '
Hello,

We have received your booking{{#form_data.participant_name}} for {{form_data.participant_name}}{{/form_data.participant_name}}.

The booking reference is #{{booking_id}}. Important please keep a note of this reference and use it in future correspondence or any applicable payments.

If there are any problems please do not hesitate to contact the bookings person for this event (CC).

Thank You

--
https://{{domain_name}}/';

  if (!$template){
    $template = $default_template;
  }

  $context = array(
    'booking_id' => $booking_id,
    'domain_name' => $config['domain'],
    'form_data' => $form_data,
    'event' => $event_details,
  );

  $m = new Mustache_Engine;
  $mail_body = $m->render($template, $context);

  $headers = 'From: '.$config['from'].''."\r\n";
  if (isset($mail_cc)){
    $headers .= 'Cc:' . $mail_cc . "\r\n";
  }
  $headers .= 'Bcc: '.$config['admin_email']."\r\n";
  $headers .= "Content-type: text/plain; charset=iso-8859-1\r\n";
  $headers .= 'Reply-To:'.$booking_person_email;

  $subject = $event_details['name'].' - form received';

  // Debug
  // file_put_contents('/tmp/email.txt', $mail_body);
  // Reminder this may be systemd-private/.../tmp/email.txt

  try {
    mail ($mail_to, $subject, $mail_body, $headers, '-f '.$config['from']);
  } catch (Exception $e){
    exit_with_error('Mailing exception: '.$e->get_message().'');
  }
}

?>