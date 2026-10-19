<?php
/**
 * 登录接口
 * 接收 POST：UserName, Password
 * 返回 JSON：{"code":0, "message":"ok", "userId":1, "userName":"xxx"}
 */

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

// ========== 主逻辑 ==========
try {

    requirePost();

    $_pdo = GetPDO();
    rateLimit('user/login', 5, 60);

    $UserName = trim($_POST['UserName'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($UserName === '') {
        throw new Exception('请输入用户名');
    }
    if ($Password === '') {
        throw new Exception('请输入密码');
    }

    // 查用户
    $_stmt = $_pdo->prepare("SELECT user_id, username, password, status FROM users WHERE username = :uname LIMIT 1");
    $_stmt->execute(['uname' => $UserName]);
    $_user = $_stmt->fetch();

    if (!$_user) {
        throw new Exception('用户名或密码错误');
    }

    // 检查封号
    if ((int)$_user['status'] === 1) {
        throw new Exception('账号已被封禁');
    }

    // 验证密码（测试阶段明文对比）
    if (!verifyPwd($Password, $_user['password'])) {
        throw new Exception('用户名或密码错误');
    }

    // 更新登录时间和IP
    $_loginIp = getClientIp();
    $_stmt = $_pdo->prepare("UPDATE users SET login_at = NOW(), login_ip = :ip WHERE user_id = :uid");
    $_stmt->execute([
        'ip'  => $_loginIp,
        'uid' => $_user['user_id']
    ]);

    // 签发会话令牌，前端存它而不是明文密码
    $_sessionToken = issueSessionToken((int)$_user['user_id']);

    // 返回
    echo json_encode([
        'code'         => 0,
        'message'      => '登录成功',
        'userId'       => (int)$_user['user_id'],
        'userName'     => $_user['username'],
        'sessionToken' => $_sessionToken
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    apiError($e);
}