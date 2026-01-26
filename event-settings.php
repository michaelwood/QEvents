<?php

/* These are mostly all the admin page handlers */

$m = new Mustache_Engine(array(
  'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/views'),
));

/* Page util functions */

function find_available_forms($selected_id=null){
  $db = llg_db_connection();

  $res = mysqli_query($db, 'SELECT id, name from forms');
  $forms = mysqli_fetch_all($res, MYSQLI_ASSOC);

  if (isset($selected_id)){
    foreach ($forms as &$form){
      if ($form['id'] == $selected_id){
        $form['selected'] = true;
      }
    }

    unset($form);
  }

  return $forms;
}

function find_available_emails($selected_id=null){
  $db = llg_db_connection();

  $res = mysqli_query($db, 'SELECT id, name from emails');

  if ($error = mysqli_error($db)){
    echo '<p>Please check your database version is up to date</p>';
    echo $error;
  }

  $emails = mysqli_fetch_all($res, MYSQLI_ASSOC);

  if (isset($selected_id)){
    foreach ($emails as &$email){
      if ($email['id'] == $selected_id){
        $email['selected'] = true;
      }
    }

    unset($email);
  }

  return $emails;
}


function update_event(){

  if (!isset($_POST['password'])){
    echo "No password supplied";
    return;
  }

  $db = llg_db_connection();

  $event_id = mysqli_real_escape_string($db, $_POST['event_id']);
  $pass = mysqli_real_escape_string($db, $_POST['password']);

  if(!llg_validate_pass($db, $event_id, $pass)){
    exit();
  }

  $update_sql = "UPDATE `events` SET ";
  foreach ($_POST as $key => $value) {
    /* Don't allow updating of these fields as they could impact
     * existing bookings made.
     */
    if ($key == "llg_post_action" ||
      $key == 'wp_page_id' ||
      $key == 'name' ||
      $key == 'llg_event_dash_csrf' ||
      $key == '_wp_http_referer' ||
      $key == 'password' ||
      $key == 'event_id'){
      continue;
    }

    $esc_val = mysqli_real_escape_string($db, $value);
    $update_sql .= mysqli_real_escape_string($db, $key);
    $update_sql .= '=\'';
    $update_sql .= $esc_val;
    $update_sql .= '\',';
  }

  /* remove trailing comma */
  $update_sql = substr ($update_sql, 0, -1);

  $update_sql .= ' WHERE id='.$event_id;

  $res = mysqli_query($db, $update_sql) or die(mysqli_error($db));
}

function insert_event () {
  /* wp_parent_page is the page in which the new form page will be parented to
   * otherwise it is orphaned :(
   */
  $db = llg_db_connection();

  $sql  = "INSERT INTO events ";

  foreach ($_POST as $key => $value) {
    if ($key == "llg_post_action" ||
      $key == 'llg_event_dash_csrf' ||
      $key == 'wp_parent_page' ||
      $key == '_wp_http_referer'){
      continue;
    }

    $keys .= $key;
    $keys .= ',';
    $esc_val = mysqli_real_escape_string($db, $value);

    if ($key == 'password') {
      $insert_sql .= "SHA2(\"$esc_val\", 256),";
      continue;
    }

    $insert_sql .= '\'';
    $insert_sql .= $esc_val;
    $insert_sql .= '\',';
  }

  $insert_sql = substr ($insert_sql, 0, -1);
  $keys = substr ($keys, 0, -1);


  $sql .= "($keys) VALUES(";
  $sql .= $insert_sql;
  $sql .= ')';

  $result = mysqli_query($db, $sql) or die("E2422: ".mysqli_error($db));

  /* Update the form's own page */
  $event_name = mysqli_real_escape_string($db, $_POST['name']);

  $res = mysqli_query($db, 'SELECT wp_page_id FROM events WHERE name="'.$event_name.'"');
  $wp_page_id = mysqli_fetch_array($res)[0];

  $new_page = array(
    'post_title'    => $_POST['name'],
    'post_content'  => '[qform event="'.$_POST['name'].'"]',
    'post_status'   => 'publish',
    'post_author'   => 1,
    'post_parent' => $_POST['wp_parent_page'],
    'post_type'     => 'page',
    'post_name' => $_POST['name'],
    'ID' => $wp_page_id,
  );

  /* Insert the post into the database */
  $new_wp_post_id = wp_insert_post($new_page);

  /* Blindly update this */
  $sql = 'UPDATE `events` SET `wp_page_id`='.$new_wp_post_id.' WHERE name="'.$event_name.'"';

  mysqli_query($db, $sql) or die("E9432: ".mysqli_error($db));
}


