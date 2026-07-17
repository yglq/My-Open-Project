<?php
/**
 * 清空聊天记录（仅群主）
 * POST: UserId, ChatId
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId = intval($_POST['UserId'] ?? 0);
    $ChatId = trim($_POST['ChatId'] ?? '');

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('群聊不存在');

    $_json = json_decode($_chat['chat_json'] ?? '[]', true);
    $_memberKeys = array_keys($_json['Members'] ?? []);

    // 只有群主
    if ($_memberKeys[0] != $UserId) throw new Exception('只有群主可以清空聊天记录');

    $_json['Messages'] = [];
    $_json['TotalMsg'] = 0;
    $_json['ReadList'] = [];
    $_newJson = json_encode($_json, JSON_UNESCAPED_UNICODE);
    $_stmt2 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
    $_stmt2->execute(['json' => $_newJson, 'cid' => $ChatId]);

    // 所有成员的 chat_list 里 last_msg_id 置 0
    foreach ($_json['Members'] as $_mid => $_mname) {
        $_stmt3 = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid");
        $_stmt3->execute(['uid' => intval($_mid)]);
        $_list = json_decode($_stmt3->fetchColumn(), true) ?: [];
        foreach ($_list as &$_item) {
            if (($_item['chat_id'] ?? '') === $ChatId) {
                $_item['last_msg_id'] = 0;
                break;
            }
        }
        $_stmt4 = $_pdo->prepare("UPDATE users SET chat_list = :clist WHERE user_id = :uid");
        $_stmt4->execute(['clist' => json_encode($_list, JSON_UNESCAPED_UNICODE), 'uid' => intval($_mid)]);
    }

    echo json_encode(['code' => 0, 'message' => '聊天记录已清空'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}