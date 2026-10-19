<?php
/**
 * 卡密列表（分页，API 版）
 * 仅支持 POST
 * 参数: ConfigId, Tokey, Page(默认1), PageSize(默认20), Type(可选筛选: gold/vip/var/json/custom)
 */
require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Page     = max(intval($_POST['Page'] ?? 1), 1);
    $PageSize = min(intval($_POST['PageSize'] ?? 20), 50);
    $Type     = trim($_POST['Type'] ?? '');

    if ($ConfigId === '' || $Tokey === '') throw new Exception('参数错误：ConfigId, Tokey 均为必填');

    $_pdo = GetPDO();
    validateTokeyGlobal($ConfigId, $Tokey);

    // 确保表存在
    createCardKeyTable($ConfigId);

    $_tableName = '`card_data`.`' . $ConfigId . '_cardkey`';
    $_offset = ($Page - 1) * $PageSize;

    $_where = '';
    $_whereParams = [];
    if ($Type !== '') {
        $_typeMap = ['gold' => 1, 'vip' => 2, 'var' => 3, 'json' => 4];
        if ($Type === 'custom') {
            $_where = " WHERE type IN (3, 4)";
        } else {
            $_typeNum = $_typeMap[$Type] ?? 0;
            if ($_typeNum > 0) {
                $_where = " WHERE type = :ftype";
                $_whereParams['ftype'] = $_typeNum;
            }
        }
    }

    // 总数
    $_countStmt = $_pdo->prepare("SELECT COUNT(*) FROM $_tableName" . $_where);
    $_countStmt->execute($_whereParams);
    $_total = intval($_countStmt->fetchColumn());

    // 列表
    $_listStmt = $_pdo->prepare("SELECT * FROM $_tableName" . $_where . " ORDER BY id DESC LIMIT $_offset, $PageSize");
    $_listStmt->execute($_whereParams);
    $_rows = $_listStmt->fetchAll();

    echo json_encode([
        'code'     => 0,
        'total'    => $_total,
        'page'     => $Page,
        'pageSize' => $PageSize,
        'list'     => $_rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
