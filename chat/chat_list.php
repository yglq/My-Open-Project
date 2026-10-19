<?php
/**
 * 获取当前用户的消息列表
 * POST: UserId
 * 返回：对话列表，包含最后一条消息摘要和未读数
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 获取用户的 chat_list
    $_stmt = $_pdo->prepare("SELECT chat_list FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_user = $_stmt->fetch();
    if (!$_user) throw new Exception('用户不存在');

    $_chatList = json_decode($_user['chat_list'] ?? '[]', true) ?: [];
    $_result = [];

    foreach ($_chatList as $_item) {
        $_chatId = $_item['chat_id'];
        $_lastMsgId = intval($_item['last_msg_id'] ?? 0);

        // 取对话数据
        $_stmt2 = $_pdo->prepare("SELECT chat_json FROM chat_data WHERE chat_id = :cid LIMIT 1");
        $_stmt2->execute(['cid' => $_chatId]);
        $_chat = $_stmt2->fetch();
        if (!$_chat) continue;

        $_chatJson = json_decode($_chat['chat_json'] ?? '[]', true);
        $_members = $_chatJson['Members'] ?? [];
        $_messages = $_chatJson['Messages'] ?? [];
        $_totalMsg = intval($_chatJson['TotalMsg'] ?? 0);

        // 判断私聊还是群聊
        $_isGroup = count($_members) > 2;
        $_otherMemberId = null;
        $_otherMemberName = '';
        $_avatar = 'icon.png';

        if (!$_isGroup) {
            // 私聊：找对方 ID
            foreach ($_members as $_mid => $_mname) {
                if (intval($_mid) !== $UserId) {
                    $_otherMemberId = $_mid;
                    $_otherMemberName = $_mname;
                    break;
                }
            }
            // 获取对方头像（简单取 users 表）
            if ($_otherMemberId) {
                $_stmt3 = $_pdo->prepare("SELECT avatar, nickname FROM users WHERE user_id = :uid LIMIT 1");
                $_stmt3->execute(['uid' => $_otherMemberId]);
                $_targetUser = $_stmt3->fetch();
                if ($_targetUser) {
                    $_otherMemberName = $_targetUser['nickname'] ?: $_otherMemberName;
                    $_avatar = $_targetUser['avatar'] ?: 'icon.png';
                }
            }
        } else {
            // 群聊
            $_otherMemberName = $_chatJson['GroupName'] ?? '群聊';
            $_avatar = $_chatJson['GroupAvatar'] ?? 'icon.png';
        }

        // 最后一条消息
        $_lastMsg = !empty($_messages) ? $_messages[0] : null;
        $_lastText = '';
        if ($_lastMsg) {
            $_type = $_lastMsg['Type'] ?? 'Message';
       if ($_type === 'Message') {
    $_lastText = $_lastMsg['Text'] ?? '';
} elseif ($_type === 'Image') {
    $_lastText = '[图片]';
} elseif ($_type === 'InviteStudio') {
    $_lastText = '邀请工作室--' . ($_lastMsg['StudioName'] ?? '');
} elseif ($_type === 'SendCoin') {
    $_lastText = '赠送--' . ($_lastMsg['Num'] ?? 0) . '金币';
} elseif ($_type === 'RequestCoin') {
    $_lastText = '索要--' . ($_lastMsg['Num'] ?? 0) . '金币';
}
            $_lastText = mb_strlen($_lastText) > 20 ? mb_substr($_lastText, 0, 20) . '...' : $_lastText;
        }

        // 未读数
        $_unread = max(0, $_totalMsg - $_lastMsgId);
// 获取对方 VIP 状态（私聊）
$_targetVip = 'none';
if (!$_isGroup && $_otherMemberId) {
    $_stmtV = $_pdo->prepare("SELECT vip_time, svip_time FROM users WHERE user_id = :uid LIMIT 1");
    $_stmtV->execute(['uid' => $_otherMemberId]);
    $_v = $_stmtV->fetch();
    $_now = date('Y-m-d H:i:s');
    if ($_v && $_v['svip_time'] && $_v['svip_time'] > $_now) {
        $_targetVip = 'svip';
    } elseif ($_v && $_v['vip_time'] && $_v['vip_time'] > $_now) {
        $_targetVip = 'vip';
    }
}

// 群聊颜色从 server_config 取
$_groupColor = '#1a1a1a';
if ($_isGroup) {
    $_stmtSC = $_pdo->query("SELECT group_color FROM server_config LIMIT 1");
    $_sc = $_stmtSC->fetch();
    if ($_sc) {
        $_colorData = json_decode($_sc['group_color'], true);
        if ($_colorData) {
            krsort($_colorData);
            $_colorMap = [0 => '#1a1a1a', 1 => '#2563eb', 2 => '#7c3aed', 3 => '#dc2626'];
            foreach ($_colorData as $_threshold => $_cidx) {
                if (count($_members) >= intval($_threshold)) {
                    $_groupColor = $_colorMap[$_cidx] ?? '#1a1a1a';
                    break;
                }
            }
        }
    }
}

     $_result[] = [
    'chat_id'       => $_chatId,
    'is_group'      => $_isGroup,
    'name'          => $_otherMemberName,
    'avatar'        => $_avatar,
    'last_msg'      => $_lastText,
    'unread'        => $_unread,
    'members_count' => count($_members),
    'members'       => $_members , // 新增：返回成员列表
'vip_status'  => $_targetVip,
'group_color' => $_groupColor
    ];
    }

    echo json_encode([
        'code' => 0,
        'data' => $_result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}