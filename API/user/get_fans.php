<?php
/**
 * 获取子用户粉丝列表
 * 支持 GET / POST
 * 参数: ConfigId, ApiToken, Username, Password
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $_REQUEST = array_merge($_GET, $_POST);

    $ConfigId = trim($_REQUEST['ConfigId'] ?? '');
    $ApiToken = trim($_REQUEST['ApiToken'] ?? '');
    $Username = trim($_REQUEST['Username'] ?? '');
    $Password = $_REQUEST['Password'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT fans FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');

    // 验证密码
    $_stmtPwd = $_pdo->prepare("SELECT password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmtPwd->execute(['uname' => $Username]);
    if (!verifyPwd($Password, $_stmtPwd->fetchColumn())) throw new Exception('密码错误');

    $_fans = $_user['fans'] ? json_decode($_user['fans'], true) : [];

    echo json_encode([
        'code'  => 0,
        'data'  => $_fans,
        'count' => count($_fans)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}