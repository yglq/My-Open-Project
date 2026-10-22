<?php
/**
 * 群主清空聊天记录
 * POST: UserId, ChatId
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    $ChatId = trim($_POST['ChatId'] ?? '');

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT owner_id FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmt->execute(['c' => $ChatId]);
    $_conv = $_stmt->fetch();
    if (!$_conv) throw new Exception('群聊不存在');
    if (intval($_conv['owner_id']) !== $UserId) throw new Exception('只有群主可以清空聊天记录');

    $_memberIds = chatMemberIds($_pdo, $ChatId);

    $_pdo->beginTransaction();
    try {
        $_pdo->prepare("DELETE FROM chat_messages WHERE chat_id = :c")->execute(['c' => $ChatId]);
        $_pdo->prepare("UPDATE chat_members SET last_read_id = 0 WHERE chat_id = :c")->execute(['c' => $ChatId]);
        $_pdo->commit();
    } catch (Exception $e2) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e2;
    }

    wsPublishToMembers($_memberIds, ['type' => 'chat_refresh', 'chat_id' => $ChatId]);

    echo json_encode(['code' => 0, 'message' => '聊天记录已清空'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
