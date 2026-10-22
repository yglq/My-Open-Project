<?php
/**
 * 获取对话详细消息（分页）
 * POST: UserId, ChatId
 * POST: Offset (默认0), Limit (默认20)
 * 新结构：消息从 chat_messages 查，已读进度存 chat_members.last_read_id
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

    if (!chatIsMember($_pdo, $ChatId, $UserId)) {
        throw new Exception('无权访问');
    }

    // 会话信息
    $_stmtC = $_pdo->prepare("SELECT is_group, group_name, group_avatar, owner_id FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmtC->execute(['c' => $ChatId]);
    $_conv = $_stmtC->fetch();
    if (!$_conv) throw new Exception('对话不存在');
    $_isGroup = intval($_conv['is_group']) === 1;

    $_members = chatMemberMap($_pdo, $ChatId);

    // 消息总数 + 分页（倒序，最新在前）
    $_stmtN = $_pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE chat_id = :c");
    $_stmtN->execute(['c' => $ChatId]);
    $_totalMsg = intval($_stmtN->fetchColumn());

    $_stmtM = $_pdo->prepare("SELECT * FROM chat_messages WHERE chat_id = :c ORDER BY msg_id DESC LIMIT :o, :l");
    $_stmtM->bindValue('o', $Offset, PDO::PARAM_INT);
    $_stmtM->bindValue('l', $Limit, PDO::PARAM_INT);
    $_stmtM->bindValue('c', $ChatId);
    $_stmtM->execute();
    $_rows = $_stmtM->fetchAll();
    $_slice = array_map('chatRowToMsg', $_rows);

    // 标记已读（仅在首屏，即 Offset=0）
    $_maxId = chatMaxMsgId($_pdo, $ChatId);
    if ($Offset === 0) {
        $_pdo->prepare("UPDATE chat_members SET last_read_id = :m WHERE chat_id = :c AND user_id = :u")
             ->execute(['m' => $_maxId, 'c' => $ChatId, 'u' => $UserId]);
    }

    // 已读进度：user_id => last_read_id
    $_readList = chatMemberIds($_pdo, $ChatId);
    $_stmtR = $_pdo->prepare("SELECT user_id, last_read_id FROM chat_members WHERE chat_id = :c");
    $_stmtR->execute(['c' => $ChatId]);
    $_readList = array_map('intval', $_stmtR->fetchAll(PDO::FETCH_KEY_PAIR));

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

    // 已读用户详情列表
    $_readUserList = [];
    if (!empty($_readList)) {
        $_userIds = array_keys($_readList);
        $_placeholders = implode(',', array_fill(0, count($_userIds), '?'));
        $_stmt6 = $_pdo->prepare("SELECT user_id, nickname, username, avatar FROM users WHERE user_id IN ($_placeholders)");
        $_stmt6->execute(array_map('intval', $_userIds));
        foreach ($_stmt6->fetchAll() as $_u) {
            $_readUserList[$_u['user_id']] = [
                'name'   => $_u['nickname'] ?: $_u['username'],
                'avatar' => $_u['avatar'] ?: 'icon.png'
            ];
        }
    }

    // 成员头像 + VIP（批量一次查完）
    $_memberIds = array_map('intval', array_keys($_members));
    $_memberAvatars = [];
    $_memberVipStatus = [];
    $_now = date('Y-m-d H:i:s');
    if (!empty($_memberIds)) {
        $_ph = implode(',', array_fill(0, count($_memberIds), '?'));
        $_stmtU = $_pdo->prepare("SELECT user_id, avatar, vip_time, svip_time FROM users WHERE user_id IN ($_ph)");
        $_stmtU->execute($_memberIds);
        foreach ($_stmtU->fetchAll() as $_u) {
            $_memberAvatars[$_u['user_id']] = $_u['avatar'] ?: 'icon.png';
            if ($_u['svip_time'] && $_u['svip_time'] > $_now) $_memberVipStatus[$_u['user_id']] = 'svip';
            elseif ($_u['vip_time'] && $_u['vip_time'] > $_now) $_memberVipStatus[$_u['user_id']] = 'vip';
            else $_memberVipStatus[$_u['user_id']] = 'none';
        }
    }

    $_result = [
        'ChatId'          => $ChatId,
        'Members'         => $_members,
        'TotalMsg'        => $_totalMsg,
        'Offset'          => $Offset,
        'Limit'           => $Limit,
        'HasMore'         => ($Offset + $Limit) < $_totalMsg,
        'Messages'        => $_slice,
        'ReadList'        => $_readList,
        'GroupAvatar'     => $_conv['group_avatar'] ?? '',
        'GroupMemberCount'=> count($_members),
        'GroupName'       => $_conv['group_name'] ?? '',
        'IsGroup'         => $_isGroup,
        'ReadUserList'    => $_readUserList,
        'MyTables'        => $_myTables,
        'MemberAvatars'   => $_memberAvatars,
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
