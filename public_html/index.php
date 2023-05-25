<?php

// Get the HTTP method, path and body of the request
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$input = json_decode(file_get_contents('php://input'), true);

[$endpoint, $action, $action_args] = parseRequest($request);

authorize($input);

// get_request_function() will include a file that defines a 
// a function that will handle the request
try {
    get_request_function($endpoint);
    $data = request_exec($action, $action_args, $input);
    exit_and_return_json($data);
} catch (\Throwable $th) {
    exit_and_return_json([
        'status' => 'error',
        'message' => $th->getMessage()
    ]);
}

// -----------------------------------------------------------------------
// FUNCTIONS
// -----------------------------------------------------------------------

function authorize($input)
{
    // naive security implementation only for the purposes of demonstration
    $auth_key = "458a6031-eb7b-42d1-861a-1c7057f183c7";

    $headers = getallheaders();

    if (!array_key_exists('x-api-key', $headers)) {
        exit_and_return_json(['status' => 'forbidden']);
    }

    $request_auth_key = $headers['x-api-key'];

    if ($request_auth_key != $auth_key) {
        exit_and_return_json(['status' => 'forbidden']);
    }
}

function parseRequest($request)
{
    $endpoint = null;
    $action = "index";
    $action_args = [];

    $request_count = count($request);

    if ($request_count == 1) {
        $endpoint = $request[0];
    } else if ($request_count >= 2) {
        [$endpoint, $action] = $request;
        if ($request_count >= 3) {
            $action_args = array_slice($request, 3);
        }
    }

    return [$endpoint, $action, $action_args];
}

function get_request_function($endpoint)
{
    // TODO: hardcoded switch to remove the possibility of including
    // remote files. check server settings to see if this is necessary.

    switch ($endpoint) {
        case 'area':
            require_once('../endpoints/area.php');
            break;
        case 'module_type':
            require_once('../endpoints/module_type.php');
            break;
        case 'field_scan_type':
            require_once('../endpoints/field_scan_type.php');
            break;
        case 'material_status':
            require_once('../endpoints/material_status.php');
            break;
        case 'receiving_log':
            require_once('../endpoints/receiving_log.php');
            break;
        case 'field_scan':
            require_once('../endpoints/field_scan.php');
            break;
        case 'query': // remove after no longer needed for reference
            require_once('../endpoints/query.php');
            break;
        case 'pallet': // remove after no longer needed for reference
            require_once('../endpoints/pallet.php');
            break;
        default:
            exit_and_return_json(['status' => 'unknown request']);
    }
}

function exit_and_return_json($data)
{
    // TODO: double-check security implications for these header values

    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header("Access-Control-Allow-Headers: *");
    echo json_encode($data);
    exit();
}
