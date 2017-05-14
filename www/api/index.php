<?php
/**
 * Used to get raw data for the front end to display.
 * For an example of a REST api in php see: https://www.leaseweb.com/labs/2015/10/creating-a-simple-rest-api-in-php/
 */

// get the HTTP method, path and body of the request
$remoteIP = $_SERVER['REMOTE_ADDR'];
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));
$input = json_decode(file_get_contents('php://input'),true);

// retrieve the table and key from the path. in /api/device/10.1.1.1 :: $table='device', $key='10.1.1.1'
$table = preg_replace('/[^a-z0-9_]+/i','',array_shift($request));
$key = trim(substr(array_shift($request),0,128));  // to get only a number use: array_shift($request)+0;

//if a url path wasn't used, then lets try the GET variables
$table = ($table=='')?trim(substr(filter_input(INPUT_GET, 'table', FILTER_SANITIZE_STRING),0,64)):"";
$key = ($key=='')?trim(substr(filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING),0,64)):"";


switch ($method) {
    case 'GET':
        //$sql = "select * from `$table`".($key?" WHERE id=$key":''); break;
    case 'PUT':
        //$sql = "update `$table` set $set where id=$key"; break;
    case 'POST':
        //$sql = "insert into `$table` set $set"; break;
    case 'DELETE':
        //$sql = "delete `$table` where id=$key"; break;
}


//SHOW THE JSON
$retArr = array( "date_atom" => date(DATE_ATOM), "method" => $method, "request" => $request, "input"=> $input, "table" => $table, "key" => $key, "remote-ip" => $remoteIP );
echo json_encode($retArr,JSON_PRETTY_PRINT);

