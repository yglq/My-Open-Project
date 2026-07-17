<?php
/**
 * 子用户列表（分页）
 * POST: UserId, ConfigId, Tokey, Page, PageSize
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Page     = intval($_POST['Page'] ?? 1);
    $PageSize = intval($_POST['PageSize'] ?? 20);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 验证 tokey
    validateTokey($UserId, $ConfigId, $Tokey);

    // 检查表是否存在
    $_stmt2 = $_pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'user_data' AND table_name = :t");
    $_stmt2->execute(['t' => $ConfigId]);
    if ($_stmt2->fetchColumn() == 0) {
        echo json_encode(['code' => 0, 'data' => [], 'total' => 0, 'totalPages' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 查总数
    $_stmt3 = $_pdo->query("SELECT COUNT(*) FROM `user_data`.`$ConfigId`");
    $_total = intval($_stmt3->fetchColumn());

    $_totalPages = ceil($_total / $PageSize);
    $_offset = ($Page - 1) * $PageSize;

    // 分页查询
    $_stmt4 = $_pdo->prepare("SELECT id, avatar, username, nickname, gender, ban_time, vip_time, sign_date, created_at, login_at FROM `user_data`.`$ConfigId` ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $_stmt4->bindValue('limit', $PageSize, PDO::PARAM_INT);
    $_stmt4->bindValue('offset', $_offset, PDO::PARAM_INT);
    $_stmt4->execute();
    $_users = $_stmt4->fetchAll();

    echo json_encode([
        'code'       => 0,
        'data'       => $_users,
        'total'      => $_total,
        'totalPages' => $_totalPages
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}