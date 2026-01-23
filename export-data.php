<?php

require_once('config.php');

function export_data(){

  /* If we're not already using SSL don't allow continue, redirect instead */
  if (empty ($_SERVER['HTTPS'])) {
    $correct_url = 'https://';
    $correct_url .= $_SERVER['HTTP_HOST'];
    $correct_url .= $_SERVER['REQUEST_URI'];

    echo '<a href='.$correct_url.'>Please go to secure server first</a>';
    exit();
  }

  $db = llg_db_connection();
  $config = config();

  $pass = mysqli_real_escape_string($db, $_POST['password']);
  $event_id = mysqli_real_escape_string($db, $_POST['event_id']);
  $event_name = mysqli_real_escape_string($db, $_POST['event_name']);

  if (!isset ($pass)) {
    return;
  }

  if(!llg_validate_pass($db, $event_id, $pass)){
    exit();
  }

  $salt = file_get_contents($config['saltfile'], FILE_USE_INCLUDE_PATH);
  if ($salt === false){
    http_response_code(500);
    exit();
  }

  $select_booking_data = '
    SELECT bookings.id, bookings.submit_timestamp,
     AES_DECRYPT(data, CONCAT(SHA2("'.$pass.'", 256), "'.$salt.'")) AS data,
     AES_DECRYPT(admin_notes, CONCAT(SHA2("'.$pass.'", 256), "'.$salt.'")) AS notes
     FROM bookings WHERE bookings.event_id='.$event_id.' ORDER BY id ASC';

/*   Possible join for getting event information
 *   RIGHT JOIN events ON bookings.event_id=events.id
 *   WHERE bookings.event_id='.$event_id.'';
 */
  $res = mysqli_query($db, $select_booking_data) or die (mysqli_error ($db));

  switch ($_POST['output_type']){
    case 'csv':
      output_as_csv($res, $event_name);
      break;
    case 'pdf':
      output_as_pdf($res, $event_name);
      break;
    case 'html':
      output_as_html($res, $event_name, $event_id);
      break;
    default:
      echo "Err Unknown output type";
      exit();
  }
}

function skip_keys($key){
  return in_array($key, ['anti_spam', 'llg_post_action', 'event_id']);
}

function output_as_html($res, $event_name, $event_id){
  $table_content = array();

  $col_headers_previous = array();

  while ($row_arr = mysqli_fetch_assoc($res)) {

    $col_headers = array();
    $row = array();

    foreach ($row_arr as $key => $value) {

      if (skip_keys($key)){
        continue;
      }


      if ($key == 'data'){
        $json_data = json_decode($value, true);
        foreach ($json_data as $key => $value){

          if (skip_keys($key)){
            continue;
          }

          $row[] = array('value' => $value,
          'key' => $key,
        );
          $col_headers[] = $key;
        }
      } else if($key == 'notes'){
        $row[] = array(
          'value' => $value,
          'key' => $key,
          'admin_notes' => True,
          'booking_id' => $row_arr['id'],
        );

        $col_headers[] = $key;
      } else {
        $row[] = array('value' => $value,
        'key' => $key);
        $col_headers[] = $key;
      }
    }

    /* If we're past the first row in the table and the keys suddenly change
     * then write the keys out as a new row
     */
    if (count(array_diff($col_headers, $col_headers_previous)) != 0 &&
        count($table_content) > 0) {
      $table_content[] = $col_headers;
    }

    if (!isset($col_headers_initial)){
      $col_headers_initial = $col_headers;
    }

    $col_headers_previous = $col_headers;

    $table_content[] = $row;
  }

  $context = array(
    'col_headers' => $col_headers_initial,
    'table_content' => $table_content,
    'event_name' => $event_name,
    'event_id' => $event_id,
    'org_name' => config()['org_name'],
    'js_dir' => plugins_url('/js/', __FILE__),
    'csrf' => wp_nonce_field("llg_event_dash", "llg_event_dash_csrf"),
  );

	$m = new Mustache_Engine(array(
    'loader' => new Mustache_Loader_FilesystemLoader(
      dirname(__FILE__) . '/views'),
	));

  try {
    echo $m->render("html-data-output", $context);
  } catch (Exception $e){
    echo $e;
  }

  exit();
}


