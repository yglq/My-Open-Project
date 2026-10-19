<?php
/**
 * 踢出群聊
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
    if ($UserId === $TargetUserId) throw new Exception('不能踢自己');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('群聊不存在');

    $_json = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_json['Members'] ?? [];

    // 检查是否是群主
    $_memberKeys = array_keys($_members);
    if ($_memberKeys[0] != $UserId) throw new Exception('只有群主可以踢人');

    // 检查目标在不在群里
    if (!array_key_exists(strval($TargetUserId), $_members)) throw new Exception('该用户不在群里');

    // 删除成员
    unset($_json['Members'][strval($TargetUserId)]);
    $_json['GroupMemberCount'] = count($_json['Members']);

    // 如果只剩 2 人 → 变私聊，删群字段
    $_becomePrivate = false;
    if (count($_json['Members']) == 2) {
        unset($_json['GroupAvatar'], $_json['GroupMemberCount'], $_json['GroupName']);
        $_becomePrivate = true;
    }

    $_newJson = json_encode($_json, JSON_UNESCAPED_UNICODE);
    $_stmt2 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
    $_stmt2->execute(['json' => $_newJson, 'cid' => $ChatId]);

    // 从被踢者的 chat_list 中移除
    $_stmt3 = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid");
    $_stmt3->execute(['uid' => $TargetUserId]);
    $_targetList = json_decode($_stmt3->fetchColumn(), true) ?: [];
    $_targetList = array_filter($_targetList, function($_item) use ($ChatId) {
        return ($_item['chat_id'] ?? '') !== $ChatId;
    });
    $_targetList = array_values($_targetList);
    $_stmt4 = $_pdo->prepare("UPDATE users SET chat_list = :clist WHERE user_id = :uid");
    $_stmt4->execute(['clist' => json_encode($_targetList, JSON_UNESCAPED_UNICODE), 'uid' => $TargetUserId]);

    echo json_encode([
        'code'         => 0,
        'message'      => '已踢出',
        'become_private' => $_becomePrivate
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}