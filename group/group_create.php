<?php
/**
 * 创建群聊
 * POST: UserId, GroupName, Members (JSON数组 [2, 3, 5])
 * POST: GroupAvatar (文件可选)
 * 新结构：chat_conversations(is_group=1) + chat_members
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId       = intval($_POST['UserId'] ?? 0);
    $GroupName    = trim($_POST['GroupName'] ?? '');
    $_membersJson = trim($_POST['Members'] ?? '[]');

    if ($UserId <= 0) throw new Exception('参数错误');
    if ($GroupName === '') throw new Exception('请输入群聊名称');

    $_selectedIds = json_decode($_membersJson, true);
    if (!is_array($_selectedIds) || count($_selectedIds) < 2) {
        throw new Exception('至少选择 2 位好友');
    }

    $_pdo = GetPDO();

    // 获取自己的信息
    $_stmt = $_pdo->prepare("SELECT nickname, username, avatar FROM users WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_me = $_stmt->fetch();
    if (!$_me) throw new Exception('用户不存在');
    $_myName = $_me['nickname'] ?: $_me['username'];

    $_allMembers = [$UserId => $_myName];

    foreach ($_selectedIds as $_fid) {
        $_stmt2 = $_pdo->prepare("SELECT user_id, nickname, username, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => intval($_fid)]);
        $_f = $_stmt2->fetch();
        if (!$_f || $_f['status'] == 1) continue;
        $_allMembers[intval($_f['user_id'])] = $_f['nickname'] ?: $_f['username'];
    }

    if (count($_allMembers) < 3) {
        throw new Exception('有效成员不足，无法创建群聊');
    }

    $_newChatId = chatNewId($_pdo);

    // 群头像
    $_avatarName = 'icon.png';
    if (!empty($_FILES['GroupAvatar']) && $_FILES['GroupAvatar']['error'] === UPLOAD_ERR_OK) {
        $_file = $_FILES['GroupAvatar'];
        $_imgInfo = @getimagesize($_file['tmp_name']);
        if ($_imgInfo === false) throw new Exception('头像不是有效图片');
        if ($_file['size'] > 2 * 1024 * 1024) throw new Exception('头像不能超过 2MB');

        $_avatarDir = __DIR__ . '/../group_avatars';
        if (!is_dir($_avatarDir)) mkdir($_avatarDir, 0755, true);

        $_avatarName = $_newChatId . '.png';
        if (!move_uploaded_file($_file['tmp_name'], $_avatarDir . '/' . $_avatarName)) {
            $_avatarName = 'icon.png';
        }
    }

    $_pdo->beginTransaction();
    try {
        $_pdo->prepare("INSERT INTO chat_conversations (chat_id,is_group,group_name,group_avatar,owner_id,created_at) VALUES (?,1,?,?,?,NOW())")
             ->execute([$_newChatId, $GroupName, $_avatarName, $UserId]);
        $_insM = $_pdo->prepare("INSERT INTO chat_members (chat_id,user_id,member_name,last_read_id,joined_at) VALUES (?,?,?,0,NOW())");
        foreach ($_allMembers as $_mid => $_mname) {
            $_insM->execute([$_newChatId, intval($_mid), (string)$_mname]);
        }
        $_pdo->commit();
    } catch (Exception $e2) {
        if ($_pdo->inTransaction()) $_pdo->rollBack();
        throw $e2;
    }

    wsPublishToMembers(array_map('intval', array_keys($_allMembers)), ['type' => 'chat_refresh', 'chat_id' => $_newChatId]);

    echo json_encode([
        'code'    => 0,
        'chat_id' => $_newChatId,
        'message' => '群聊创建成功'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
