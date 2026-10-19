<?php
/**
 * 删除未使用的卡密
 * POST: UserId, ConfigId, Tokey, CardId
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $CardId   = intval($_POST['CardId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '' || $CardId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();
    validateTokey($UserId, $ConfigId, $Tokey);

    createCardKeyTable($ConfigId);

    $_tableName = '`card_data`.`' . $ConfigId . '_cardkey`';
    $_stmt = $_pdo->prepare("DELETE FROM $_tableName WHERE id = :id AND used_at IS NULL");
    $_stmt->execute(['id' => $CardId]);

    if ($_stmt->rowCount() === 0) throw new Exception('卡密不存在或已使用');

    echo json_encode(['code' => 0, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}