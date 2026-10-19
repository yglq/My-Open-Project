<?php
/**
 * 获取对话详细消息（分页）
 * POST: UserId, ChatId
 * POST: Offset (默认0), Limit (默认20)
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    $ChatId = trim($_POST['ChatId'] ?? '');
    $Offset = intval($_POST['Offset'] ?? 0);
    $Limit  = intval($_POST['Limit'] ?? 20);

    if ($UserId <= 0 || $ChatId === '') throw new Exception('参数错误');
    if ($Limit < 1) $Limit = 20;
    if ($Limit > 50) $Limit = 50;

    $_pdo = GetPDO();

    // 取对话
    $_stmt = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ChatId]);
    $_chat = $_stmt->fetch();
    if (!$_chat) throw new Exception('对话不存在');

    $_fullJson = json_decode($_chat['chat_json'] ?? '[]', true);
    $_members = $_fullJson['Members'] ?? [];

    if (!array_key_exists(strval($UserId), $_members)) {
        throw new Exception('无权访问');
    }

    $_totalMsg = intval($_fullJson['TotalMsg'] ?? 0);
    $_allMessages = $_fullJson['Messages'] ?? [];

    // 分页截取：从 Offset 开始，取 Limit 条
    $_slice = array_slice($_allMessages, $Offset, $Limit);

    // 已信任 ImagePath，不做 file_exists 检测

    // 更新用户 chat_list
    $_stmt2 = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $UserId]);
    $_user = $_stmt2->fetch();
    $_chatList = json_decode($_user['chat_list'] ?? '[]', true) ?: [];

    $_found = false;
    foreach ($_chatList as &$_item) {
        if ($_item['chat_id'] === $ChatId) {
            $_item['last_msg_id'] = $_totalMsg;
            $_found = true;
            break;
        }
    }
    unset($_item);
    if (!$_found) {
        $_chatList[] = ['chat_id' => $ChatId, 'last_msg_id' => $_totalMsg];
    }

    $_newChatList = json_encode($_chatList, JSON_UNESCAPED_UNICODE);
    $_stmt3 = $_pdo->prepare("UPDATE users SET chat_list = :clist WHERE user_id = :uid");
    $_stmt3->execute(['clist' => $_newChatList, 'uid' => $UserId]);

    // 更新 ReadList（只在首次打开时更新，即 Offset=0）
    if ($Offset === 0) {
        if (!empty($_fullJson['ReadList'])) {
            $_fullJson['ReadList'][strval($UserId)] = $_totalMsg;
        } else {
            $_fullJson['ReadList'] = [strval($UserId) => $_totalMsg];
        }

        foreach ($_fullJson['ReadList'] as $_uid => $_readId) {
            if (intval($_readId) > $_totalMsg) {
                $_fullJson['ReadList'][$_uid] = $_totalMsg;
            }
        }

        $_newJson = json_encode($_fullJson, JSON_UNESCAPED_UNICODE);
        $_stmt4 = $_pdo->prepare("UPDATE chat_data SET chat_json = :json WHERE chat_id = :cid");
        $_stmt4->execute(['json' => $_newJson, 'cid' => $ChatId]);
    }


        // 获取当前用户已有的数据库表名列表
    $_myTables = [];
    $_stmt5 = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
    $_stmt5->execute(['uid' => $UserId]);
    $_myReg = $_stmt5->fetch();
    if ($_myReg) {
        $_myData = json_decode($_myReg['table_data'], true) ?: [];
        foreach ($_myData as $_item) {
            $_myTables[] = $_item['table'] ?? '';
        }
    }
// 构建已读用户详情列表
$_readUserList = [];
if (!empty($_fullJson['ReadList'])) {
    $_userIds = array_keys($_fullJson['ReadList']);
    if (!empty($_userIds)) {
        $_placeholders = implode(',', array_fill(0, count($_userIds), '?'));
        $_stmt6 = $_pdo->prepare("SELECT user_id, nickname, username, avatar FROM users WHERE user_id IN ($_placeholders)");
        $_stmt6->execute(array_map('intval', $_userIds));
        $_users = $_stmt6->fetchAll();
        foreach ($_users as $_u) {
            $_readUserList[$_u['user_id']] = [
                'name'   => $_u['nickname'] ?: $_u['username'],
                'avatar' => $_u['avatar'] ?: 'icon.png'
            ];
        }
    }
}

// 成员头像映射
$_memberAvatars = [];
foreach ($_members as $_mid => $_mname) {
    $_stmtA = $_pdo->prepare("SELECT avatar FROM users WHERE user_id = :uid LIMIT 1");
    $_stmtA->execute(['uid' => intval($_mid)]);
    $_av = $_stmtA->fetchColumn();
    $_memberAvatars[$_mid] = $_av ?: 'icon.png';
}
// 成员 VIP 状态
$_memberVipStatus = [];
$_now = date('Y-m-d H:i:s');
foreach ($_members as $_mid => $_mname) {
    $_stmtV = $_pdo->prepare("SELECT vip_time, svip_time FROM users WHERE user_id = :uid LIMIT 1");
    $_stmtV->execute(['uid' => intval($_mid)]);
    $_v = $_stmtV->fetch();
    if ($_v && $_v['svip_time'] && $_v['svip_time'] > $_now) {
        $_memberVipStatus[$_mid] = 'svip';
    } elseif ($_v && $_v['vip_time'] && $_v['vip_time'] > $_now) {
        $_memberVipStatus[$_mid] = 'vip';
    } else {
        $_memberVipStatus[$_mid] = 'none';
    }
}

    // 构建返回数据
    $_result = [
        'ChatId'          => $ChatId,
        'Members'         => $_members,
        'TotalMsg'        => $_totalMsg,
        'Offset'          => $Offset,
        'Limit'           => $Limit,
        'HasMore'         => ($Offset + $Limit) < $_totalMsg,
        'Messages'        => $_slice,
        'ReadList'        => $_fullJson['ReadList'] ?? [],
        'GroupAvatar'     => $_fullJson['GroupAvatar'] ?? '',
        'GroupMemberCount'=> $_fullJson['GroupMemberCount'] ?? 0,
        'GroupName'       => $_fullJson['GroupName'] ?? '',
        'IsGroup'         => count($_members) > 2,
'ReadUserList' => $_readUserList,
     'MyTables'        => $_myTables,
     'MemberAvatars' => $_memberAvatars,
     'MemberVipStatus' => $_memberVipStatus
    ];
    echo json_encode([
        'code' => 0,
        'data' => $_result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}