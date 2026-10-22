<?php
/**
 * 邀请好友进群
 * POST: UserId, ChatId, Members (JSON数组)
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId    = intval($_POST['UserId'] ?? 0);
    $ChatId    = trim($_POST['ChatId'] ?? '');
    $_selected = json_decode($_POST['Members'] ?? '[]', true);

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');
    if (empty($_selected)) throw new Exception('请选择好友');

    $_pdo = GetPDO();

    $_stmtC = $_pdo->prepare("SELECT is_group FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmtC->execute(['c' => $ChatId]);
    $_conv = $_stmtC->fetch();
    if (!$_conv) throw new Exception('群聊不存在');

    if (!chatIsMember($_pdo, $ChatId, $UserId)) throw new Exception('你不在该群聊中');

    $_added = 0;
    $_insM = $_pdo->prepare("INSERT IGNORE INTO chat_members (chat_id,user_id,member_name,last_read_id,joined_at) VALUES (?,?,?,0,NOW())");
    foreach ($_selected as $_fid) {
        $_fid = intval($_fid);
        $_stmt2 = $_pdo->prepare("SELECT nickname, username, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => $_fid]);
        $_f = $_stmt2->fetch();
        if (!$_f || $_f['status'] == 1) continue;
        $_insM->execute([$ChatId, $_fid, $_f['nickname'] ?: $_f['username']]);
        if ($_insM->rowCount() > 0) $_added++;
    }

    // 人数 >=3 确保是群
    if (!intval($_conv['is_group'])) {
        $_pdo->prepare("UPDATE chat_conversations SET is_group = 1 WHERE chat_id = :c")->execute(['c' => $ChatId]);
    }

    chatPushRefresh($_pdo, $ChatId);

    echo json_encode(['code' => 0, 'message' => "已邀请 $_added 人"], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
