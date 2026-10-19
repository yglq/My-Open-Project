<?php
/**
 * 创建子用户
 * POST: UserId, ConfigId, Tokey, Username, Nickname, Password
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Nickname = trim($_POST['Nickname'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');
    if ($Username === '' || $Nickname === '' || $Password === '') throw new Exception('请填写账号、昵称、密码');

    $_pdo = GetPDO();

    // 验证 tokey
    validateTokey($UserId, $ConfigId, $Tokey);

    // 检查表是否存在，不存在则创建
    $_stmt2 = $_pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'user_data' AND table_name = :t");
    $_stmt2->execute(['t' => $ConfigId]);
    if ($_stmt2->fetchColumn() == 0) {
    createSubUserTable($ConfigId);
    }

    // 检查用户名是否已存在
    $_stmt3 = $_pdo->prepare("SELECT COUNT(*) FROM `user_data`.`$ConfigId` WHERE username = :uname");
    $_stmt3->execute(['uname' => $Username]);
    if ($_stmt3->fetchColumn() > 0) throw new Exception('账号已存在');

    // 插入
    $_stmt4 = $_pdo->prepare("INSERT INTO `user_data`.`$ConfigId` (username, nickname, password, created_at) VALUES (:uname, :nick, :pwd, NOW())");
    $HashPwd = intval($_POST['HashPwd'] ?? 0);
    $_stmt4->execute(['uname' => $Username, 'nick' => $Nickname, 'pwd' => hashSubPwd($Password, $HashPwd)]);
$_newUserId = $_pdo->lastInsertId();
// 初始化自定义变量和 JSON 表默认值
$_stmtCfg = $_pdo->prepare("SELECT custom_vars, custom_vars_defaults, custom_table_defaults FROM admin_config WHERE config_id = :cid LIMIT 1");
$_stmtCfg->execute(['cid' => $ConfigId]);
$_cfg = $_stmtCfg->fetch();

if ($_cfg) {
    // 确保自定义变量列 / JSON 列已存在，否则写默认值会报列不存在
    ensureCustomColumns(
        $ConfigId,
        $_cfg['custom_vars'] ?? '[]',
        $_cfg['custom_vars_defaults'] ?? '[]',
        $_cfg['custom_table_defaults'] ?? '[]'
    );

    $_varDefaults = $_cfg['custom_vars_defaults'] ? json_decode($_cfg['custom_vars_defaults'], true) : [];
    $_tblDefaults = $_cfg['custom_table_defaults'] ? json_decode($_cfg['custom_table_defaults'], true) : [];

    $_updateParts = [];
    $_updateParams = [];

    // 自定义变量默认值
    foreach ($_varDefaults as $_vname => $_vval) {
        $_safeVname = _safeIdent($_vname);
        $_updateParts[] = "`$_safeVname` = :v_$_safeVname";
        $_updateParams["v_$_safeVname"] = $_vval;
    }

    // 自定义 JSON 表默认值：直接构建一行数据
    foreach ($_tblDefaults as $_tname => $_tcols) {
        $_safeTname = _safeIdent($_tname);
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
}


    echo json_encode(['code' => 0, 'message' => '创建成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}