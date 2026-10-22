<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();
    authUser();
    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();
   $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
     $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();
    $_list = [];

    if ($_reg) {
        $_data = json_decode($_reg['table_data'] ?? '[]', true) ?: [];
      
        // 获取自己的 VIP 状态
        $_stmtV = $_pdo->prepare("SELECT vip_time, svip_time FROM users WHERE user_id = :uid");
        $_stmtV->execute(['uid' => $UserId]);
        $_meVip = $_stmtV->fetch();
        $_now = date('Y-m-d H:i:s');
        $_myVipStatus = 'none';
        if ($_meVip && $_meVip['svip_time'] > $_now) $_myVipStatus = 'svip';
        elseif ($_meVip && $_meVip['vip_time'] > $_now) $_myVipStatus = 'vip';

        foreach ($_data as $_item) {
            $_tableName = $_item['table'] ?? '';
            $_dbName    = $_item['name'] ?? '未命名';
            $_role      = $_item['role'] ?? null;
            $_ownerId   = $_item['owner_id'] ?? null;

         $_tokey = $_item['tokey'] ?? '';
            $_isOwner = ($_role === 0 || $_role === null); // null 视为私有
            $_isMember = ($_role === 1);

            $_ownerName = '';
            $_ownerVipStatus = 'none';
            if ($_ownerId && $_isMember) {
                $_stmtO = $_pdo->prepare("SELECT nickname, username, vip_time, svip_time FROM users WHERE user_id = :oid");
                $_stmtO->execute(['oid' => intval($_ownerId)]);
                $_owner = $_stmtO->fetch();
                if ($_owner) {
                    $_ownerName = $_owner['nickname'] ?: $_owner['username'];
                    if ($_owner['svip_time'] > $_now) $_ownerVipStatus = 'svip';
                    elseif ($_owner['vip_time'] > $_now) $_ownerVipStatus = 'vip';
                }
            }
// 获取该数据库的成员列表（从 admin_config.studio_members）
$_members = [];
$_stmtM = $_pdo->prepare("SELECT studio_members FROM admin_config WHERE config_id = :cid LIMIT 1");
$_stmtM->execute(['cid' => $_tableName]);
$_mData = $_stmtM->fetchColumn();
if ($_mData) {
    $_members = json_decode($_mData, true) ?: [];
}
            $_list[] = [
                'table'      => $_tableName,
                'name'       => $_dbName,
                'role'       => $_isOwner ? 'owner' : 'member',
                'is_owner'   => $_isOwner,
                'is_member'  => $_isMember,
                'tokey'      => $_tokey,
                'owner_name' => $_ownerName,
                'owner_vip'  => $_ownerVipStatus,
                'my_vip'     => $_myVipStatus,
                'members' => $_members
            ];
        }
    }

    echo json_encode(['code' => 0, 'data' => $_list], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}