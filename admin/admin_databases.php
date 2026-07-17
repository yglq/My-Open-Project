<?php
/**
 * 获取用户的数据库列表
 * POST: UserId
 * 返回: {code:0, list:[{table, name, role, display, owner_id}, ...]}
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方式错误');
    }

    $UserId = intval($_POST['UserId'] ?? 0);
    if ($UserId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();
    $_stmt = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid LIMIT 1");
    $_stmt->execute(['uid' => $UserId]);
    $_row = $_stmt->fetch();

    if (!$_row) {
        echo json_encode(['code' => 0, 'list' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_list = json_decode($_row['table_data'] ?? '[]', true);
    if (!is_array($_list)) {
        $_list = [];
    }

    echo json_encode(['code' => 0, 'list' => $_list], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    apiError($e);
}