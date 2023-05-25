<?php

include_once '../db_config.php';
include_once '../config.php';

function request_exec($action, $action_args, $input)
{
    switch ($action) {
        case 'index':
            return exec_index($input);
            break;
        case 'page':
            return exec_page($input);
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

function exec_index($input)
{
    [$sql_where, $sql_params] = get_sql_params($input);

    if (count($sql_where) == 0) {
        return get_return_value([
            'message' => "Please specify some query parameters.",
            'input' => $input
        ]);
    }

    $conn = get_db_connection();

    // get total count
    $stmtTotalCount = $conn->prepare("select count(*) from vw_pallet_detail where " . implode(' and ', $sql_where));
    $stmtTotalCount->execute($sql_params);
    $total_records = $stmtTotalCount->fetchColumn();

    if ($total_records > MAX_QUERY_RESULTS) {
        return get_return_value([
            'message' => "Too many records ({$total_records}). Please adjust your query to return less than " . MAX_QUERY_RESULTS . " records.",
            'input' => $input
        ]);
    }

    $stmt = $conn->prepare("select * from vw_pallet_detail where " . implode(' and ', $sql_where));
    $stmt->execute($sql_params);
    $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return get_return_value([
        'message' => $total_records . ' entr' . ($total_records == 1 ? 'y' : 'ies') . ' found.',
        'query_result' => $query_result,
        'input' => $input,
        'total_records' => $total_records
    ]);
}

function exec_page($input)
{
    [$sql_where, $sql_params] = get_sql_params($input);

    if (count($sql_where) == 0) {
        return get_return_value([
            'message' => "Please specify some query parameters.",
            'input' => $input
        ]);
    }

    $conn = get_db_connection();

    // get total count
    $stmtTotalCount = $conn->prepare("select count(*) from vw_pallet_detail where " . implode(' and ', $sql_where));
    $stmtTotalCount->execute($sql_params);
    $total_records = $stmtTotalCount->fetchColumn();

    // get data for requested page
    $current_page = 1;
    if (array_key_exists('current_page', $input) && is_numeric($input['current_page'])) {
        $current_page = $input['current_page'];
    }

    $page_size = 100;
    if (array_key_exists('page_size', $input) && is_numeric($input['page_size'])) {
        $page_size = $input['page_size'];
    }
    $offset = $page_size * ($current_page - 1);

    $stmt = $conn->prepare("select * from vw_pallet_detail where " . implode(' and ', $sql_where) . " limit {$page_size} offset {$offset}");
    $stmt->execute($sql_params);
    $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return get_return_value([
        'message' => $total_records . ' entr' . ($total_records == 1 ? 'y' : 'ies') . ' found.',
        'query_result' => $query_result,
        'input' => $input,
        'total_records' => $total_records,
        'current_page' => $current_page,
        'page_size' => $page_size,
        'total_pages' => ceil($total_records / $page_size)
    ]);
}

function get_sql_params($input)
{
    $sql_where = [];
    $sql_params = [];
    $add_like_param = function ($param_name) use ($input, &$sql_where, &$sql_params, &$debug) {
        if (array_key_exists($param_name, $input) && $input[$param_name] != '') {
            $sql_where[] = "{$param_name} like :{$param_name}";
            $sql_params[$param_name] = "%{$input[$param_name]}%";
        }
    };

    $add_like_param('pallet_id');
    $add_like_param('module_type');
    $add_like_param('area');

    // osd_only is a boolean that translates to the status column
    if (array_key_exists('osd_only', $input) && $input['osd_only'] == 'true') {
        $sql_where[] = "status in ('over', 'short', 'damaged')";
    }

    // moved_only ...
    if (array_key_exists('moved_only', $input) && $input['moved_only'] == 'true') {
        $sql_where[] = "_moves > 0";
    }

    return [$sql_where, $sql_params];
}

function get_return_value($params)
{
    $result_template = [
        'status' => 'ok',
        'message' => '',
        'query_result' => [],
        'total_records' => null,
        'current_page' => null,
        'page_size' => null,
        'total_pages' => null,
        'input' => null,
    ];

    foreach ($params as $key => $value) {
        $result_template[$key] = $value;
    }

    return $result_template;
}
