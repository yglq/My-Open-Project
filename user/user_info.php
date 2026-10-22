<?php
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT user_id, username, nickname, avatar, bio, gender, gold, exp, vip_time, svip_time, status FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_user = $_stmt->fetch();

    if (!$_user) {
        throw new Exception('用户不存在');
    }

    if ((int)$_user['status'] === 1) {
        throw new Exception('账号已被封禁');
    }

    echo json_encode([
        'code' => 0,
        'data' => $_user
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    apiError($e);
}