<?php
/**
 * 修改子用户昵称
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, Nickname
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持 POST 请求');

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $Nickname = trim($_POST['Nickname'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $Nickname === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['change_nick_switch'] ?? 1) !== 1) throw new Exception('修改昵称功能未开启');

    $_nickMin = intval($_config['nick_min_len'] ?? 1);
    $_nickMax = intval($_config['nick_max_len'] ?? 30);
    if (mb_strlen($Nickname) < $_nickMin || mb_strlen($Nickname) > $_nickMax) {
        throw new Exception("昵称长度需在 {$_nickMin}~{$_nickMax} 之间");
    }

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET nickname = :nick WHERE id = :uid");
    $_stmt3->execute(['nick' => $Nickname, 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '昵称修改成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}