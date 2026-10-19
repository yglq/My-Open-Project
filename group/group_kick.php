<?php
/**
 * 群主踢人
 * POST: UserId, ChatId, TargetUserId
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId       = intval($_POST['UserId'] ?? 0);
    $ChatId       = trim($_POST['ChatId'] ?? '');
    $TargetUserId = intval($_POST['TargetUserId'] ?? 0);

    if ($UserId <= 0 || $ChatId === '' || $TargetUserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT owner_id, is_group FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmt->execute(['c' => $ChatId]);
    $_conv = $_stmt->fetch();
    if (!$_conv) throw new Exception('群聊不存在');

    if (intval($_conv['owner_id']) !== $UserId) throw new Exception('只有群主可以踢人');
    if (!chatIsMember($_pdo, $ChatId, $TargetUserId)) throw new Exception('该用户不在群里');
    if ($TargetUserId === $UserId) throw new Exception('不能踢自己');

    $_pdo->prepare("DELETE FROM chat_members WHERE chat_id = :c AND user_id = :u")
         ->execute(['c' => $ChatId, 'u' => $TargetUserId]);

    // 剩 2 人降级为私聊
    $_becomePrivate = false;
    $_count = count(chatMemberIds($_pdo, $ChatId));
    if ($_count == 2 && intval($_conv['is_group'])) {
        $_pdo->prepare("UPDATE chat_conversations SET is_group = 0 WHERE chat_id = :c")->execute(['c' => $ChatId]);
        $_becomePrivate = true;
    }

    // 剩余成员 + 被踢者都刷新
    wsPublishToMembers(
        array_merge(chatMemberIds($_pdo, $ChatId), [$TargetUserId]),
        ['type' => 'chat_refresh', 'chat_id' => $ChatId]
    );

    echo json_encode([
        'code'           => 0,
        'message'        => '已踢出',
        'become_private' => $_becomePrivate
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
