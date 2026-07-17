<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 验证 tokey
    validateTokey($UserId, $ConfigId, $Tokey);

    $_stmt2 = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt2->execute(['cid' => $ConfigId]);
    $_config = $_stmt2->fetch();
    if (!$_config) throw new Exception('配置不存在');

    echo json_encode(['code' => 0, 'data' => $_config], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}