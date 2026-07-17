<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId = intval($_POST['UserId'] ?? 0);
    $Nickname = trim($_POST['Nickname'] ?? '');

    if ($UserId <= 0) throw new Exception('参数错误');
    if (mb_strlen($Nickname) < 1 || mb_strlen($Nickname) > 30) throw new Exception('昵称需1-30位');

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("UPDATE users SET nickname = :nick WHERE user_id = :uid");
    $_stmt->execute(['nick' => $Nickname, 'uid' => $UserId]);

    echo json_encode(['code' => 0, 'message' => 'ok'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}