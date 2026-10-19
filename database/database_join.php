<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();
    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 查询所有 registry，遍历 table_data 找到匹配的项
    $_stmt = $_pdo->query("SELECT id, user_id, table_data FROM data_tables_registry");
    $_all = $_stmt->fetchAll();

    $_matchedOwnerId = null;
    $_dbName         = '';
    $_actualTokey    = null;

    foreach ($_all as $_row) {
        $_data = json_decode($_row['table_data'] ?? '[]', true) ?: [];
        foreach ($_data as $_item) {
            if (($_item['table'] ?? '') === $ConfigId) {
                $_matchedOwnerId = $_row['user_id'];
                $_dbName = $_item['name'] ?? '';
                $_actualTokey = $_item['tokey'] ?? '';
                break 2;
            }
        }
    }

    if (!$_matchedOwnerId) throw new Exception('数据库不存在');
    if ($_actualTokey !== $Tokey) throw new Exception('密钥错误');

    // 检查是否已经加入
    $_stmt2 = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt2->execute(['uid' => $UserId]);
    $_myReg = $_stmt2->fetch();
    $_myData = $_myReg ? (json_decode($_myReg['table_data'], true) ?: []) : [];
    foreach ($_myData as $_item) {
        if (($_item['table'] ?? '') === $ConfigId) throw new Exception('你已经在该数据库中');
    }

    // 获取用户信息
    $_stmtU = $_pdo->prepare("SELECT nickname, username, avatar FROM users WHERE user_id = :uid LIMIT 1");
    $_stmtU->execute(['uid' => $UserId]);
    $_userInfo = $_stmtU->fetch();
    $_userName = $_userInfo['nickname'] ?: $_userInfo['username'];
    $_userAvatar = $_userInfo['avatar'] ?: 'icon.png';

  $_newItem = [
    'table'    => $ConfigId,
    'name'     => $_dbName ?: '数据库',
    'role'     => 1,
    'display'  => '成员',
    'owner_id' => $_matchedOwnerId,
    'tokey'    => $_actualTokey  // 这个要有
];

    if ($_myReg) {
        $_myData[] = $_newItem;
        $_newJson = json_encode($_myData, JSON_UNESCAPED_UNICODE);
        $_stmt3 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :td WHERE user_id = :uid");
        $_stmt3->execute(['td' => $_newJson, 'uid' => $UserId]);
    } else {
        $_newJson = json_encode([$_newItem], JSON_UNESCAPED_UNICODE);
        $_stmt3 = $_pdo->prepare("INSERT INTO data_tables_registry (user_id, table_data) VALUES (:uid, :td)");
        $_stmt3->execute(['uid' => $UserId, 'td' => $_newJson]);
    }

    // 更新原记录的 member_count
    $_stmt4 = $_pdo->prepare("UPDATE data_tables_registry SET member_count = member_count + 1 WHERE user_id = :oid");
    $_stmt4->execute(['oid' => $_matchedOwnerId]);

    // 更新 admin_config.studio_members
    $_stmtM = $_pdo->prepare("SELECT studio_members FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmtM->execute(['cid' => $ConfigId]);
    $_mJson = $_stmtM->fetchColumn();
    $_mData = $_mJson ? (json_decode($_mJson, true) ?: []) : [];

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
            'avatar'    => $_userAvatar,
            'role'      => 'member',
            'joined_at' => date('Y-m-d-H-i-s')
        ];
        $_stmtU2 = $_pdo->prepare("UPDATE admin_config SET studio_members = :sm WHERE config_id = :cid");
        $_stmtU2->execute(['sm' => json_encode($_mData, JSON_UNESCAPED_UNICODE), 'cid' => $ConfigId]);
    }
// 确保 admin_config 记录存在
$_stmtCheck = $_pdo->prepare("SELECT COUNT(*) FROM admin_config WHERE config_id = :cid");
$_stmtCheck->execute(['cid' => $ConfigId]);
if ($_stmtCheck->fetchColumn() == 0) {
    $_stmtIns = $_pdo->prepare("INSERT INTO admin_config (config_id, user_id, api_token) VALUES (:cid, :uid, :token)");
    $_stmtIns->execute([
        'cid'   => $ConfigId,
        'uid'   => $_matchedOwnerId,
        'token' => substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 21)
    ]);
}

    echo json_encode(['code' => 0, 'message' => '加入成功'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}