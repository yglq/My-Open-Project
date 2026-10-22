<?php
/**
 * 验证登录状态
 * POST: UserId, Password
 * 返回: {code:0} 或 {code:1}
 */

require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();

    $UserId = intval($_POST['UserId'] ?? 0);
    $SessionToken = trim($_POST['SessionToken'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($UserId <= 0 || ($SessionToken === '' && $Password === '')) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT user_id, password, session_token, status FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_user = $_stmt->fetch();

    // 用户不存在
    if (!$_user) {
        throw new Exception('用户不存在');
    }

    // 被封号
    if ((int)$_user['status'] === 1) {
        throw new Exception('账号已被封禁');
    }

    // 优先会话令牌；无令牌时兼容旧密码校验
    if ($SessionToken !== '') {
        if (!hash_equals((string)$_user['session_token'], $SessionToken)) {
            throw new Exception('登录状态已失效，请重新登录');
        }
    } elseif (!verifyPwd($Password, $_user['password'])) {
        throw new Exception('密码已变更');
    }

    echo json_encode(['code' => 0, 'message' => 'ok'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(401);
    apiError($e);
}