<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    $_fields = [
        'login_switch','register_switch','change_name_switch','change_nick_switch',
        'change_avatar_switch','api_score_switch','name_min_len','name_max_len',
        'pwd_min_len','pwd_max_len','nick_min_len','nick_max_len','ip_register_limit',
        'register_gold','register_exp','register_vip_minutes','sign_gold','sign_exp',
        'cardkey_length','cardkey_type','cardkey_api_switch','cardkey_generate_switch',
        'notice_switch','notice_api_switch','stats_switch',
        'server_status','api_token_switch','api_token','sub_pwd_hash',
        'custom_vars','custom_tables','custom_vars_defaults','custom_table_defaults'
    ];

    $_sets = []; $_params = [];
    foreach ($_fields as $_f) {
        if (isset($_POST[$_f]) && $_POST[$_f] !== '') {
            $_sets[] = "`$_f` = :$_f";
            $_params[$_f] = $_POST[$_f];
        }
    }

    if (empty($_sets)) throw new Exception('没有要更新的字段');

    $_params['cid'] = $ConfigId;
    $_sql = "UPDATE admin_config SET " . implode(', ', $_sets) . " WHERE config_id = :cid";
    $_stmt2 = $_pdo->prepare($_sql);
    $_stmt2->execute($_params);

    // 检查子用户表是否存在
    $_subTableExists = $_pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='user_data' AND table_name='$ConfigId'"
    )->fetchColumn() > 0;

    // 同步自定义变量列
    if ($_subTableExists && isset($_POST['custom_vars_defaults']) && $_POST['custom_vars_defaults'] !== '') {
        $_newDefaults = json_decode($_POST['custom_vars_defaults'], true) ?: [];
        $_stmtCols = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId`");
        $_existingCols = $_stmtCols->fetchAll(PDO::FETCH_COLUMN);

        foreach ($_newDefaults as $_varName => $_defaultVal) {
            $_safeName = _safeIdent($_varName);
            $_safeVal  = str_replace("'", "\\'", $_defaultVal);

            if (!in_array($_safeName, $_existingCols)) {
                $_pdo->exec("ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_safeName` VARCHAR(255) DEFAULT '{$_safeVal}'");
                $_existingCols[] = $_safeName;
            } else {
                $_pdo->exec("ALTER TABLE `user_data`.`$ConfigId` MODIFY COLUMN `$_safeName` VARCHAR(255) DEFAULT '{$_safeVal}'");
                $_pdo->exec("UPDATE `user_data`.`$ConfigId` SET `$_safeName` = '{$_safeVal}' WHERE `$_safeName` IS NULL OR `$_safeName` = ''");
            }
        }
    }

    // 同步自定义 JSON 表列
    if ($_subTableExists && isset($_POST['custom_table_defaults']) && $_POST['custom_table_defaults'] !== '') {
        $_newTblDefaults = json_decode($_POST['custom_table_defaults'], true) ?: [];

        $_stmtCols = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId`");
        $_existingCols = $_stmtCols->fetchAll(PDO::FETCH_COLUMN);
        $_stmtCols->closeCursor();

        foreach ($_newTblDefaults as $_tblName => $_colDefaults) {
            $_safeTblName = _safeIdent($_tblName);

            if (!in_array($_safeTblName, $_existingCols)) {
                $_pdo->exec("ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_safeTblName` JSON DEFAULT NULL");
                $_existingCols[] = $_safeTblName;
            }

            $_defaultRow = [];
            foreach ($_colDefaults as $_colName => $_colDefault) {
                $_defaultRow[$_colName] = $_colDefault;
            }

            $_allRows = $_pdo->query("SELECT id, `$_safeTblName` FROM `user_data`.`$ConfigId`")->fetchAll();

            foreach ($_allRows as $_row) {
                $_uid  = $_row['id'];
                $_raw  = $_row[$_safeTblName];
                $_data = $_raw ? json_decode($_raw, true) : [];
                if (!is_array($_data)) $_data = [];

                if (empty($_data)) {
                    $_data = [$_defaultRow];
                } else {
                    foreach ($_data as &$_item) {
                        foreach ($_defaultRow as $_k => $_v) {
                            if (!array_key_exists($_k, $_item) || $_item[$_k] === null || $_item[$_k] === '') {
                                $_item[$_k] = $_v;
                            }
                        }
                    }
                    unset($_item);
                }

                $_newJson = json_encode($_data, JSON_UNESCAPED_UNICODE);
                $_upd = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET `$_safeTblName` = :j WHERE id = :i");
                $_upd->execute(['j' => $_newJson, 'i' => $_uid]);
                $_upd->closeCursor();
            }
        }
    }

    echo json_encode(['code' => 0, 'message' => '保存成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}