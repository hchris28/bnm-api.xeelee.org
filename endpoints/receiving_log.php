<?php

include_once '../db_config.php';
include_once '../config.php';
include_once '../sql.php';
include_once '../utility/guid_generator.php';

function request_exec($action, $action_args, $input)
{

    switch ($action) {
        case 'index':
            return exec_index($input);
            break;
        case 'list':
            return exec_list($input);
            break;
        case 'add_note':
            return exec_add_note($input);
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
    if (!array_key_exists('id', $input)) {
        return [
            'status' => 'error',
            'message' => "Material Receiving Log ID is required",
            'query_result' => false,
            'input' => $input
        ];
    }

    $id = $input['id'];
    $id_param = ['id' => $id];

    $conn = get_db_connection();

    $stmt = $conn->prepare(SQL_MATERIAL_RECEIVING_LOG . " where `material_receiving_log`.`id` = :id");
    $stmt->execute($id_param);
    $query_result = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_notes = $conn->prepare("select * from material_receiving_note where material_receiving_log_id = :id");
    $stmt_notes->execute($id_param);
    $query_result += ['notes' => $stmt_notes->fetchAll(PDO::FETCH_ASSOC)];

    format_row($query_result);

    return [
        'status' => 'ok',
        'message' => "Material Receiving Log entry {$id} " . ($query_result == false ? "was not " : "") . "found.",
        'query_result' => $query_result,
        'input' => $input
    ];
}

function exec_list($input)
{
    [$sql_where, $sql_params] = get_sql_params($input);

    $conn = get_db_connection();

    // get total count
    $stmtTotalCount = $conn->prepare("select count(*) from material_receiving_log {$sql_where}");
    $stmtTotalCount->execute($sql_params);
    $total_records = $stmtTotalCount->fetchColumn();

    if ($total_records > MAX_QUERY_RESULTS) {
        return get_return_value([
            'message' => "Too many records ({$total_records}). Please adjust your query to return less than " . MAX_QUERY_RESULTS . " records.",
            'input' => $input
        ]);
    }

    $stmt = $conn->prepare(SQL_MATERIAL_RECEIVING_LOG . " {$sql_where} order by `time_stamp` desc");
    $stmt->execute($sql_params);
    $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($query_result === false) {
        return get_return_value([
            'error' => $stmt->errorInfo()
        ]);
    }

    foreach ($query_result as &$row) {
        format_row($row);
    }

    return get_return_value([
        'message' => $total_records . ' entr' . ($total_records == 1 ? 'y' : 'ies') . ' found.',
        'query_result' => $query_result,
        'input' => $input,
        'total_records' => $total_records
    ]);
}

function exec_add_note($input)
{
    if (
        !array_key_exists('material_receiving_log_id', $input)
        || !array_key_exists('content', $input)
        || !array_key_exists('author', $input)
    ) {
        return [
            'status' => 'error',
            'message' => 'Missing required parameters.',
            'query_result' => null,
            'input' => $input
        ];
    }

    $material_receiving_log_id = $input['material_receiving_log_id'];
    $content = $input['content'];
    $author = $input['author'];

    $conn = get_db_connection();

    $stmt = $conn->prepare("
        insert into material_receiving_note (`id`, `content`, `time_stamp`, `author`, `material_receiving_log_id`) 
        values (:id, :content, curdate(), :author, :material_receiving_log_id)
    ");

    $new_id = \GuidGenerator::new_guid();

    $stmt_success = $stmt->execute([
        'id' => $new_id,
        'material_receiving_log_id' => $material_receiving_log_id,
        'content' => $content,
        'author' => $author
    ]);

    if ($stmt_success) {
        return [
            'status' => 'ok',
            'message' => "A note has been added to the meterial receiving log entry {$material_receiving_log_id}",
            'query_result' => null,
            'input' => $input,
            'id' => $new_id
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'An error occurred while adding the note.',
            'query_result' => $stmt->errorInfo(),
            'input' => $input
        ];
    }
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

    $add_like_param('bill_of_lading');
    $add_like_param('container_id');
    $add_like_param('material_receiving_report');

    if (count($sql_params) == 0) {
        return ['', []];
    } else {
        return [
            'where ' . implode(' and ', $sql_where),
            $sql_params
        ];
    }
}

function format_row(&$row)
{
    $row['location'] = [
        "lat" => (float)$row["lat"],
        "lng" => (float)$row["lng"]
    ];
    unset($row["lat"]);
    unset($row["lng"]);

    $row["pallet_quantity"] = (int)$row["pallet_quantity"];
    $row["unit_quantity"] = (int)$row["unit_quantity"];
    $row["material_status_id"] = (int)$row["material_status_id"];
    $row["pallet_ids"] = explode(',', $row["pallet_ids"]);

    $img_src_replace = [
        'find' => '/home/xeeleeah/subdomains/bnm.xeelee.org/public_html/',
        'replace' => 'http://bnm.xeelee.org/'
    ];
    $images = [];
    if ($row["image_1"] != null) {
        $images[] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["image_1"]);
    }
    if ($row["image_2"] != null) {
        $images[] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["image_2"]);
    }
    if ($row["image_3"] != null) {
        $images[] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["image_3"]);
    }
    if ($row["image_4"] != null) {
        $images[] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["image_4"]);
    }
    if ($row["image_5"] != null) {
        $images[] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["image_5"]);
    }
    $row['inspection_images'] = $images;
    unset($row["image_1"]);
    unset($row["image_2"]);
    unset($row["image_3"]);
    unset($row["image_4"]);
    unset($row["image_5"]);

    $row["signature"] = str_replace($img_src_replace['find'], $img_src_replace['replace'], $row["signature"]);
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
