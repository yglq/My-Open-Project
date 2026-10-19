<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    $UserId    = intval($_POST['UserId'] ?? 0);
    $DbName    = trim($_POST['DbName'] ?? '');
    $NewTokey  = trim($_POST['Tokey'] ?? ''); // 允许为空，则自动生成

    if ($UserId <= 0 || $DbName === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 生成唯一表名
    $_newTable = '';
    for ($_i = 0; $_i < 20; $_i++) {
        $_tmp = randomString(21);
        $_stmt = $_pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'user_data' AND table_name = :t");
        $_stmt->execute(['t' => $_tmp]);
        if ($_stmt->fetchColumn() == 0) { $_newTable = $_tmp; break; }
    }
    if (!$_newTable) throw new Exception('表名生成失败');

    // 如果没传 tokey，自动生成
    if ($NewTokey === '') $NewTokey = randomString(21);

    // 在 user_data 库建表
    createSubUserTable($_newTable);

    // 更新当前用户的 table_data
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();
    $_data = $_reg ? (json_decode($_reg['table_data'], true) ?: []) : [];

    $_data[] = [
        'table' => $_newTable,
        'name'  => $DbName,
        'tokey' => $NewTokey
    ];

    $_newJson = json_encode($_data, JSON_UNESCAPED_UNICODE);
    if ($_reg) {
        $_stmt2 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :td WHERE user_id = :uid");
        $_stmt2->execute(['td' => $_newJson, 'uid' => $UserId]);
    } else {
        $_stmt2 = $_pdo->prepare("INSERT INTO data_tables_registry (user_id, table_data) VALUES (:uid, :td)");
        $_stmt2->execute(['uid' => $UserId, 'td' => $_newJson]);
    }
// 创建对应的 admin_config 记录
$_stmt3 = $_pdo->prepare("INSERT INTO admin_config (config_id, user_id, api_token) VALUES (:cid, :uid, :token)");
$_stmt3->execute([
    'cid'   => $_newTable,
    'uid'   => $UserId,
    'token' => randomString(21)
]);
// 初始化 studio_members
$_stmtM = $_pdo->prepare("UPDATE admin_config SET studio_members = :sm WHERE config_id = :cid");
$_stmtM->execute([
    'sm' => json_encode([[
        'user_id'   => $UserId,
        'name'      => $DbName,
        'avatar'    => 'icon.png',
        'role'      => 'owner',
        'joined_at' => date('Y-m-d-H-i-s')
    ]], JSON_UNESCAPED_UNICODE),
    'cid' => $_newTable
]);
    echo json_encode(['code' => 0, 'message' => '创建成功', 'table' => $_newTable], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}