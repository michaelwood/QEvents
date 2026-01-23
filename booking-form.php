<?php

function get_event_data($event_name){
  $db = llg_db_connection();

  /* If we don't have an event name set then just get the latest one */
  if (!isset($event_name)){
    $sql = 'SELECT events.*, forms.template FROM events LEFT JOIN forms on events.form_id = forms.id ORDER BY events.id DESC LIMIT 1 ';
  } else {
    $event_name = mysqli_real_escape_string($db, $event_name);
    $sql = 'SELECT events.*, forms.template FROM events LEFT JOIN forms ON events.form_id = forms.id WHERE events.name="'.$event_name.'" LIMIT 1 ';
  }

  $result = mysqli_query($db, $sql) or die(mysqli_error($db));
  if (mysqli_num_rows ($result) > 0)
    $event_data = mysqli_fetch_assoc ($result);

  return $event_data;
}

function booking_form_get_string ($event_name){
  $config = config();

  $m = new Mustache_Engine;

  /* If we're not already using SSL don't allow continue, redirect instead */
  if (empty($_SERVER['HTTPS']) && $config['debug'] == false) {
    $correct_url = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    return '<p>&#x1f512; <a href="'.$correct_url.'"><span id="llg-https-redirect-notice">Redirecting to secure server</span></a></p>';
  }

  $event_data = get_event_data($event_name);
  if ($event_data['enabled'] == 0)
    return '<p>Sorry bookings are now closed. <a href="/contact">Contact for further enquiries.</a></p>';

  if (isset ($event_data) == 0) {
    return 'No event found E13';
  }

  $context = array(
    'event' => $event_data,
    'img_url' => plugins_url('/img/', __FILE__),
  );

  $ret = '
  <script>
    var llgCSRF = "'.wp_create_nonce().'";
    var llgEventId = '.$event_data['id'].';
  </script>';

  $ret .= $m->render($event_data['template'], $context);

  return $ret;
}

?>