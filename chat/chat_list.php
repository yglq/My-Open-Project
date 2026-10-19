<?php
/**
 * 获取当前用户的消息列表
 * POST: UserId
 * 返回：对话列表，包含最后一条消息摘要和未读数
 * 新结构：成员关系/已读进度/最后消息全部从三张表查
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 我加入的全部会话 + 我的已读位置
    $_stmt = $_pdo->prepare("SELECT chat_id, last_read_id FROM chat_members WHERE user_id = :u ORDER BY joined_at ASC");
    $_stmt->execute(['u' => $UserId]);
    $_myChats = $_stmt->fetchAll();

    $_result = [];

    foreach ($_myChats as $_mc) {
        $_chatId = $_mc['chat_id'];
        $_lastRead = intval($_mc['last_read_id']);

        $_stmtC = $_pdo->prepare("SELECT is_group, group_name, group_avatar FROM chat_conversations WHERE chat_id = :c LIMIT 1");
        $_stmtC->execute(['c' => $_chatId]);
        $_conv = $_stmtC->fetch();
        if (!$_conv) continue;
        $_isGroup = intval($_conv['is_group']) === 1;

        $_members = chatMemberMap($_pdo, $_chatId);

        // 最后一条消息
        $_stmtL = $_pdo->prepare("SELECT * FROM chat_messages WHERE chat_id = :c ORDER BY msg_id DESC LIMIT 1");
        $_stmtL->execute(['c' => $_chatId]);
        $_lastRow = $_stmtL->fetch();
        $_lastText = '';
        if ($_lastRow) {
            $_lastMsg = chatRowToMsg($_lastRow);
            $_type = $_lastMsg['Type'] ?? 'Message';
            if ($_type === 'Message') $_lastText = $_lastMsg['Text'] ?? '';
            elseif ($_type === 'Image') $_lastText = '[图片]';
            elseif ($_type === 'InviteStudio') $_lastText = '邀请工作室--' . ($_lastMsg['StudioName'] ?? '');
            elseif ($_type === 'SendCoin') $_lastText = '赠送--' . ($_lastMsg['Num'] ?? 0) . '金币';
            elseif ($_type === 'RequestCoin') $_lastText = '索要--' . ($_lastMsg['Num'] ?? 0) . '金币';
            $_lastText = mb_strlen($_lastText) > 20 ? mb_substr($_lastText, 0, 20) . '...' : $_lastText;
        }

        // 未读数：msg_id 大于我已读位置的条数
        $_stmtU = $_pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE chat_id = :c AND msg_id > :r");
        $_stmtU->execute(['c' => $_chatId, 'r' => $_lastRead]);
        $_unread = intval($_stmtU->fetchColumn());

        $_otherMemberName = '';
        $_avatar = 'icon.png';
        $_targetVip = 'none';

        if (!$_isGroup) {
            // 私聊：找对方
            $_otherId = 0;
            foreach ($_members as $_mid => $_mname) {
                if (intval($_mid) !== $UserId) { $_otherId = intval($_mid); $_otherMemberName = $_mname; break; }
            }
            if ($_otherId) {
                $_stmt3 = $_pdo->prepare("SELECT avatar, nickname, vip_time, svip_time FROM users WHERE user_id = :uid LIMIT 1");
                $_stmt3->execute(['uid' => $_otherId]);
                $_t = $_stmt3->fetch();
                if ($_t) {
                    $_otherMemberName = $_t['nickname'] ?: $_otherMemberName;
                    $_avatar = $_t['avatar'] ?: 'icon.png';
                    $_now = date('Y-m-d H:i:s');
                    if ($_t['svip_time'] && $_t['svip_time'] > $_now) $_targetVip = 'svip';
                    elseif ($_t['vip_time'] && $_t['vip_time'] > $_now) $_targetVip = 'vip';
                }
            }
        } else {
            $_otherMemberName = $_conv['group_name'] ?: '群聊';
            $_avatar = $_conv['group_avatar'] ?: 'icon.png';
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
            'members'       => $_members,
            'vip_status'    => $_targetVip,
            'group_color'   => $_groupColor
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
