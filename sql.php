<?php

define(
    'SQL_MATERIAL_RECEIVING_LOG', 
    <<<EOD
        SELECT 
            `material_receiving_log`.`id`, 
            `time_stamp`, 
            `bill_of_lading`, 
            `material_receiving_report`, 
            `container_id`, 
            `material_type`,
            `pallet_ids`, 
            `pallet_quantity`, 
            `unit_quantity`, 
            `material_receiving_log`.`material_status_id`, 
            `material_status`.`label` as `material_status_label`,
            ST_X(`location`) as `lat`,
            ST_Y(`location`) as `lng`,  
            `image_1`, 
            `image_2`, 
            `image_3`, 
            `image_4`, 
            `image_5`, 
            `signature`
        FROM `material_receiving_log`
        JOIN `material_status`
            ON `material_receiving_log`.`material_status_id` = `material_status`.`id` 
    EOD
);

define(
    'SQL_FIELD_SCAN',
    <<<EOD
        SELECT 
            `field_scan`.`id`, 
            `time_stamp`, 
            `pallet_id`, 
            `field_scan`.`area_id`, 
            `area`.`label` as `area_label`,
            `field_scan`.`module_type_id`,
            `module_type`.`label` as `module_type_label`,
            ST_X(`location`) as `lat`,
            ST_Y(`location`) as `lng`,  
            `image_1`,
            `image_2`, 
            `image_3`, 
            `field_scan`.`field_scan_type_id`, 
            `field_scan_type`.`label` as `field_scan_type_label`,
            `notes`,
            DATEDIFF(CURDATE(), `time_stamp`) as age
        FROM 
            `field_scan` 
        JOIN `area`
            ON `field_scan`.`area_id`  = `area`.`id`
        JOIN `module_type`
            ON `field_scan`.`module_type_id`  = `module_type`.`id`
        JOIN `field_scan_type`
            ON `field_scan`.`field_scan_type_id`  = `field_scan_type`.`id`
    EOD
);