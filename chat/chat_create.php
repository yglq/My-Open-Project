<?php
/**
 * 创建私聊对话
 * POST: UserId, TargetUserId
 * 如果已存在则返回已有的 chat_id
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId       = intval($_POST['UserId'] ?? 0);
    $TargetUserId = intval($_POST['TargetUserId'] ?? 0);

    if ($UserId <= 0 || $TargetUserId <= 0) throw new Exception('参数错误');
    if ($UserId === $TargetUserId) throw new Exception('不能和自己聊天');

    $_pdo = GetPDO();

    // 检查目标用户是否存在且可被搜索
    $_stmt = $_pdo->prepare("SELECT user_id, nickname, username, status, can_search FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $TargetUserId]);
    $_target = $_stmt->fetch();
    if (!$_target) throw new Exception('用户不存在');
    if ($_target['status'] == 1) throw new Exception('该用户已被封号');
    if ($_target['can_search'] == 0) throw new Exception('该用户不可被搜索');

    // 获取自己信息
    $_stmt2 = $_pdo->prepare("SELECT nickname, username FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $UserId]);
    $_me = $_stmt2->fetch();
    $_myName = $_me['nickname'] ?: $_me['username'];
    $_targetName = $_target['nickname'] ?: $_target['username'];

    // 检查是否已有私聊
    $_stmt3 = $_pdo->query("SELECT chat_id, chat_json FROM chat_data");
    $_allChats = $_stmt3->fetchAll();
    foreach ($_allChats as $_chat) {
        $_json = json_decode($_chat['chat_json'] ?? '[]', true);
        $_members = $_json['Members'] ?? [];
        // 私聊：刚好2人，且包含双方
        if (count($_members) == 2 
            && array_key_exists(strval($UserId), $_members) 
            && array_key_exists(strval($TargetUserId), $_members)) {
            // 已存在，直接返回
            echo json_encode([
                'code'     => 0,
                'chat_id'  => $_chat['chat_id'],
                'is_new'   => false,
                'message'  => '已有对话'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 不存在，创建新对话
    $_newChatId = '';
    for ($_i = 0; $_i < 10; $_i++) {
        $_tempId = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 21);
        $_stmt4 = $_pdo->prepare("SELECT COUNT(*) FROM chat_data WHERE chat_id = :cid");
        $_stmt4->execute(['cid' => $_tempId]);
        if ($_stmt4->fetchColumn() == 0) {
            $_newChatId = $_tempId;
            break;
        }
    }
    if ($_newChatId === '') throw new Exception('生成对话ID失败');

    $_chatJson = json_encode([
        'ChatId'  => $_newChatId,
        'Members' => [
            strval($UserId)       => $_myName,
            strval($TargetUserId) => $_targetName
        ],
        'TotalMsg' => 0,
        'ReadList' => [],
        'Messages' => []
    ], JSON_UNESCAPED_UNICODE);

    $_stmt5 = $_pdo->prepare("INSERT INTO chat_data (chat_id, chat_json) VALUES (:cid, :json)");
    $_stmt5->execute(['cid' => $_newChatId, 'json' => $_chatJson]);

    // 更新两人的 chat_list
    $_newItem = json_encode(['chat_id' => $_newChatId, 'last_msg_id' => 0]);
    $_pdo->exec("UPDATE users SET chat_list = JSON_ARRAY_APPEND(COALESCE(chat_list, '[]'), '$', CAST('$_newItem' AS JSON)) WHERE user_id = $UserId");
    $_pdo->exec("UPDATE users SET chat_list = JSON_ARRAY_APPEND(COALESCE(chat_list, '[]'), '$', CAST('$_newItem' AS JSON)) WHERE user_id = $TargetUserId");

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