<?php
/**
 * 注册接口
 * 接收 POST：UserName, NickName, Password, QQ, Avatar(文件可选)
 * 返回 JSON：{"code":0, "message":"ok", "userId":123}
 */

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

// ========== 主逻辑 ==========
try {

    requirePost();

    $_pdo = GetPDO();
    rateLimit('user/register', 3, 300);

    // ---------- 接收并校验参数 ----------
    $UserName = trim($_POST['UserName'] ?? '');
    $NickName = trim($_POST['NickName'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $QQ       = trim($_POST['QQ'] ?? '');

    if (mb_strlen($UserName) < 4 || mb_strlen($UserName) > 20) {
        throw new Exception('用户名需4-20位');
    }
    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $UserName)) {
        throw new Exception('用户名只能包含中英文、数字、下划线');
    }
    if (mb_strlen($NickName) < 1 || mb_strlen($NickName) > 30) {
        throw new Exception('昵称需1-30位');
    }
    if (strlen($Password) < 8) {
        throw new Exception('密码至少8位');
    }
    if (!preg_match('/[a-zA-Z]/', $Password) || !preg_match('/[0-9]/', $Password)) {
        throw new Exception('密码必须包含字母和数字');
    }
    if ($QQ !== '' && !preg_match('/^\d{5,15}$/', $QQ)) {
        throw new Exception('QQ号格式不正确');
    }

    // ---------- 检查用户名唯一 ----------
    $_stmt = $_pdo->prepare("SELECT user_id FROM users WHERE username = :uname LIMIT 1");
    $_stmt->execute(['uname' => $UserName]);
    if ($_stmt->fetch()) {
        throw new Exception('用户名已被注册');
    }

    // ---------- 处理头像 ----------
    $avatarFileName = '';
    $avatarsDir = __DIR__ . '/../avatars';

    if (!is_dir($avatarsDir)) {
        mkdir($avatarsDir, 0755, true);
    }

    if (!empty($_FILES['Avatar']) && $_FILES['Avatar']['error'] === UPLOAD_ERR_OK) {
        $_file = $_FILES['Avatar'];

        $_imgInfo = @getimagesize($_file['tmp_name']);
        if ($_imgInfo === false) {
            throw new Exception('头像不是有效的图片');
        }
        if ($_imgInfo['mime'] !== 'image/png') {
            throw new Exception('头像必须是 PNG 格式');
        }

        if ($_file['size'] > 5 * 1024 * 1024) {
            throw new Exception('头像文件不能超过 5MB');
        }

        $_maxRetry = 10;
        for ($_i = 0; $_i < $_maxRetry; $_i++) {
            $avatarFileName = randomString(21, 'avatar') . '.png';
            if (!file_exists($avatarsDir . '/' . $avatarFileName)) {
                break;
            }
            if ($_i === $_maxRetry - 1) {
                throw new Exception('头像文件名生成失败，请重试');
            }
        }

        if (!move_uploaded_file($_file['tmp_name'], $avatarsDir . '/' . $avatarFileName)) {
            throw new Exception('头像上传失败');
        }
    }

    // ---------- 生成子数据表名 ----------
    $_dataTable = generateUniqueTableName();

    // ---------- 获取客户端 IP ----------
    $_regIp = getClientIp();

    // ---------- 写入 users 表 ----------
    $_stmt = $_pdo->prepare("INSERT INTO users (username, password, qq, avatar, nickname, gender, bio, status, role, vip_time, svip_time, gold, exp, created_at, reg_ip) 
                             VALUES (:uname, :pwd, :qq, :avatar, :nick, 0, '', 0, 'owner', :vip, :svip, 0, 0, NOW(), :ip)");
    $_stmt->execute([
        'uname'  => $UserName,
        'pwd' => hashPwd($Password),
        'qq'     => $QQ,
        'avatar' => $avatarFileName,
        'nick'   => $NickName,
        'vip'    => date('Y-m-d H:i:s', strtotime('+3 days')),
        'svip'   => date('Y-m-d H:i:s'),
        'ip'     => $_regIp,
    ]);

    $_newUserId = $_pdo->lastInsertId();

    // ---------- 写入 data_tables_registry ----------
    $_record = json_encode([
        [
            'action'  => 'create',
            'user_id' => $_newUserId,
            'time'    => date('Y-m-d-H-i-s')
        ]
    ], JSON_UNESCAPED_UNICODE);

    $_defTokey = randomString(21, 'table');
    $_jsonData = json_encode([
        [
            'table' => $_dataTable,
            'name'  => '默认数据库',
            'tokey' => $_defTokey
        ]
    ], JSON_UNESCAPED_UNICODE);

    $_stmtR = $_pdo->prepare("INSERT INTO data_tables_registry (user_id, table_data, record, created_at) VALUES (:uid, :tdata, :rec, NOW())");
    $_stmtR->execute([
        'uid'   => $_newUserId,
        'tdata' => $_jsonData,
        'rec'   => $_record,
    ]);

    // Tokey 独立凭证表
    $_pdo->prepare("INSERT IGNORE INTO db_credentials (config_id,tokey,owner_id,created_at) VALUES (?,?,?,NOW())")
         ->execute([$_dataTable, $_defTokey, $_newUserId]);

    // ---------- 创建 admin_config + studio_members ----------
    $_apiToken = randomString(21, 'table');
    $_initMembers = json_encode([[
        'user_id'   => $_newUserId,
        'name'      => $NickName ?: $UserName,
        'avatar'    => $avatarFileName ?: 'icon.png',
        'role'      => 'owner',
        'joined_at' => date('Y-m-d-H-i-s')
    ]], JSON_UNESCAPED_UNICODE);

    $_stmtA = $_pdo->prepare("INSERT INTO admin_config (config_id, user_id, api_token, studio_members) VALUES (:cid, :uid, :token, :sm)");
    $_stmtA->execute([
        'cid'   => $_dataTable,
        'uid'   => $_newUserId,
        'token' => $_apiToken,
        'sm'    => $_initMembers
    ]);

    // ---------- 成功返回（顺带签发登录态，注册即登录） ----------
    $_sessionToken = issueSessionToken((int)$_newUserId);
    echo json_encode([
        'code'         => 0,
        'message'      => '注册成功',
        'userId'       => (int)$_newUserId,
        'sessionToken' => $_sessionToken
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    apiError($e);
}