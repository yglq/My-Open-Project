<?php
/**
 * 修改指定自定义JSON表数据
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, TableName, Data (JSON数组字符串)
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId  = trim($_POST['ConfigId'] ?? '');
    $ApiToken  = trim($_POST['ApiToken'] ?? '');
    $Username  = trim($_POST['Username'] ?? '');
    $Password  = $_POST['Password'] ?? '';
    $TableName = trim($_POST['TableName'] ?? '');
    $Data      = trim($_POST['Data'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $TableName === '' || $Data === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password, TableName, Data 均为必填');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证表名是否在 custom_tables 中
    $_customTables = parseCustomTableDefs($_config['custom_tables'] ?? '{}');
    if (!array_key_exists($TableName, $_customTables)) throw new Exception('JSON表不存在：' . $TableName);
    $_safeTable = _safeIdent($TableName);

    // 验证 Data 是否为合法 JSON 数组
    $_dataDecoded = json_decode($Data, true);
    if (!is_array($_dataDecoded)) throw new Exception('Data 必须是合法的 JSON 数组');

    // 验证每行的列名是否都在表定义中
    $_allowedCols = $_customTables[$TableName] ?? [];
    foreach ($_dataDecoded as $_row) {
        foreach ($_row as $_col => $_val) {
            if (!in_array($_col, $_allowedCols)) throw new Exception('列名不存在：' . $_col);
        }
    }

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 检查列是否存在，不存在则自动创建
    $_stmtCol = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId` LIKE '$_safeTable'");
    if ($_stmtCol->rowCount() === 0) {
        $_alterSQL = "ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_safeTable` JSON DEFAULT NULL";
        $_pdo->exec($_alterSQL);
    }

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET `$_safeTable` = :data WHERE id = :uid");
    $_stmt3->execute(['data' => $Data, 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}