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

    $_stmt = $_pdo->prepare("SELECT is_group, group_name, group_avatar, owner_id FROM chat_conversations WHERE chat_id = :c LIMIT 1");
    $_stmt->execute(['c' => $ChatId]);
    $_conv = $_stmt->fetch();
    if (!$_conv) throw new Exception('群聊不存在');
    if (!intval($_conv['is_group'])) throw new Exception('不是群聊');

    $_ownerId = intval($_conv['owner_id']);
    $_members = chatMemberMap($_pdo, $ChatId);

    // 成员详情（批量）
    $_memberIds = array_map('intval', array_keys($_members));
    $_usersById = [];
    if (!empty($_memberIds)) {
        $_ph = implode(',', array_fill(0, count($_memberIds), '?'));
        $_stmtU = $_pdo->prepare("SELECT user_id, nickname, username, avatar, bio FROM users WHERE user_id IN ($_ph)");
        $_stmtU->execute($_memberIds);
        foreach ($_stmtU->fetchAll() as $_u) $_usersById[intval($_u['user_id'])] = $_u;
    }

    $_memberList = [];
    foreach ($_members as $_mid => $_mname) {
        $_mid = intval($_mid);
        $_u = $_usersById[$_mid] ?? null;
        $_memberList[] = [
            'user_id'  => $_mid,
            'name'     => $_u ? ($_u['nickname'] ?: $_u['username']) : $_mname,
            'avatar'   => $_u ? ($_u['avatar'] ?: 'icon.png') : 'icon.png',
            'bio'      => $_u ? ($_u['bio'] ?: '') : '',
            'is_owner' => ($_mid === $_ownerId)
        ];
    }

    // 非成员好友：与我有私聊（is_group=0 共同成员）但不在此群的人
    $_nonMembers = [];
    $_seenIds = array_map('intval', $_memberIds);
    $_stmtF = $_pdo->prepare(
        "SELECT DISTINCT m2.user_id FROM chat_members m1
         JOIN chat_members m2 ON m1.chat_id = m2.chat_id
         JOIN chat_conversations c ON c.chat_id = m1.chat_id
         WHERE m1.user_id = :u AND m2.user_id <> :u AND c.is_group = 0"
    );
    $_stmtF->execute(['u' => $UserId]);
    foreach ($_stmtF->fetchAll(PDO::FETCH_COLUMN) as $_fid) {
        $_fid = intval($_fid);
        if (in_array($_fid, $_seenIds, true)) continue;
        $_seenIds[] = $_fid;
        $_stmt4 = $_pdo->prepare("SELECT user_id, nickname, username, avatar, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt4->execute(['uid' => $_fid]);
        $_cu = $_stmt4->fetch();
        if ($_cu && $_cu['status'] == 0) {
            $_nonMembers[] = [
                'user_id'  => $_fid,
                'nickname' => $_cu['nickname'] ?: $_cu['username'],
                'avatar'   => $_cu['avatar'] ?: 'icon.png'
            ];
        }
    }

    echo json_encode([
        'code'        => 0,
        'members'     => $_memberList,
        'non_members' => $_nonMembers,
        'owner_id'    => $_ownerId,
        'is_owner'    => ($UserId === $_ownerId),
        'group_name'  => $_conv['group_name'] ?? '',
        'group_avatar'=> $_conv['group_avatar'] ?? 'icon.png',
        'member_count'=> count($_members),
        'max_members' => 100
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
