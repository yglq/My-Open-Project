<?php
/**
 * 退出/解散群聊
 * POST: UserId, ChatId
 * 群主或最后一人 = 解散；其他成员 = 仅自己离开
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

    $_stmt = $_pdo->prepare("SELECT owner_id, is_group FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmt->execute(['c' => $ChatId]);
    $_conv = $_stmt->fetch();
    if (!$_conv) throw new Exception('群聊不存在');

    $_memberIds = chatMemberIds($_pdo, $ChatId);
    if (!in_array($UserId, $_memberIds, true)) throw new Exception('你不在该群聊中');

    $_isOwner = (intval($_conv['owner_id']) === $UserId);

    if ($_isOwner || count($_memberIds) <= 1) {
        // 解散：删会话 + 成员 + 消息 + 聊天图片目录
        $_pdo->beginTransaction();
        try {
            $_pdo->prepare("DELETE FROM chat_conversations WHERE chat_id = :c")->execute(['c' => $ChatId]);
            $_pdo->prepare("DELETE FROM chat_members WHERE chat_id = :c")->execute(['c' => $ChatId]);
            $_pdo->prepare("DELETE FROM chat_messages WHERE chat_id = :c")->execute(['c' => $ChatId]);
            $_pdo->commit();
        } catch (Exception $e2) {
            if ($_pdo->inTransaction()) $_pdo->rollBack();
            throw $e2;
        }

        $_imgDir = __DIR__ . '/../chat_images/' . $ChatId;
        if (is_dir($_imgDir)) {
            foreach (glob($_imgDir . '/*') as $_f) @unlink($_f);
            @rmdir($_imgDir);
        }

        wsPublishToMembers($_memberIds, ['type' => 'chat_refresh', 'chat_id' => $ChatId]);
        echo json_encode(['code' => 0, 'message' => '群聊已解散'], JSON_UNESCAPED_UNICODE);
    } else {
        $_pdo->prepare("DELETE FROM chat_members WHERE chat_id = :c AND user_id = :u")
             ->execute(['c' => $ChatId, 'u' => $UserId]);

        // 剩 2 人降级为私聊
        $_becomePrivate = false;
        $_remain = chatMemberIds($_pdo, $ChatId);
        if (count($_remain) == 2 && intval($_conv['is_group'])) {
            $_pdo->prepare("UPDATE chat_conversations SET is_group = 0 WHERE chat_id = :c")->execute(['c' => $ChatId]);
            $_becomePrivate = true;
        }

        wsPublishToMembers(array_merge($_remain, [$UserId]), ['type' => 'chat_refresh', 'chat_id' => $ChatId]);

        echo json_encode([
            'code'           => 0,
            'message'        => '已退出群聊',
            'become_private' => $_becomePrivate
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
