<?php
/**
 * 子用户登录
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
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password 均为必填');
    }

    $_pdo = GetPDO();
    rateLimit('API/user/login', 10, 60);

    // 获取配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证登录开关
    if (intval($_config['login_switch'] ?? 1) !== 1) throw new Exception('登录功能未开启');

    // 查找子用户
    $_stmt2 = $_pdo->prepare("SELECT id, username, nickname, password, avatar, gold, exp, title, vip_time, ban_time FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号或密码错误');

    // 检查封号
    if ($_user['ban_time'] && strtotime($_user['ban_time']) > time()) throw new Exception('账号已被封禁');

    // 验证密码
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('账号或密码错误');

    // 更新登录时间
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET login_at = NOW() WHERE id = :uid");
    $_stmt3->execute(['uid' => $_user['id']]);

    echo json_encode([
        'code' => 0,
        'message' => '登录成功',
        'user' => [
            'id' => (int)$_user['id'],
            'username' => $_user['username'],
            'nickname' => $_user['nickname'],
            'avatar' => $_user['avatar'],
            'gold' => (int)$_user['gold'],
            'exp' => (int)$_user['exp'],
            'title' => $_user['title'],
            'vip_time' => $_user['vip_time']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
