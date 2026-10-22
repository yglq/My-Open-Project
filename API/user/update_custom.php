<?php
/**
 * 修改指定自定义变量
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, VarName, Value
 * 存储：user_data.{ConfigId}_vars 侧表，不再改子用户主表结构
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

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

    // 验证变量名是否在 custom_vars 中 + 类型检查
    $_varDefs = json_decode($_config['custom_vars'] ?? '[]', true) ?: [];
    $_varType = null;
    foreach ($_varDefs as $_vd) {
        $_n = is_array($_vd) ? ($_vd['name'] ?? '') : $_vd;
        if ($_n === $VarName) { $_varType = is_array($_vd) ? ($_vd['type'] ?? 'value') : 'value'; break; }
    }
    if ($_varType === null) throw new Exception('变量名不存在：' . $VarName);
    if ($_varType === 'value' && !is_numeric($Value)) throw new Exception('该变量是数值类型，Value 必须是数字');

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 写入侧表（upsert）
    customVarSet($ConfigId, $_user['id'], $VarName, $Value);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}