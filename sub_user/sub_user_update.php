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

    $_allFields = array_merge($_baseFields, $_varNames, array_keys($_tblDefs));

    $_sets = [];
    $_params = [];
foreach ($_allFields as $_f) {
    if (array_key_exists($_f, $_POST) && $_POST[$_f] !== '') {
        $_safeCol = _safeIdent($_f);
        if (!in_array($_safeCol, $_existingCols)) {
            $_isTable = array_key_exists($_f, $_tblDefs);
            $_colType = $_isTable ? 'JSON DEFAULT NULL' : "VARCHAR(255) DEFAULT '0'";
            $_alterSQL = "ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_safeCol` $_colType";
            $_pdo->exec($_alterSQL);
            $_existingCols[] = $_safeCol;
        }

        $paramKey = 'p_' . $_safeCol;
        $_sets[] = "`$_safeCol` = :$paramKey";
        if ($_f === 'password') {
            $HashPwd = intval($_POST['HashPwd'] ?? 0);
            $_params[$paramKey] = hashSubPwd($_POST[$_f], $HashPwd);
        } else {
            $_val = $_POST[$_f];
            // 自定义 JSON 表：前端按 [对象] 数组提交，按设计只存单个对象
            if (array_key_exists($_f, $_tblDefs)) {
                $_dec = json_decode($_val, true);
                if (is_array($_dec)) {
                    $_keys = array_keys($_dec);
                    $_isList = !empty($_dec) && ($_keys === range(0, count($_dec) - 1));
                    if ($_isList) {
                        // 列表则取第一个元素作为单对象存储（空列表存 {}）
                        $_obj = count($_dec) > 0 ? $_dec[0] : new stdClass();
                        $_val = json_encode($_obj, JSON_UNESCAPED_UNICODE);
                    }
                    // 本身已是对象则保持原样
                }
            }
            $_params[$paramKey] = $_val;
        }
    }
}
    if (empty($_sets)) throw new Exception('没有需要更新的数据');

    $_params['uid'] = $SubUserId;
    $_sql = "UPDATE `user_data`.`$ConfigId` SET " . implode(', ', $_sets) . " WHERE id = :uid";
    $_stmt2 = $_pdo->prepare($_sql);
    $_stmt2->execute($_params);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}