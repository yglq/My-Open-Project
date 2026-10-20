<?php
/**
 * Tokey 迁移脚本（幂等）
 * 把 data_tables_registry.table_data JSON 里的每个 {table,tokey} 搬到 db_credentials 表
 * POST: Token (deploy.token / LQ_DEPLOY_TOKEN)
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();

    $_token = trim($_POST['Token'] ?? '');
    $_valid = getenv('LQ_DEPLOY_TOKEN') ?: (is_file(__DIR__ . '/deploy.token') ? trim(file_get_contents(__DIR__ . '/deploy.token')) : '');
    if ($_valid === '' || !hash_equals($_valid, $_token)) throw new Exception('令牌无效');

    $_pdo = GetPDO();
    $_pdo->exec("CREATE TABLE IF NOT EXISTS `db_credentials` (
        `config_id` varchar(32) NOT NULL PRIMARY KEY,
        `tokey` varchar(64) NOT NULL,
        `owner_id` int(11) NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL,
        UNIQUE KEY `uk_tokey` (`tokey`),
        KEY `idx_owner` (`owner_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $_all = $_pdo->query("SELECT user_id, table_data FROM data_tables_registry")->fetchAll();
    $_ins = $_pdo->prepare("INSERT IGNORE INTO db_credentials (config_id,tokey,owner_id,created_at) VALUES (?,?,?,NOW())");

    $_migrated = 0;
    $_skipped  = 0;
    $_errors   = [];

    foreach ($_all as $_row) {
        $_data = json_decode($_row['table_data'] ?? '[]', true) ?: [];
        $_ownerId = intval($_row['user_id']);
        foreach ($_data as $_item) {
            $_cid = trim($_item['table'] ?? '');
            $_tk  = trim($_item['tokey'] ?? '');
            if ($_cid === '' || $_tk === '') { $_skipped++; continue; }
            $_ins->execute([$_cid, $_tk, $_ownerId]);
            if ($_ins->rowCount() > 0) $_migrated++; else $_skipped++;
        }
    }

    echo json_encode([
        'code'     => 0,
        'migrated' => $_migrated,
        'skipped'  => $_skipped,
        'errors'   => $_errors
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
