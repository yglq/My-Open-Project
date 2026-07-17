<?php
/**
 * 创建群聊
 * POST: UserId, GroupName, Members (JSON数组 [2, 3, 5])
 * POST: GroupAvatar (文件可选)
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方式错误');
    }

    $UserId    = intval($_POST['UserId'] ?? 0);
    $GroupName = trim($_POST['GroupName'] ?? '');
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

    // 构建 Members
    $_allMembers = [strval($UserId) => $_myName];

    foreach ($_selectedIds as $_fid) {
        $_stmt2 = $_pdo->prepare("SELECT user_id, nickname, username, status FROM users WHERE user_id = :uid LIMIT 1");
        $_stmt2->execute(['uid' => intval($_fid)]);
        $_f = $_stmt2->fetch();
        if (!$_f || $_f['status'] == 1) continue;
        $_allMembers[strval($_f['user_id'])] = $_f['nickname'] ?: $_f['username'];
    }

    if (count($_allMembers) < 3) {
        throw new Exception('有效成员不足，无法创建群聊');
    }

    // 生成 ChatId
    $_newChatId = '';
    for ($_i = 0; $_i < 10; $_i++) {
        $_tempId = randomString(21);
        $_stmt3 = $_pdo->prepare("SELECT COUNT(*) FROM chat_data WHERE chat_id = :cid");
        $_stmt3->execute(['cid' => $_tempId]);
        if ($_stmt3->fetchColumn() == 0) {
            $_newChatId = $_tempId;
            break;
        }
    }
    if ($_newChatId === '') throw new Exception('生成群聊ID失败');

    // 群头像
    $_avatarName = 'icon.png';
    if (!empty($_FILES['GroupAvatar']) && $_FILES['GroupAvatar']['error'] === UPLOAD_ERR_OK) {
        $_file = $_FILES['GroupAvatar'];
        $_imgInfo = @getimagesize($_file['tmp_name']);
        if ($_imgInfo === false) throw new Exception('头像不是有效图片');
        if ($_file['size'] > 2 * 1024 * 1024) throw new Exception('头像不能超过 2MB');

        $_avatarDir = __DIR__ . '/../group_avatars';
        if (!is_dir($_avatarDir)) mkdir($_avatarDir, 0755, true);

        // 简单文件名：群聊ID + .png
        $_avatarName = $_newChatId . '.png';
        if (!move_uploaded_file($_file['tmp_name'], $_avatarDir . '/' . $_avatarName)) {
            $_avatarName = 'icon.png';
        }
    }

    // 写入 chat_data
    $_chatJson = json_encode([
        'ChatId'           => $_newChatId,
        'Members'          => $_allMembers,
        'TotalMsg'         => 0,
        'ReadList'         => [],
        'GroupAvatar'      => $_avatarName,
        'GroupMemberCount' => count($_allMembers),
        'GroupName'        => $GroupName,
        'Messages'         => []
    ], JSON_UNESCAPED_UNICODE);

    $_stmt4 = $_pdo->prepare("INSERT INTO chat_data (chat_id, chat_json) VALUES (:cid, :json)");
    $_stmt4->execute(['cid' => $_newChatId, 'json' => $_chatJson]);

    // 更新所有成员的 chat_list
    $_newItem = json_encode(['chat_id' => $_newChatId, 'last_msg_id' => 0]);
    foreach ($_allMembers as $_mid => $_mname) {
        $_pdo->exec("UPDATE users SET chat_list = JSON_ARRAY_APPEND(COALESCE(chat_list, '[]'), '$', CAST('$_newItem' AS JSON)) WHERE user_id = " . intval($_mid));
    }

    echo json_encode([
        'code'    => 0,
        'chat_id' => $_newChatId,
        'message' => '群聊创建成功'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}