<?php
/**
 * 关注用户
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, TargetUsername
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId       = trim($_POST['ConfigId'] ?? '');
    $ApiToken       = trim($_POST['ApiToken'] ?? '');
    $Username       = trim($_POST['Username'] ?? '');
    $Password       = $_POST['Password'] ?? '';
    $TargetUsername = trim($_POST['TargetUsername'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $TargetUsername === '') {
        throw new Exception('参数错误');
    }

    if ($Username === $TargetUsername) throw new Exception('不能关注自己');

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证操作者
    $_stmt2 = $_pdo->prepare("SELECT id, password, follow FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 验证目标用户
    $_stmt3 = $_pdo->prepare("SELECT id, fans FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt3->execute(['uname' => $TargetUsername]);
    $_target = $_stmt3->fetch();
    if (!$_target) throw new Exception('目标用户不存在');

    // 更新操作者的 follow
    $_myFollow = $_user['follow'] ? json_decode($_user['follow'], true) : [];
    if (in_array($TargetUsername, $_myFollow)) throw new Exception('已关注该用户');
    $_myFollow[] = $TargetUsername;
    $_stmt4 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET follow = :f WHERE id = :uid");
    $_stmt4->execute(['f' => json_encode($_myFollow, JSON_UNESCAPED_UNICODE), 'uid' => $_user['id']]);

    // 更新目标的 fans
    $_targetFans = $_target['fans'] ? json_decode($_target['fans'], true) : [];
    $_targetFans[] = $Username;
    $_stmt5 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET fans = :f WHERE id = :uid");
    $_stmt5->execute(['f' => json_encode($_targetFans, JSON_UNESCAPED_UNICODE), 'uid' => $_target['id']]);

    echo json_encode(['code' => 0, 'message' => '关注成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}