<?php
/**
 * 获取当前用户的所有私聊好友
 * POST: UserId
 * 返回：好友列表 [{user_id, nickname, avatar, chat_id}]
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 只取包含当前用户的对话（JSON 成员索引过滤，不再全表扫描）
    $_stmt = $_pdo->prepare("SELECT chat_id, chat_json FROM chat_data WHERE JSON_CONTAINS_PATH(chat_json, 'one', :path)");
    $_stmt->execute(['path' => '$.Members."' . $UserId . '"']);
    $_allChats = $_stmt->fetchAll();

    $_friends = [];
    $_seenIds = [];

    foreach ($_allChats as $_chat) {
        $_json = json_decode($_chat['chat_json'] ?? '[]', true);
        $_members = $_json['Members'] ?? [];

        // 只要私聊（2人）
        if (count($_members) != 2) continue;
        if (!array_key_exists(strval($UserId), $_members)) continue;

        // 找对方
        foreach ($_members as $_mid => $_mname) {
            if (intval($_mid) === $UserId) continue;
            if (in_array($_mid, $_seenIds)) continue;
            $_seenIds[] = $_mid;

            // 查对方信息
            $_stmt2 = $_pdo->prepare("SELECT user_id, nickname, username, avatar, status FROM users WHERE user_id = :uid LIMIT 1");
            $_stmt2->execute(['uid' => intval($_mid)]);
            $_u = $_stmt2->fetch();

            if ($_u && $_u['status'] == 0) {
                $_friends[] = [
                    'user_id'  => intval($_mid),
                    'nickname' => $_u['nickname'] ?: $_u['username'],
                    'avatar'   => $_u['avatar'] ?: 'icon.png',
                    'chat_id'  => $_chat['chat_id']
                ];
            }
            break;
        }
    }

    echo json_encode(['code' => 0, 'data' => $_friends], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}