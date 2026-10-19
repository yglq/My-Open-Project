<?php
/**
 * 卡密列表（分页）
 * POST: UserId, ConfigId, Tokey, Page(默认1), PageSize(默认20), Type(可选筛选)
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Page     = max(intval($_POST['Page'] ?? 1), 1);
    $PageSize = min(intval($_POST['PageSize'] ?? 20), 50);
    $Type     = trim($_POST['Type'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();
    validateTokey($UserId, $ConfigId, $Tokey);

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
    $_countSql = "SELECT COUNT(*) FROM $_tableName" . $_where;
    $_countStmt = $_pdo->prepare($_countSql);
    $_countStmt->execute($_whereParams);
    $_total = intval($_countStmt->fetchColumn());

    // 列表
    $_listSql = "SELECT * FROM $_tableName" . $_where . " ORDER BY id DESC LIMIT $_offset, $PageSize";
    $_listStmt = $_pdo->prepare($_listSql);
    $_listStmt->execute($_whereParams);
    $_rows = $_listStmt->fetchAll();

    echo json_encode([
        'code' => 0,
        'total' => $_total,
        'page' => $Page,
        'pageSize' => $PageSize,
        'list' => $_rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}