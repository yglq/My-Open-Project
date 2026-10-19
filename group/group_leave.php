<?php
/**
 * 退出/解散群聊
 * POST: UserId, ChatId
 * 
 * 群主退出 = 解散（删全部数据）
 * 成员退出 = 离开（只从自己的 chat_list 移除）
 * 剩 2 人 → 变私聊
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

    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('群聊不存在');

    $_json = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_json['Members'] ?? [];
    $_memberKeys = array_keys($_members);
    $_isOwner = ($_memberKeys[0] == $UserId);

    if ($_isOwner || count($_members) <= 1) {
        // 解散：删 chat_data + 所有成员的 chat_list
        $_stmt2 = $_pdo->prepare("DELETE FROM chat_data WHERE chat_id = :cid");
        $_stmt2->execute(['cid' => $ChatId]);

        foreach ($_members as $_mid => $_mname) {
            $_stmt3 = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid");
            $_stmt3->execute(['uid' => intval($_mid)]);
            $_list = json_decode($_stmt3->fetchColumn(), true) ?: [];
            $_list = array_values(array_filter($_list, function($_item) use ($ChatId) {
                return ($_item['chat_id'] ?? '') !== $ChatId;
            }));
            $_stmt4 = $_pdo->prepare("UPDATE users SET chat_list = :clist WHERE user_id = :uid");
            $_stmt4->execute(['clist' => json_encode($_list, JSON_UNESCAPED_UNICODE), 'uid' => intval($_mid)]);
        }

        // 删图片文件夹
        $_imgDir = __DIR__ . '/../chat_images/' . $ChatId;
        if (is_dir($_imgDir)) {
            array_map('unlink', glob("$_imgDir/*"));
            rmdir($_imgDir);
        }

        echo json_encode(['code' => 0, 'message' => '群聊已解散'], JSON_UNESCAPED_UNICODE);
    } else {
        // 普通成员退出
        unset($_json['Members'][strval($UserId)]);
        $_json['GroupMemberCount'] = count($_json['Members']);

        $_becomePrivate = false;
        if (count($_json['Members']) == 2) {
            unset($_json['GroupAvatar'], $_json['GroupMemberCount'], $_json['GroupName']);
            $_becomePrivate = true;
        }

        $_newJson = json_encode($_json, JSON_UNESCAPED_UNICODE);
        $_stmt5 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
        $_stmt5->execute(['json' => $_newJson, 'cid' => $ChatId]);

        // 从自己的 chat_list 移除
        $_stmt6 = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid");
        $_stmt6->execute(['uid' => $UserId]);
        $_myList = json_decode($_stmt6->fetchColumn(), true) ?: [];
        $_myList = array_values(array_filter($_myList, function($_item) use ($ChatId) {
            return ($_item['chat_id'] ?? '') !== $ChatId;
        }));
        $_stmt7 = $_pdo->prepare("UPDATE users SET chat_list = :clist WHERE user_id = :uid");
        $_stmt7->execute(['clist' => json_encode($_myList, JSON_UNESCAPED_UNICODE), 'uid' => $UserId]);

        // 实时刷新在线成员界面（静默失败）
    wsPublishToMembers(array_map('intval', array_merge(array_map('intval', array_keys($_json['Members'])), [intval($UserId)])), ['type' => 'chat_refresh', 'chat_id' => $ChatId]);

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