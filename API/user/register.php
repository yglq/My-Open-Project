<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持 POST 请求');

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Nickname = trim($_POST['Nickname'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Nickname === '' || $Password === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Nickname, Password 均为必填');
    }

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['register_switch'] ?? 1) !== 1) throw new Exception('注册功能未开启');

    $_nameMin = intval($_config['name_min_len'] ?? 4);
    $_nameMax = intval($_config['name_max_len'] ?? 20);
    if (mb_strlen($Username) < $_nameMin || mb_strlen($Username) > $_nameMax) {
        throw new Exception("用户名长度需在 {$_nameMin}~{$_nameMax} 之间");
    }

    $_pwdMin = intval($_config['pwd_min_len'] ?? 8);
    $_pwdMax = intval($_config['pwd_max_len'] ?? 30);
    if (strlen($Password) < $_pwdMin || strlen($Password) > $_pwdMax) {
        throw new Exception("密码长度需在 {$_pwdMin}~{$_pwdMax} 之间");
    }

    $_regIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_ipLimit = intval($_config['ip_register_limit'] ?? 10);
    $_stmtIp = $_pdo->prepare("SELECT COUNT(*) FROM `user_data`.`$ConfigId` WHERE reg_ip = :ip");
    $_stmtIp->execute(['ip' => $_regIp]);
    if ($_stmtIp->fetchColumn() >= $_ipLimit) throw new Exception('该 IP 注册次数已达上限');

    $_stmt2 = $_pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'user_data' AND table_name = :t");
    $_stmt2->execute(['t' => $ConfigId]);
    if ($_stmt2->fetchColumn() == 0) {
        createSubUserTable($ConfigId);
    }

    $_stmt3 = $_pdo->prepare("SELECT COUNT(*) FROM `user_data`.`$ConfigId` WHERE username = :uname");
    $_stmt3->execute(['uname' => $Username]);
    if ($_stmt3->fetchColumn() > 0) throw new Exception('账号已存在');

    $avatarFileName = '';
    if (!empty($_FILES['Avatar']) && $_FILES['Avatar']['error'] === UPLOAD_ERR_OK) {
        $_file = $_FILES['Avatar'];
        $_imgInfo = @getimagesize($_file['tmp_name']);
        if ($_imgInfo === false) throw new Exception('头像不是有效的图片');
        if ($_file['size'] > 2 * 1024 * 1024) throw new Exception('头像不能超过 2MB');

        $_avatarDir = __DIR__ . '/../../sub_avatars/' . $ConfigId;
        if (!is_dir($_avatarDir)) mkdir($_avatarDir, 0755, true);

        for ($_i = 0; $_i < 10; $_i++) {
            $avatarFileName = randomString(21) . '.png';
            if (!file_exists($_avatarDir . '/' . $avatarFileName)) break;
        }
        if ($avatarFileName === '') throw new Exception('头像文件名生成失败');

        if (!move_uploaded_file($_file['tmp_name'], $_avatarDir . '/' . $avatarFileName)) {
            throw new Exception('头像上传失败');
        }
    }

    $_giftGold  = intval($_config['register_gold'] ?? 0);
    $_giftExp   = intval($_config['register_exp'] ?? 0);
    $_giftVipMinutes = intval($_config['register_vip_minutes'] ?? 0);
    $_giftVipTime = $_giftVipMinutes > 0 ? date('Y-m-d H:i:s', strtotime("+{$_giftVipMinutes} minutes")) : null;

    $_stmt4 = $_pdo->prepare("INSERT INTO `user_data`.`$ConfigId` (avatar, username, nickname, password, gold, exp, vip_time, created_at, reg_ip) VALUES (:av, :uname, :nick, :pwd, :gold, :exp, :vip, NOW(), :ip)");
    $_stmt4->execute([
        'av'    => $avatarFileName,
        'uname' => $Username,
        'nick'  => $Nickname,
        'pwd' => hashSubPwd($Password, intval($_config['sub_pwd_hash'] ?? 0)),
        'gold'  => $_giftGold,
        'exp'   => $_giftExp,
        'vip'   => $_giftVipTime,
        'ip'    => $_regIp
    ]);

    $_newUserId = $_pdo->lastInsertId();

    // 确保自定义变量列 / JSON 列已存在，否则建表后写默认值会报列不存在
    ensureCustomColumns(
        $ConfigId,
        $_config['custom_vars'] ?? '[]',
        $_config['custom_vars_defaults'] ?? '[]',
        $_config['custom_table_defaults'] ?? '[]'
    );

    // 初始化自定义变量和 JSON 表默认值
    $_varDefaults = $_config['custom_vars_defaults'] ? json_decode($_config['custom_vars_defaults'], true) : [];
    $_tblDefaults = $_config['custom_table_defaults'] ? json_decode($_config['custom_table_defaults'], true) : [];

    $_updateParts = [];
    $_updateParams = [];

    foreach ($_varDefaults as $_vname => $_vval) {
        $_safeVname = str_replace("`", "", $_vname);
        $_updateParts[] = "`$_safeVname` = :v_$_safeVname";
        $_updateParams["v_$_safeVname"] = $_vval;
    }

    foreach ($_tblDefaults as $_tname => $_tcols) {
        $_safeTname = str_replace("`", "", $_tname);
        $_initRow = [];
        foreach ($_tcols as $_colName => $_colDefault) {
            $_initRow[$_colName] = $_colDefault;
        }
        $_updateParts[] = "`$_safeTname` = :t_$_safeTname";
        $_updateParams["t_$_safeTname"] = json_encode($_initRow, JSON_UNESCAPED_UNICODE);
    }

    if (!empty($_updateParts)) {
        $_updateParams['newid'] = $_newUserId;
        $_sqlInit = "UPDATE `user_data`.`$ConfigId` SET " . implode(', ', $_updateParts) . " WHERE id = :newid";
        $_stmtInit = $_pdo->prepare($_sqlInit);
        $_stmtInit->execute($_updateParams);
    }

    echo json_encode([
        'code'     => 0,
        'message'  => '注册成功',
        'user_id'  => (int)$_newUserId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}