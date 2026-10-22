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
    // 播种自定义变量/JSON 表默认值到侧表（新架构，不再 ALTER 主表加列）
    seedCustomDefaults(
        $ConfigId,
        $_newUserId,
        $_cfg['custom_vars_defaults'] ?? '{}',
        $_cfg['custom_table_defaults'] ?? '{}'
    );
}


    echo json_encode(['code' => 0, 'message' => '创建成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}