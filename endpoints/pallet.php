<?php

include_once '../db_config.php';

function request_exec($action, $action_args, $input)
{
    switch ($action) {
        case 'index':
            return exec_index($input);
            break;
        case 'set_status':
            return exec_set_status($input);
            break;
        case 'add_note':
            return exec_add_note($input);
            break;
        default:
            return [
                'status' => 'error',
                'message' => "Invalid action requested [{$action}].",
                'query_result' => [],
                'input' => $input
            ];
    }
}

function exec_index($input)
{
    if (!array_key_exists('pallet_id', $input)) {
        return [
            'status' => 'error',
            'message' => "Pallet ID is required",
            'query_result' => false,
            'input' => $input
        ];
    }

    $pallet_id = $input['pallet_id'];
    $pallet_param = ['pallet_id' => $pallet_id];

    $conn = get_db_connection();

    $stmt = $conn->prepare("select * from vw_pallet_detail where pallet_id = :pallet_id");
    $stmt->execute($pallet_param);
    $query_result = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_history = $conn->prepare("select * from burns_inventory where pallet_id = :pallet_id");
    $stmt_history->execute($pallet_param);
    $query_result['history'] = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    $stmt_status = $conn->prepare("select status from pallet_status where pallet_id = :pallet_id");
    $stmt_status->execute($pallet_param);
    $pallet_status = $stmt_status->fetchColumn();
    $query_result['status'] = $pallet_status == FALSE ? "active" : $pallet_status;

    $stmt_notes = $conn->prepare("select * from pallet_notes where pallet_id = :pallet_id");
    $stmt_notes->execute($pallet_param);
    $query_result['notes'] = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'ok',
        'message' => "Pallet ID {$pallet_id} " . ($query_result == false ? "was not " : "") . "found.",
        'query_result' => $query_result,
        'input' => $input
    ];
}

function exec_set_status($input)
{
    if (!array_key_exists('pallet_id', $input) || !array_key_exists('status', $input)) {
        return [
            'status' => 'error',
            'message' => 'Missing required parameters.',
            'query_result' => null,
            'input' => $input
        ];
    }

    $pallet_id = $input['pallet_id'];
    $new_status = $input['status'];

    $conn = get_db_connection();

    if (!in_array($new_status, [
        'active',
        'damaged',
        'over',
        'short'
    ])) {
        return [
            'status' => 'error',
            'message' => 'Invalid status.',
            'query_result' => null,
            'input' => $input
        ];
    }

    $stmt = $conn->prepare("
        insert into pallet_status (`pallet_id`, `status`, `date`) 
        values (:pallet_id, :status_1, curdate())  
        on duplicate key update `status` = :status_2, `date` = curdate()
    ");

    $stmt_success = $stmt->execute([
        'pallet_id' => $pallet_id,
        'status_1' => $new_status,
        'status_2' => $new_status
    ]);

    if ($stmt_success) {
        return [
            'status' => 'ok',
            'message' => "Status has been set to {$new_status}",
            'query_result' => null,
            'input' => $input
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'An error occurred while setting the pallet status.',
            'query_result' => $stmt->errorInfo(),
            'input' => $input
        ];
    }
}

function exec_add_note($input)
{
    if (
        !array_key_exists('pallet_id', $input)
        || !array_key_exists('note', $input)
        || !array_key_exists('author', $input)
    ) {
        return [
            'status' => 'error',
            'message' => 'Missing required parameters.',
            'query_result' => null,
            'input' => $input
        ];
    }

    $pallet_id = $input['pallet_id'];
    $content = $input['note'];
    $author = $input['author'];

    $conn = get_db_connection();

    $stmt = $conn->prepare("
        insert into pallet_notes (`content`, `date`, `author`, `pallet_id`) 
        values (:content, curdate(), :author, :pallet_id)
    ");

    $stmt_success = $stmt->execute([
        'pallet_id' => $pallet_id,
        'content' => $content,
        'author' => $author
    ]);

    if ($stmt_success) {
        return [
            'status' => 'ok',
            'message' => "A note has been added to pallet {$pallet_id}",
            'query_result' => null,
            'input' => $input,
            'id' => $conn->lastInsertId()
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
