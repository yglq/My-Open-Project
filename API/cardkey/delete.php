<?php
/**
 * 删除未使用的卡密（API 版）
 * 仅支持 POST
 * 参数: ConfigId, Tokey, CardId
 */
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持 POST 请求');

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $CardId   = intval($_POST['CardId'] ?? 0);

    if ($ConfigId === '' || $Tokey === '' || $CardId <= 0) {
        throw new Exception('参数错误：ConfigId, Tokey, CardId 均为必填');
    }

    $_pdo = GetPDO();
    validateTokeyGlobal($ConfigId, $Tokey);

    createCardKeyTable($ConfigId);

    $_tableName = '`card_data`.`' . $ConfigId . '_cardkey`';
    // 仅允许删除未使用的卡密，避免误删已兑换记录
    $_stmt = $_pdo->prepare("DELETE FROM $_tableName WHERE id = :id AND used_at IS NULL");
    $_stmt->execute(['id' => $CardId]);

    if ($_stmt->rowCount() === 0) throw new Exception('卡密不存在或已使用，无法删除');

    echo json_encode(['code' => 0, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
