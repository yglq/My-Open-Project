<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();
    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $NewTokey = trim($_POST['NewTokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $NewTokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();
    if (!$_reg) throw new Exception('你没有任何数据库');

    $_data = json_decode($_reg['table_data'], true) ?: [];
    $_updated = false;
    foreach ($_data as &$_item) {
        if (($_item['table'] ?? '') === $ConfigId) {
            if (($_item['role'] ?? null) === 1) throw new Exception('只有创建者可以修改密钥');
            $_item['tokey'] = $NewTokey;
            $_updated = true;
            break;
        }
    }
    if (!$_updated) throw new Exception('数据库未找到或无权限');

    $_newJson = json_encode($_data, JSON_UNESCAPED_UNICODE);
    $_stmt2 = $_pdo->prepare("UPDATE data_tables_registry SET table_data = :td WHERE user_id = :uid");
    $_stmt2->execute(['td' => $_newJson, 'uid' => $UserId]);

    // 同步配置表 Tokey 字段（成员侧 JSON 里的副本不再参与验证）
    $_pdo->prepare("UPDATE admin_config SET tokey = :tk WHERE config_id = :cid")
         ->execute(['tk' => $NewTokey, 'cid' => $ConfigId]);

    echo json_encode(['code' => 0, 'message' => '修改成功'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}