<?php

include_once '../db_config.php';

function request_exec($action, $action_args, $input)
{
    switch ($action) {
        case 'list':
            return exec_list();
            break;
        default:
            return [
                'status' => 'ok',
                'message' => "Invalid action requested [{$action}].",
                'query_result' => [],
                'input' => $input
            ];
    }
}

function exec_list()
{
    $conn = get_db_connection();

    $stmt = $conn->query('select * from `module_type`');
    $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'ok',
        'message' => count($query_result) . ' entr' . (count($query_result) == 1 ? 'y' : 'ies') . ' found.',
        'query_result' => $query_result,
        'input' => null
    ];
}
