<?php
/**
 * 修改指定自定义变量
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, VarName, Value
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持 POST 请求');

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $VarName  = trim($_POST['VarName'] ?? '');
    $Value    = $_POST['Value'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $VarName === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password, VarName 均为必填');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证变量名是否在 custom_vars 中
    $_customVars = parseCustomVarNames($_config['custom_vars'] ?? '[]');
    if (!in_array($VarName, $_customVars)) throw new Exception('变量名不存在：' . $VarName);
    $_safeVar = _safeIdent($VarName);

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 检查列是否存在，不存在则自动创建
    $_stmtCol = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$ConfigId` LIKE '$_safeVar'");
    if ($_stmtCol->rowCount() === 0) {
        $_alterSQL = "ALTER TABLE `user_data`.`$ConfigId` ADD COLUMN `$_safeVar` VARCHAR(255) DEFAULT '0'";
        $_pdo->exec($_alterSQL);
    }

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET `$_safeVar` = :val WHERE id = :uid");
    $_stmt3->execute(['val' => $Value, 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}