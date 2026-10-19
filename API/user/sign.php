<?php
/**
 * 子用户签到
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';

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
    $_stmt2 = $_pdo->prepare("SELECT id, password, sign_date FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 检查今天是否已签到
    $_today = date('Y-m-d');
    if ($_user['sign_date'] === $_today) throw new Exception('今日已签到');

    $_giftGold = intval($_config['sign_gold'] ?? 0);
    $_giftExp  = intval($_config['sign_exp'] ?? 0);

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET sign_date = :sd, gold = gold + :g, exp = exp + :e WHERE id = :uid");
    $_stmt3->execute(['sd' => $_today, 'g' => $_giftGold, 'e' => $_giftExp, 'uid' => $_user['id']]);

    echo json_encode([
        'code'    => 0,
        'message' => '签到成功',
        'gold'    => $_giftGold,
        'exp'     => $_giftExp
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}