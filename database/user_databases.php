<?php
/**
 * 获取用户的工作室列表（用于邀请）
 * POST: UserId
 * 返回：该用户 table_data 中所有数据库
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) throw new Exception('参数错误');

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_reg = $_stmt->fetch();

    $_list = [];
    if ($_reg) {
        $_data = json_decode($_reg['table_data'], true) ?: [];
        foreach ($_data as $_item) {
            $_list[] = [
                'table' => $_item['table'] ?? '',
                'name'  => $_item['name'] ?? '未命名',
                'role'  => $_item['role'] ?? null
            ];
        }
    }

    echo json_encode([
        'code' => 0,
        'data' => $_list
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}