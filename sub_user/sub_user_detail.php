<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId    = intval($_POST['UserId'] ?? 0);
    $ConfigId  = trim($_POST['ConfigId'] ?? '');
    $Tokey     = trim($_POST['Tokey'] ?? '');
    $SubUserId = intval($_POST['SubUserId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '' || $SubUserId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    // 获取 admin_config
    $_stmt3 = $_pdo->prepare("SELECT custom_vars, custom_tables, custom_vars_defaults, custom_table_defaults FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt3->execute(['cid' => $ConfigId]);
    $_config = $_stmt3->fetch();

    $_varRaw = json_decode($_config['custom_vars'] ?? '[]', true) ?: [];
    $_varNames = [];
    foreach ($_varRaw as $_v) { $_varNames[] = is_array($_v) ? ($_v['name'] ?? '') : $_v; }
    $_tblRaw = json_decode($_config['custom_tables'] ?? '{}', true) ?: [];
    $_tblDefs = [];
    foreach ($_tblRaw as $_tblName => $_cols) {
        $_tblDefs[$_tblName] = [];
        foreach ((array)$_cols as $_c) {
            $_tblDefs[$_tblName][] = is_array($_c) ? ($_c['name'] ?? '') : $_c;
        }
    }
    $_defaultsVars = $_config && $_config['custom_vars_defaults'] ? json_decode($_config['custom_vars_defaults'], true) : [];
    $_defaultsTbls = $_config && $_config['custom_table_defaults'] ? json_decode($_config['custom_table_defaults'], true) : [];

    // 自动补全子用户表中缺失的自定义变量列
    $_stmtCols = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId`");
    $_existingCols = $_stmtCols->fetchAll(PDO::FETCH_COLUMN);

    foreach ($_varNames as $_name) {
        if (!in_array($_name, $_existingCols)) {
            $_defaultVal = $_defaultsVars[$_name] ?? '0';
            $_alterSQL = "ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_name` VARCHAR(255) DEFAULT '{$_defaultVal}'";
            $_pdo->exec($_alterSQL);
        }
    }

    foreach ($_tblDefs as $_tblName => $_cols) {
        if (!in_array($_tblName, $_existingCols)) {
            $_alterSQL = "ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_tblName` JSON DEFAULT NULL";
            $_pdo->exec($_alterSQL);
        }
    }

    // 获取子用户全部数据
    $_stmt2 = $_pdo->prepare("SELECT * FROM `user_data`.`$ConfigId` WHERE id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $SubUserId]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('子用户不存在');

    // custom_vars 取值，优先取实际值，没有则取默认值
    $_customVars = [];
    foreach ($_varNames as $_name) {
        $_defaultVal = $_defaultsVars[$_name] ?? '0';
        $_customVars[$_name] = $_user[$_name] !== null && $_user[$_name] !== '' ? $_user[$_name] : $_defaultVal;
    }

    // custom_tables
    $_customTables = [];
    foreach ($_tblDefs as $_tblName => $_cols) {
        $_raw = $_user[$_tblName] ?? null;
        $_decoded = $_raw ? json_decode($_raw, true) : [];
        // 兼容：若列里存的是单个对象(关联数组)，包装成 [对象] 供前端按行渲染
        if (is_array($_decoded) && !empty($_decoded)) {
            $_keys = array_keys($_decoded);
            $_isList = ($_keys === range(0, count($_decoded) - 1));
            if (!$_isList) $_decoded = [$_decoded];
        }
        $_customTables[$_tblName] = [
            'columns' => $_cols,
            'rows'    => $_decoded
        ];
    }

    // 从 user 数据中移除自定义字段
    foreach ($_varNames as $_name) unset($_user[$_name]);
    foreach ($_tblDefs as $_tblName => $_cols) unset($_user[$_tblName]);

    echo json_encode([
        'code' => 0,
        'data' => [
            'user'          => $_user,
            'custom_vars'   => $_customVars,
            'custom_tables' => $_customTables
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}