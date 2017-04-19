<?php
/**
 * Used to get raw data for the front end to display.
 */

// get the HTTP method, path and body of the request
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));
$input = json_decode(file_get_contents('php://input'),true);

// retrieve the table and key from the path
$table = preg_replace('/[^a-z0-9_]+/i','',array_shift($request));
$key = array_shift($request)+0;


//SHOW THE JSON
$retArr = array( "method" => $method, "request" => $request, "input"=> $input, "table" => $table, "key" => $key );
echo json_encode($retArr,JSON_PRETTY_PRINT);
