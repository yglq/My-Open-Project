<?php
/**
 * 创建/获取私聊对话
 * POST: UserId, TargetUserId
 * 新结构：chat_conversations(is_group=0) + chat_members
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId       = intval($_POST['UserId'] ?? 0);
    $TargetUserId = intval($_POST['TargetUserId'] ?? 0);

    if ($UserId <= 0 || $TargetUserId <= 0) throw new Exception('参数错误');
    if ($UserId === $TargetUserId) throw new Exception('不能和自己聊天');

    $_pdo = GetPDO();

    // 对方用户存在且未封禁
    $_stmt = $_pdo->prepare("SELECT nickname, username, status FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $TargetUserId]);
    $_target = $_stmt->fetch();
    if (!$_target || $_target['status'] == 1) throw new Exception('对方用户不存在或已封禁');
    $_targetName = $_target['nickname'] ?: $_target['username'];

    // 找已有私聊：两人都在同一个 is_group=0 的会话里
    $_stmt3 = $_pdo->prepare(
        "SELECT m1.chat_id FROM chat_members m1
         JOIN chat_members m2 ON m1.chat_id = m2.chat_id
         JOIN chat_conversations c ON c.chat_id = m1.chat_id
         WHERE m1.user_id = :u AND m2.user_id = :t AND c.is_group = 0 LIMIT 1"
    );
    $_stmt3->execute(['u' => $UserId, 't' => $TargetUserId]);
    $_existing = $_stmt3->fetchColumn();
    if ($_existing) {
        wsPublishToMembers([$UserId, $TargetUserId], ['type' => 'chat_refresh', 'chat_id' => $_existing]);
        echo json_encode([
            'code'    => 0,
            'chat_id' => $_existing,
            'is_new'  => false,
            'message' => '已有对话'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 我的名字
    $_stmtM = $_pdo->prepare("SELECT nickname, username FROM users WHERE user_id = :uid LIMIT 1");
    $_stmtM->execute(['uid' => $UserId]);
    $_me = $_stmtM->fetch();
    $_myName = ($_me['nickname'] ?? '') ?: ($_me['username'] ?? '');

    $_newChatId = chatNewId($_pdo);

    $_pdo->beginTransaction();
    try {
        $_pdo->prepare("INSERT INTO chat_conversations (chat_id,is_group,group_name,group_avatar,owner_id,created_at) VALUES (?,0,'','',?,NOW())")
             ->execute([$_newChatId, $UserId]);
        $_insM = $_pdo->prepare("INSERT INTO chat_members (chat_id,user_id,member_name,last_read_id,joined_at) VALUES (?,?,?,0,NOW())");
        $_insM->execute([$_newChatId, $UserId, $_myName]);
        $_insM->execute([$_newChatId, $TargetUserId, $_targetName]);
        $_pdo->commit();
    } catch (Exception $e2) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e2;
    }

    wsPublishToMembers([$UserId, $TargetUserId], ['type' => 'chat_refresh', 'chat_id' => $_newChatId]);

    echo json_encode([
        'code'    => 0,
        'chat_id' => $_newChatId,
        'is_new'  => true,
        'message' => '对话创建成功'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
