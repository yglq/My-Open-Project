<?php
/**
 * 获取群成员 + 非成员好友
 * POST: UserId, ChatId
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

    // 取群聊数据
    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('群聊不存在');

    $_json = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_json['Members'] ?? [];
    $_isGroup = count($_members) > 2;

    if (!$_isGroup) throw new Exception('不是群聊');

    // 群主 = 第一个创建的人（Members 里按加入顺序排列，取第一个）
    $_memberKeys = array_keys($_members);
    $_ownerId = $_memberKeys[0];

    // 查所有成员信息
    $_memberList = [];
    foreach ($_members as $_mid => $_mname) {
        $_stmt2 = $_pdo->prepare("SELECT user_id, nickname, username, avatar, bio FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => intval($_mid)]);
        $_u = $_stmt2->fetch();
        $_memberList[] = [
            'user_id'  => intval($_mid),
            'name'     => $_u ? ($_u['nickname'] ?: $_u['username']) : $_mname,
            'avatar'   => $_u ? ($_u['avatar'] ?: 'icon.png') : 'icon.png',
            'bio'      => $_u ? ($_u['bio'] ?: '') : '',
            'is_owner' => (intval($_mid) === intval($_ownerId))
        ];
    }

    // 获取当前用户的私聊好友（非成员）
    $_nonMembers = [];
    $_stmt3 = $_pdo->query("SELECT chat_id, chat_json FROM chat_data");
    $_allChats = $_stmt3->fetchAll();
    $_seenIds = array_keys($_members);

    foreach ($_allChats as $_c) {
        $_cJson = json_decode($_c['chat_json'], true);
        $_cMembers = $_cJson['Members'] ?? [];
        if (count($_cMembers) != 2) continue;
        if (!array_key_exists(strval($UserId), $_cMembers)) continue;

        foreach ($_cMembers as $_cmid => $_cmname) {
            if (intval($_cmid) === $UserId || in_array($_cmid, $_seenIds)) continue;
            $_seenIds[] = $_cmid;

            $_stmt4 = $_pdo->prepare("SELECT user_id, nickname, username, avatar, status FROM users WHERE user_id = :uid LIMIT 1");
            $_stmt4->execute(['uid' => intval($_cmid)]);
            $_cu = $_stmt4->fetch();
            if ($_cu && $_cu['status'] == 0) {
                $_nonMembers[] = [
                    'user_id'  => intval($_cmid),
                    'nickname' => $_cu['nickname'] ?: $_cu['username'],
                    'avatar'   => $_cu['avatar'] ?: 'icon.png'
                ];
            }
            break;
        }
    }

    echo json_encode([
        'code'        => 0,
        'members'     => $_memberList,
        'non_members' => $_nonMembers,
        'owner_id'    => intval($_ownerId),
        'is_owner'    => (intval($UserId) === intval($_ownerId)),
        'group_name'  => $_json['GroupName'] ?? '',
        'group_avatar'=> $_json['GroupAvatar'] ?? 'icon.png',
        'member_count'=> count($_members),
        'max_members' => 100
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}