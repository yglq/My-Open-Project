<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();
    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');

    if ($UserId <= 0 || $ConfigId === '') throw new Exception('参数错误');

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();
    if (!$_reg) throw new Exception('你没有任何数据库');

    $_data = json_decode($_reg['table_data'] ?? '[]', true) ?: [];

    // 检查数据库数量
    if (count($_data) <= 1) throw new Exception('这是你唯一的数据库，无法删除');

    $_removeIndex = -1;
    $_removeItem = null;
    foreach ($_data as $_i => $_item) {
        if (($_item['table'] ?? '') === $ConfigId) {
            $_removeIndex = $_i;
            $_removeItem = $_item;
            break;
        }
    }
    if ($_removeIndex < 0) throw new Exception('数据库未找到');

    // 从数组中移除
    array_splice($_data, $_removeIndex, 1);
    $_newJson = json_encode($_data, JSON_UNESCAPED_UNICODE);
    $_stmt2 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :td WHERE user_id = :uid");
    $_stmt2->execute(['td' => $_newJson, 'uid' => $UserId]);

    // 如果是成员身份退出，减少原创建者的 member_count
    $_role = $_removeItem['role'] ?? null;
    if ($_role === 1) {
        $_ownerId = $_removeItem['owner_id'] ?? null;
        if ($_ownerId) {
            $_stmt3 = $_pdo->prepare("UPDATE data_tables_registry SET member_count = GREATEST(member_count - 1, 0) WHERE user_id = :oid");
            $_stmt3->execute(['oid' => intval($_ownerId)]);
        }
    }

    // 从 admin_config.studio_members 中移除自己
    $_stmtM = $_pdo->prepare("SELECT studio_members FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmtM->execute(['cid' => $ConfigId]);
    $_mJson = $_stmtM->fetchColumn();
    if ($_mJson) {
        $_mData = json_decode($_mJson, true) ?: [];
        $_mData = array_values(array_filter($_mData, function($m) use ($UserId) {
            return ($m['user_id'] ?? 0) != $UserId;
        }));
        $_stmtU = $_pdo->prepare("UPDATE admin_config SET studio_members = :sm WHERE config_id = :cid");
        $_stmtU->execute(['sm' => json_encode($_mData, JSON_UNESCAPED_UNICODE), 'cid' => $ConfigId]);
    }

    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}