/* We want to avoid breaking the csv document so if the string contains a
 * quote then we replace this and also quote the whole value.
 */
function clean_up_str($string){
  $string = str_replace("\"", "'", $string);
  $string = '"'.$string.'"';

  return $string;
}

function output_as_csv($res, $event_name){
  $config = config();

  header ('Content-type:text/csv',true);
  header ('Content-Disposition: attachment; filename="'.$config['org_name'].'-'.$event_name.'-'.date("d-m-y").'.csv"', true);

  $csv_out = "";
  $col_headers_previous = '';

  while ($row_arr = mysqli_fetch_assoc($res)) {

    $csv_row = "";
    $col_headers = "";

    foreach ($row_arr as $key => $value) {


      if ($key == 'event_name'){
        $event_name = $value;
        continue;
      }

      if (skip_keys($key)){
        continue;
      }

      if ($key == 'data'){
        $json_data = json_decode($value, true);
        foreach ($json_data as $key => $value){

          if (skip_keys($key)){
            continue;
          }

          /* first row so get the keys for the column header from the json */
          $col_headers .= clean_up_str($key) . ',';
          $csv_row .= clean_up_str($value) . ',';
        }
      } else {
          $col_headers .= clean_up_str($key) . ',';
          $csv_row .= clean_up_str($value) . ',';
      }
    }

    /* Remove trailing comma */
    $csv_row = substr ($csv_row, 0, -1);
    $col_headers = substr($col_headers, 0, -1);

    /* If the previous column headers didn't match then we output them again,
     * this should avoid values appearing under the wrong column header in the
     * case that someone changes the form half way through collecting the data
     */
    if ($col_headers != $col_headers_previous){
      $csv_out .= $col_headers . "\n";
    }

    $col_headers_previous = $col_headers;

    $csv_out .= $csv_row . "\n";
  }

  echo $csv_out;
  exit();
}

/* format the text a bit nicer */
function write_kv($pdf, $key, $value){
  if ($value == ''){
    $value = " - ";
  }
  $pdf->SetFont('Arial', '', 12);
  $pdf->Write(5, $key.': ');
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Write(5, $value);
  $pdf->SetY($pdf->GetY() + 5);
}

function output_as_pdf($res, $event_name){
  require_once('fpdf.php');
  $config = config();

  class PDF extends FPDF {
    function Footer(){
      $config = config();
      // Position at 1.5 cm from bottom
      $this->SetY(-15);
      // Arial italic 8
      $this->SetFont('Arial','I',8);
      // Page number
      $this->Cell(0,10, $config['org_name'].' CONFIDENTIAL - Destroy after use - Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
  }

  $pdf = new PDF('P', 'mm', 'A4');
  $pdf->AliasNbPages();
  $pdf->SetTitle($config['org_name'].' '.$event_name);
  $pdf->SetAutoPageBreak(True, 20);
  $pdf->SetLineWidth(2);
  $pdf->SetDrawColor(255,255,255);
  $pdf->AddPage();

  $pdf->SetFont('Arial','',15);
  $pdf->Write(5, $config['org_name'].": ".$event_name);

  $pdf->SetY($pdf->GetY() + 10);

  $pdf->Line(0, 100, 10, 100);
  $pdf->SetFont('Arial','',12);

  while ($row_arr = mysqli_fetch_assoc($res)) {
    foreach ($row_arr as $key => $value) {

      if ($key == 'data'){
        $json_data = json_decode($value, true);
        foreach ($json_data as $key => $value){

          if (skip_keys($key)){
            continue;
          }
          write_kv($pdf, $key, $value);
        }
      } else {

        write_kv($pdf, $key, $value);
      }
    }

    $pdf->SetY($pdf->GetY() + 10);
  }

  $pdf->Output();
  exit();
}

?>
