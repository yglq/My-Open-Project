<?php
/**
 * 接受工作室邀请
 * POST: UserId, ConfigId, Tokey
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ChatId   = trim($_POST['ChatId'] ?? '');
    $MsgId    = intval($_POST['MsgId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $ChatId === '' || $MsgId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 校验邀请消息：必须存在且为 InviteStudio，且当前用户在该对话中（避免把密钥泄露到聊天）
    if (!chatIsMember($_pdo, $ChatId, $UserId)) {
        throw new Exception('你不在该对话中');
    }
    $_stmtM = $_pdo->prepare("SELECT * FROM chat_messages WHERE msg_id = :m AND chat_id = :c AND mtype = 'InviteStudio' LIMIT 1");
    $_stmtM->execute(['m' => $MsgId, 'c' => $ChatId]);
    $_mRow = $_stmtM->fetch();
    $_invite = $_mRow ? chatRowToMsg($_mRow) : null;
    if (!$_invite || ($_invite['ConfigId'] ?? '') !== $ConfigId) {
        throw new Exception('邀请消息不存在或已失效');
    }

    // 校验邀请者确实是该工作室的成员/创建者
    $_senderId = intval($_invite['SenderId'] ?? 0);
    $_stmtS = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
    $_stmtS->execute(['uid' => $_senderId]);
    $_sReg = $_stmtS->fetch();
    $_sData = $_sReg ? (json_decode($_sReg['table_data'], true) ?: []) : [];
    $_senderOk = false;
    foreach ($_sData as $_item) {
        if (($_item['table'] ?? '') === $ConfigId) { $_senderOk = true; break; }
    }
    if (!$_senderOk) throw new Exception('邀请者无权邀请该工作室');

    // 查工作室（用于获取 owner_id / tokey / record）
    $_stmt = $_pdo->prepare(
      "SELECT id, user_id, table_data, record FROM data_tables_registry 
         WHERE JSON_SEARCH(table_data, 'one', :tbl, NULL, '$[*].table') IS NOT NULL 
        LIMIT 1"
    );
    $_stmt->execute(['tbl' => $ConfigId]);

    $_reg = $_stmt->fetch();

    if (!$_reg) throw new Exception('工作室不存在');

    // 从 table_data JSON 中取 tokey（仅服务端内部使用，不对外暴露）
    $_actualTokey = '';
    $_regData = json_decode($_reg['table_data'], true) ?: [];
    foreach ($_regData as $_item) {
        if (($_item['table'] ?? '') === $ConfigId) {
            $_actualTokey = $_item['tokey'] ?? '';
            break;
        }
    }

// 检查是否已经在工作室里
   $_stmt2 = $_pdo->prepare(
    "SELECT id FROM data_tables_registry 
     WHERE user_id = :uid 
     AND JSON_SEARCH(table_data, 'one', :tbl, NULL, '$[*].table') IS NOT NULL 
     LIMIT 1"
);
$_stmt2->execute(['uid' => $UserId, 'tbl' => $ConfigId]);
  if ($_stmt2->fetch()) {
        throw new Exception('你已经在工作室中');
    }

    // 获取用户信息
 $_stmt3 = $_pdo->prepare("SELECT nickname, username, avatar FROM users WHERE user_id = :uid LIMIT 1");
    $_stmt3->execute(['uid' => $UserId]);
    $_user = $_stmt3->fetch();
    $_userName = $_user['nickname'] ?: $_user['username'];

    // 获取工作室创建者信息（从 record 中取第一条 create 记录）
    $_records = json_decode($_reg['record'], true) ?: [];
    $_ownerId = $_reg['user_id'];

    // 更新用户的 table_data，加入工作室
    $_stmt4 = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
    $_stmt4->execute(['uid' => $UserId]);
    $_myReg = $_stmt4->fetch();

    if (!$_myReg) {
        // 用户还没有 registry 记录，新建
  $_newData = json_encode([
    [
        'table'    => $ConfigId,
        'name'     => $_reg['table_data'] ? json_decode($_reg['table_data'], true)[0]['name'] : '工作室',
        'role'     => 1,
        'display'  => '成员',
        'owner_id' => $_ownerId,
        'tokey'    => $_actualTokey  // 加这行
    ]
], JSON_UNESCAPED_UNICODE);

        $_stmt5 = $_pdo->prepare("INSERT INTO data_tables_registry (user_id, table_data, record, created_at) VALUES (:uid, :tdata, '[]', NOW())");
        $_stmt5->execute(['uid' => $UserId, 'tdata' => $_newData]);
    } else {
        $_myData = json_decode($_myReg['table_data'], true) ?: [];
        // 检查是否已存在
        $_exists = false;
        foreach ($_myData as $_item) {
            if ($_item['table'] === $ConfigId) {
                $_exists = true;
                break;
            }
        }
        if (!$_exists) {
   $_myData[] = [
    'table'    => $ConfigId,
    'name'     => json_decode($_reg['table_data'], true)[0]['name'] ?? '工作室',
    'role'     => 1,
    'display'  => '成员',
    'owner_id' => $_ownerId,
    'tokey'    => $_actualTokey  // 加这行
];
        $_newData = json_encode($_myData, JSON_UNESCAPED_UNICODE);
            $_stmt5 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :tdata WHERE user_id = :uid");
            $_stmt5->execute(['tdata' => $_newData, 'uid' => $UserId]);
        }
    }

    // 更新工作室记录
    $_records[] = [
        'action'    => 'join',
        'user_id'   => $UserId,
        'user_name' => $_userName,
        'time'      => date('Y-m-d-H-i-s'),
        'role'      => 'member'
    ];
    $_newRecord = json_encode($_records, JSON_UNESCAPED_UNICODE);
    $_stmt6 = $_pdo->prepare("UPDATE data_tables_registry SET record = :rec, member_count = member_count + 1 WHERE id = :rid");
    $_stmt6->execute(['rec' => $_newRecord, 'rid' => $_reg['id']]);

// 更新 admin_config.studio_members
$_stmtM = $_pdo->prepare("SELECT studio_members FROM admin_config WHERE config_id = :cid LIMIT 1");
$_stmtM->execute(['cid' => $ConfigId]);
$_mJson = $_stmtM->fetchColumn();
$_mData = $_mJson ? (json_decode($_mJson, true) ?: []) : [];

// 检查是否已在列表中
$_alreadyIn = false;
foreach ($_mData as $_m) {
    if (($_m['user_id'] ?? 0) == $UserId) {
        $_alreadyIn = true;
        break;
    }
}
if (!$_alreadyIn) {
    $_mData[] = [
        'user_id'   => $UserId,
        'name'      => $_userName,
        'avatar'    => $_user['avatar'] ?? 'icon.png',
        'role'      => 'member',
        'joined_at' => date('Y-m-d-H-i-s')
    ];
    $_stmtU = $_pdo->prepare("UPDATE admin_config SET studio_members = :sm WHERE config_id = :cid");
    $_stmtU->execute(['sm' => json_encode($_mData, JSON_UNESCAPED_UNICODE), 'cid' => $ConfigId]);
}
// 确保 admin_config 记录存在
$_stmtCheck = $_pdo->prepare("SELECT COUNT(*) FROM admin_config WHERE config_id = :cid");
$_stmtCheck->execute(['cid' => $ConfigId]);
if ($_stmtCheck->fetchColumn() == 0) {
    $_stmtIns = $_pdo->prepare("INSERT INTO admin_config (config_id, user_id, api_token) VALUES (:cid, :uid, :token)");
    $_stmtIns->execute([
        'cid'   => $ConfigId,
        'uid'   => $_ownerId,
        'token' => substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 21)
    ]);
}

    echo json_encode(['code' => 0, 'message' => '加入成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}