<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId    = intval($_POST['UserId'] ?? 0);
    $ConfigId  = trim($_POST['ConfigId'] ?? '');
    $Tokey     = trim($_POST['Tokey'] ?? '');
    $SubUserId = intval($_POST['SubUserId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '' || $SubUserId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    // 获取现有列
    $_stmtCols = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId`");
    $_existingCols = $_stmtCols->fetchAll(PDO::FETCH_COLUMN);

    // 基础字段
    $_baseFields = ['nickname', 'password', 'gender', 'bio', 'gold', 'exp', 'title', 'ban_time', 'vip_time', 'can_change_pwd'];

    // 从 admin_config 获取自定义变量名和表名
    $_stmtCfg = $_pdo->prepare("SELECT custom_vars, custom_tables FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmtCfg->execute(['cid' => $ConfigId]);
    $_cfg = $_stmtCfg->fetch();
    $_varNames = parseCustomVarNames($_cfg['custom_vars'] ?? '[]');
    $_tblDefs  = parseCustomTableDefs($_cfg['custom_tables'] ?? '{}');

    // 基础字段走主表列更新；自定义变量/JSON 表写侧表
    $_sets = [];
    $_params = [];
    $_customVarWrites = [];
    $_customTblWrites = [];

    foreach ($_baseFields as $_f) {
        if (array_key_exists($_f, $_POST) && $_POST[$_f] !== '') {
            $_safeCol = _safeIdent($_f);
            $paramKey = 'p_' . $_safeCol;
            $_sets[] = "`$_safeCol` = :$paramKey";
            $_params[$paramKey] = ($_f === 'password')
                ? hashSubPwd($_POST[$_f], intval($_POST['HashPwd'] ?? 0))
                : $_POST[$_f];
        }
    }
    foreach ($_varNames as $_f) {
        if (array_key_exists($_f, $_POST)) $_customVarWrites[$_f] = $_POST[$_f];
    }
    foreach (array_keys($_tblDefs) as $_f) {
        if (array_key_exists($_f, $_POST)) {
            $_dec = json_decode($_POST[$_f], true);
            if (is_array($_dec)) {
                if (!empty($_dec) && array_keys($_dec) !== range(0, count($_dec) - 1)) $_dec = [$_dec];
                $_customTblWrites[$_f] = $_dec;
            }
        }
    }

    if (empty($_sets) && empty($_customVarWrites) && empty($_customTblWrites)) {
        throw new Exception('没有需要更新的数据');
    }

    if (!empty($_sets)) {
        $_params['uid'] = $SubUserId;
        $_sql = "UPDATE `user_data`.`$ConfigId` SET " . implode(', ', $_sets) . " WHERE id = :uid";
        $_stmt2 = $_pdo->prepare($_sql);
        $_stmt2->execute($_params);
    }

    foreach ($_customVarWrites as $_n => $_v) customVarSet($ConfigId, $SubUserId, $_n, $_v);
    foreach ($_customTblWrites as $_t => $_rows) customTableReplace($ConfigId, $SubUserId, $_t, $_rows);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}