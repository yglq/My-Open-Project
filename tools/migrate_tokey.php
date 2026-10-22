<?php
/**
 * Tokey 迁移脚本（幂等）
 * admin_config 加 tokey 列；把 db_credentials / registry JSON 里的密钥回填到 admin_config.tokey
 * POST: Token (deploy.token / LQ_DEPLOY_TOKEN)
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requirePost();

    $_token = trim($_POST['Token'] ?? '');
    $__sec = is_file(__DIR__ . '/secrets.php') ? (include __DIR__ . '/secrets.php') : [];
    $_valid = getenv('LQ_DEPLOY_TOKEN') ?: (is_array($__sec) ? ($__sec['deploy_token'] ?? '') : '')
        ?: (is_file(__DIR__ . '/deploy.token') ? trim(file_get_contents(__DIR__ . '/deploy.token')) : '');
    if ($_valid === '' || !hash_equals($_valid, $_token)) throw new Exception('令牌无效');

    $_pdo = GetPDO();

    // 幂等加列
    $_hasCol = $_pdo->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema='game_data' AND table_name='admin_config' AND column_name='tokey'")->fetchColumn();
    if (!$_hasCol) {
        $_pdo->exec("ALTER TABLE admin_config ADD COLUMN tokey VARCHAR(64) NOT NULL DEFAULT ''");
    }

    // 密钥来源一：db_credentials 表（若存在）
    $_src = [];
    $_hasCred = $_pdo->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema='game_data' AND table_name='db_credentials'")->fetchColumn();
    if ($_hasCred) {
        foreach ($_pdo->query("SELECT config_id, tokey FROM db_credentials")->fetchAll() as $_r) {
            if ($_r['tokey'] !== '') $_src[$_r['config_id']] = $_r['tokey'];
        }
    }

    // 密钥来源二：registry JSON（db_credentials 没有的补）
    foreach ($_pdo->query("SELECT table_data FROM data_tables_registry")->fetchAll() as $_row) {
        foreach ((json_decode($_row['table_data'] ?? '[]', true) ?: []) as $_item) {
            $_cid = trim($_item['table'] ?? '');
            $_tk  = trim($_item['tokey'] ?? '');
            if ($_cid !== '' && $_tk !== '' && !isset($_src[$_cid])) $_src[$_cid] = $_tk;
        }
    }

    $_upd = $_pdo->prepare("UPDATE admin_config SET tokey = :tk WHERE config_id = :cid AND tokey = ''");
    $_migrated = 0;
    foreach ($_src as $_cid => $_tk) {
        $_upd->execute(['tk' => $_tk, 'cid' => $_cid]);
        if ($_upd->rowCount() > 0) $_migrated++;
    }

    echo json_encode([
        'code'     => 0,
        'migrated' => $_migrated,
        'known'    => count($_src)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
