<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId = intval($_POST['UserId'] ?? 0);
    $OldPassword = $_POST['OldPassword'] ?? '';
    $NewPassword = $_POST['NewPassword'] ?? '';

    if ($UserId <= 0) throw new Exception('参数错误');
    if (strlen($NewPassword) < 8) throw new Exception('新密码至少8位');
    if (!preg_match('/[a-zA-Z]/', $NewPassword) || !preg_match('/[0-9]/', $NewPassword)) {
        throw new Exception('新密码必须包含字母和数字');
    }

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT password FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_user = $_stmt->fetch();

    if (!$_user) throw new Exception('用户不存在');
    if (!verifyPwd($OldPassword, $_user['password'])) throw new Exception('原密码错误');

    $_stmt = $_pdo->prepare("UPDATE users SET password = :pwd WHERE user_id = :uid");
$_stmt->execute(['pwd' => hashPwd($NewPassword), 'uid' => $UserId]);

    // 改密后轮换会话令牌，踢掉其他端
    $_sessionToken = issueSessionToken($UserId);

    echo json_encode(['code' => 0, 'message' => '修改成功', 'sessionToken' => $_sessionToken], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}