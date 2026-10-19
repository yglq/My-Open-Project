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

    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('群聊不存在');

    $_json = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_json['Members'] ?? [];

    if (!array_key_exists(strval($UserId), $_members)) throw new Exception('你不在该群聊中');

    $_added = 0;
    foreach ($_selected as $_fid) {
        $_fid = intval($_fid);
        if (array_key_exists(strval($_fid), $_members)) continue;

        $_stmt2 = $_pdo->prepare("SELECT nickname, username, avatar, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => $_fid]);
        $_f = $_stmt2->fetch();
        if (!$_f || $_f['status'] == 1) continue;

        $_members[strval($_fid)] = $_f['nickname'] ?: $_f['username'];

        // 添加到 chat_list
        $_newItem = json_encode(['chat_id' => $ChatId, 'last_msg_id' => 0]);
        $_pdo->exec("UPDATE users SET chat_list = JSON_ARRAY_APPEND(COALESCE(chat_list, '[]'), '$', CAST('$_newItem' AS JSON)) WHERE user_id = $_fid");

        $_added++;
    }

    $_json['Members'] = $_members;
    $_json['GroupMemberCount'] = count($_members);
    $_newJson = json_encode($_json, JSON_UNESCAPED_UNICODE);
    $_stmt3 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
    $_stmt3->execute(['json' => $_newJson, 'cid' => $ChatId]);

    // 实时刷新在线成员界面（静默失败）
    wsPublishToMembers(array_map('intval', array_keys($_members)), ['type' => 'chat_refresh', 'chat_id' => $ChatId]);

    echo json_encode(['code' => 0, 'message' => "已邀请 $_added 人"], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}