<?php
/**
 * 验证登录状态
 * POST: UserId, Password
 * 返回: {code:0} 或 {code:1}
 */

require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $UserId = intval($_POST['UserId'] ?? 0);
    $Password = $_POST['Password'] ?? '';

    if ($UserId <= 0 || $Password === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT user_id, password, status FROM users WHERE user_id = :uid LIMIT 1");
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

    // 密码不对（被改了）
    if (!verifyPwd($Password, $_user['password'])) {
        throw new Exception('密码已变更');
    }

    echo json_encode(['code' => 0, 'message' => 'ok'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(401);
    apiError($e);
}