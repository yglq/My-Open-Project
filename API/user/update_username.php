<?php
/**
 * 修改子用户用户名
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username(旧), Password, NewUsername
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId    = trim($_POST['ConfigId'] ?? '');
    $ApiToken    = trim($_POST['ApiToken'] ?? '');
    $Username    = trim($_POST['Username'] ?? '');
    $Password    = $_POST['Password'] ?? '';
    $NewUsername = trim($_POST['NewUsername'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $NewUsername === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['change_name_switch'] ?? 1) !== 1) throw new Exception('修改用户名功能未开启');

    $_nameMin = intval($_config['name_min_len'] ?? 4);
    $_nameMax = intval($_config['name_max_len'] ?? 20);
    if (mb_strlen($NewUsername) < $_nameMin || mb_strlen($NewUsername) > $_nameMax) {
        throw new Exception("用户名长度需在 {$_nameMin}~{$_nameMax} 之间");
    }

    // 验证旧用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 检查新用户名是否已存在
    $_stmt3 = $_pdo->prepare("SELECT COUNT(*) FROM `user_data`.`$ConfigId` WHERE username = :nname AND id != :uid");
    $_stmt3->execute(['nname' => $NewUsername, 'uid' => $_user['id']]);
    if ($_stmt3->fetchColumn() > 0) throw new Exception('新用户名已被占用');

    // 更新
    $_stmt4 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET username = :nname WHERE id = :uid");
    $_stmt4->execute(['nname' => $NewUsername, 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '用户名修改成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}