/* Page render functions */

function llg_admin_page()
{
  $db = llg_db_connection();

  $result = mysqli_query($db, 'SELECT * FROM events ORDER BY id DESC') or die (mysqli_error ());

  $events = array();

  while ($current_values = mysqli_fetch_assoc($result)) {
    $current_values['page_link'] = get_permalink($current_values['wp_page_id']);

    $bookings_res = mysqli_query($db, 'SELECT COUNT(id) FROM bookings WHERE event_id='.$current_values['id'].'');

    $current_values['num_bookings'] = mysqli_fetch_array($bookings_res)[0];

    $events[] = $current_values;
  }


  $context = array(
    'events' => $events,
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
    'forms' => find_available_forms(),
    'this_page' => $_GET['page'],
  );

  global $m;
  echo $m->render("events-list", $context);
}


function llg_admin_add_event_page(){
  global $m;
  $context = array(
    'pages' => get_pages(),
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
    'org_name' => config()['org_name'],
    'forms' => find_available_forms(),
    'emails' => find_available_emails(),
  );

  echo $m->render("add-event", $context);
}


function llg_admin_emails_page(){
  global $m;
  $context = array(
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
    'org_name' => config()['org_name'],
    'emails' => find_available_emails($_GET['email_id']),
    'selected_email' => $_GET['email_id'],
  );

  if (isset($_GET['email_id']) && strlen($_GET['email_id']) > 0){
    $fm = new Mustache_Engine;
    $db = llg_db_connection();

    $email_id = mysqli_real_escape_string($db, $_GET['email_id']);

    $q = mysqli_query($db, 'SELECT `id`, `name`, `template` FROM emails WHERE id = '.$email_id.' LIMIT 1') or die (mysqli_error ($db));
    $email = mysqli_fetch_assoc($q);

    $email_example_context = array(
      'event' => array(
        'cost' => '23423',
        'booking_person_name' => 'BOOKING PERSON NAME',
        'enabled' => True,
        'event_end_date' => '11/22/33',
        'event_start_date' => '22/44/55',
        'name' => 'EVENT NAME',
      ),
      'img_url' => plugins_url('/img/', __FILE__),
    );

    $context['email'] = $email;
    $context['email_rendered'] = $fm->render($email['template'], $email_example_context);
  }

  echo $m->render("view-emails", $context);
}



function llg_admin_forms_page(){
  global $m;

  $context = array(
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
    'org_name' => config()['org_name'],
    'forms' => find_available_forms($_GET['form_id']),
    'selected_form' => $_GET['form_id'],
    'form_html' => '',
  );

  if (isset($_GET['form_id']) && strlen($_GET['form_id']) > 0){
    $fm = new Mustache_Engine;
    $db = llg_db_connection();

    $form_id = mysqli_real_escape_string($db, $_GET['form_id']);

    $q = mysqli_query($db, "SELECT * FROM forms WHERE id = $form_id") or die (mysqli_error ($db));
    $form = mysqli_fetch_assoc($q);


    $form_example_context = array(
      'event' => array(
        'cost' => '23423',
        'booking_person_name' => 'BOOKING PERSON NAME',
        'enabled' => True,
        'event_end_date' => '11/22/33',
        'event_start_date' => '22/44/55',
        'name' => 'EVENT NAME',
      ),
      'img_url' => plugins_url('/img/', __FILE__),
    );

    $context['form'] = $form;
    $context['form_rendered'] = $fm->render($form['template'], $form_example_context);
  }


  echo $m->render("view-forms", $context);
}

function llg_admin_event_details_page(){

  if (!isset($_GET['event_id'])){
    return;
  }

  $db = llg_db_connection();

  $id = mysqli_escape_string($db, $_GET['event_id']);

  $result = mysqli_query($db, 'SELECT * FROM events WHERE id ='.$id.'') or die (mysqli_error ());
  $bookings_res = mysqli_query($db, 'SELECT COUNT(id) FROM bookings WHERE event_id='.$id.'');

  $event = mysqli_fetch_assoc($result);

  /* Couldn't find the event. Either an incorrect id or has been deleted */
  if (!isset($event)){
    echo '<p>Event has been deleted (or never existed). <a href="?page=llg_booking_admin">All events</a></p>';
    return;
  }

  $event['page_link'] = get_permalink($event['wp_page_id']);
  $event['edit_page_link'] = get_edit_post_link($event['wp_page_id']);
  $event['num_bookings'] = mysqli_fetch_array($bookings_res)[0];


  $context = array(
    'event' => $event,
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
    'forms' => find_available_forms($event["form_id"]),
    'emails' => find_available_emails($event["email_id"]),
    'this_page' => $_GET['page'],
    'bad_pass' => ($_GET['bad_pass'] == 1),
  );

  global $m;
  echo $m->render("event-details", $context);
}


