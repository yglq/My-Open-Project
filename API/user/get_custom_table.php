<?php
/**
 * 获取子用户指定自定义JSON表数据
 * 支持 GET / POST
 * 参数: ConfigId, ApiToken, Username, Password, TableName
 * 返回: {code:0, data:[行对象...]}
 * 存储：user_data.{ConfigId}_json 侧表，一行一条记录
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $_REQUEST = array_merge($_GET, $_POST);

    $ConfigId  = trim($_REQUEST['ConfigId'] ?? '');
    $ApiToken  = trim($_REQUEST['ApiToken'] ?? '');
    $Username  = trim($_REQUEST['Username'] ?? '');
    $Password  = $_REQUEST['Password'] ?? '';
    $TableName = trim($_REQUEST['TableName'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $TableName === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password, TableName 均为必填');
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

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    $_rows = customTableRows($ConfigId, $_user['id'], $TableName);

    echo json_encode([
        'code' => 0,
        'data' => $_rows,
        'count' => count($_rows)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
