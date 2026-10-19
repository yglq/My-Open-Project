<?php
/**
 * 获取当前用户的所有私聊好友
 * POST: UserId
 * 返回：好友列表 [{user_id, nickname, avatar, chat_id}]
 * 新结构：好友 = 与我同处一个 is_group=0 会话的对方
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare(
        "SELECT m2.user_id AS other_id, m1.chat_id FROM chat_members m1
         JOIN chat_members m2 ON m1.chat_id = m2.chat_id
         JOIN chat_conversations c ON c.chat_id = m1.chat_id
         WHERE m1.user_id = :u AND m2.user_id <> :u AND c.is_group = 0"
    );
    $_stmt->execute(['u' => $UserId]);
    $_pairs = $_stmt->fetchAll();

    $_friends = [];
    $_seenIds = [];
    foreach ($_pairs as $_p) {
        $_mid = intval($_p['other_id']);
        if (in_array($_mid, $_seenIds, true)) continue;
        $_seenIds[] = $_mid;

        $_stmt2 = $_pdo->prepare("SELECT user_id, nickname, username, avatar, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => $_mid]);
        $_u = $_stmt2->fetch();
        if ($_u && $_u['status'] == 0) {
            $_friends[] = [
                'user_id'  => $_mid,
                'nickname' => $_u['nickname'] ?: $_u['username'],
                'avatar'   => $_u['avatar'] ?: 'icon.png',
                'chat_id'  => $_p['chat_id']
            ];
        }
    }

    echo json_encode(['code' => 0, 'data' => $_friends], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
