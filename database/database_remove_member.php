<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    $UserId       = intval($_POST['UserId'] ?? 0);
    $ConfigId     = trim($_POST['ConfigId'] ?? '');
    $TargetUserId = intval($_POST['TargetUserId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $TargetUserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 验证操作者是创始人（从 table_data 中查看 role）
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();
    if (!$_reg) throw new Exception('你没有任何数据库');
    $_myData = json_decode($_reg['table_data'], true) ?: [];
    $_isOwner = false;
    foreach ($_myData as $_item) {
        if (($_item['table'] ?? '') === $ConfigId && ($_item['role'] ?? null) !== 1) {
            $_isOwner = true; break;
        }
    }
    if (!$_isOwner) throw new Exception('只有创始者可以移除成员');

    // 从被移除者的 table_data 中删除该项
    $_stmt2 = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt2->execute(['uid' => $TargetUserId]);
    $_targetReg = $_stmt2->fetch();
    if ($_targetReg) {
        $_targetData = json_decode($_targetReg['table_data'], true) ?: [];
        $_targetData = array_values(array_filter($_targetData, function($item) use ($ConfigId) {
            return ($item['table'] ?? '') !== $ConfigId;
        }));
        $_newJson = json_encode($_targetData, JSON_UNESCAPED_UNICODE);
        $_stmt3 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :td WHERE user_id = :uid");
        $_stmt3->execute(['td' => $_newJson, 'uid' => $TargetUserId]);
    }

    // 从 admin_config.studio_members 中移除
    $_stmtM = $_pdo->prepare("SELECT studio_members FROM admin_config WHERE config_id = :cid");
    $_stmtM->execute(['cid' => $ConfigId]);
    $_mJson = $_stmtM->fetchColumn();
    if ($_mJson) {
        $_mData = json_decode($_mJson, true) ?: [];
        $_mData = array_values(array_filter($_mData, function($m) use ($TargetUserId) {
            return ($m['user_id'] ?? 0) != $TargetUserId;
        }));
        $_stmtU = $_pdo->prepare("UPDATE admin_config SET studio_members = :sm WHERE config_id = :cid");
        $_stmtU->execute(['sm' => json_encode($_mData, JSON_UNESCAPED_UNICODE), 'cid' => $ConfigId]);
    }

    echo json_encode(['code' => 0, 'message' => '已移除'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}