function new_form_template(){
  $db = llg_db_connection();

  $initial_form = '
  <h2>{{event.name}}</h2>

  <span class="thankyou">
    <!-- Add your message to display after form submission here -->
    <p>Thanks for submitting the test form. We will be in contact ASAP!</p>
  </span>

  <!-- This area will be hidden after form submission -->
  <span id="booking-area">
    <!-- Note required attributes in <form> element: class="llg-form", id="llg-event-form" and method="POST" -->
    <form class="llg-form" id="llg-event-form" method="POST">

      <!-- First form input field -->
      <label for="test">Test</label>
      <!-- Note: the "name=" attribute is used as the field name in data downloads" -->
      <input id="test" type="text" name="test" required />

      <!-- Required anti spam checker, the pass phrase is set in the config.php -->
      <label for="anti_spam">Anti-spam complete the following: The founder of Quakerism has the first name: George and surname:</label>
      <input type="text" name="anti_spam" id="anti_spam" placeholder="???" required>

      <!-- Required submission button must have id="llg-send-form-btn" -->
      <input type="button" id="llg-send-form-btn" value="Send!"/>
    </form>

    <!-- Optional loading spinner -->
    <img src="{{img_url}}/spinner.gif" id="llg-spinner" alt="please wait..." />
  </span>
  ';

  mysqli_query($db, 'INSERT INTO `forms` (`template`, `name`) VALUES (\''.$initial_form.'\', \'Untitled form\')') or die (mysqli_error ($db));
  $new_form_id = mysqli_insert_id($db);
  header('Location:'.$_SERVER['REQUEST_URI'].'&form_id='.$new_form_id.'');
}

function update_form_template(){
  if (
    !isset($_POST['form_id']) ||
    !isset($_POST['form_template']) ||
    !isset($_POST['form_name'])){

    echo "E45 form or template not set";
    return;
  }

  $db = llg_db_connection();

  $form_id = mysqli_real_escape_string($db, $_POST["form_id"]);
  /* if magic quotes is enabled this will end up double escaped */
  /* https://stackoverflow.com/questions/1522313/php-mysql-real-escape-string-stripslashes-leaving-multiple-slashes */
  $form_template = mysqli_real_escape_string($db, stripslashes(trim($_POST["form_template"])));
  $form_name = mysqli_real_escape_string($db, $_POST["form_name"]);

  mysqli_query($db, 'UPDATE `forms` SET `template`=\''.$form_template.'\', `name`="'.$form_name.'" WHERE `id`='.$form_id.'') or die (mysqli_error());
}

function new_email_template(){
  $initial_email= '
Hello,

We have received your booking{{#form_data.participant_name}} for {{form_data.participant_name}}{{/form_data.participant_name}}.

The booking reference is #{{booking_id}}. Important please keep a note of this reference and use it in future correspondence or any applicable payments.

If there are any problems please do not hesitate to contact the bookings person for this event (CC).

Thank You

--
https://{{domain_name}}/';

  $db = llg_db_connection();
  mysqli_query($db, 'INSERT INTO `emails` (`template`, `name`) VALUES (\''.$initial_email.'\', \'Untitled email\')') or die (mysqli_error ($db));
  $new_form_id = mysqli_insert_id($db);
  header('Location:'.$_SERVER['REQUEST_URI'].'&form_id='.$new_form_id.'');
}

function update_email_template(){
  if (
    !isset($_POST['email_id']) ||
    !isset($_POST['email_template']) ||
    !isset($_POST['email_name'])){

    echo "E456 email template not set";
    return;
  }

  $db = llg_db_connection();

  $email_id = mysqli_real_escape_string($db, $_POST["email_id"]);
  /* if magic quotes is enabled this will end up double escaped */
  /* https://stackoverflow.com/questions/1522313/php-mysql-real-escape-string-stripslashes-leaving-multiple-slashes */
  $email_template = mysqli_real_escape_string($db, stripslashes(trim($_POST["email_template"])));
  $email_name = mysqli_real_escape_string($db, $_POST["email_name"]);

  mysqli_query($db, 'UPDATE `emails` SET `template`=\''.$email_template.'\', `name`="'.$email_name.'" WHERE `id`='.$email_id.'') or die (mysqli_error($db));
}

?>