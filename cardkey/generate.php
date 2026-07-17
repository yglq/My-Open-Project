<?php
/**
 * 批量生成卡密
 * POST: UserId, ConfigId, Tokey, count, type(1金币/2会员/3变量/4JSON), typeParams...
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Count    = min(intval($_POST['Count'] ?? 10), 100);
    $Type     = intval($_POST['Type'] ?? 1);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');
    if ($Count < 1) throw new Exception('数量至少为1');

    $_pdo = GetPDO();
    validateTokey($UserId, $ConfigId, $Tokey);

    // 确保卡密表存在
    createCardKeyTable($ConfigId);

    $_tableName = '`card_data`.`' . $ConfigId . '_cardkey`';
    $_cards = [];

    for ($_i = 0; $_i < $Count; $_i++) {
        $_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));

        $_params = ['code' => $_code, 'type' => $Type];
        $_sql = "INSERT INTO $_tableName (code, type";

        if ($Type == 1) {
            $_coinNum = intval($_POST['Gold'] ?? 0);
            $_sql .= ", coin_num";
            $_params['coin_num'] = $_coinNum;
        } elseif ($Type == 2) {
            $_vipDays = intval($_POST['VipDays'] ?? 0);
            $_sql .= ", vip_days";
            $_params['vip_days'] = $_vipDays;
        } elseif ($Type == 3) {
            $_valueList = $_POST['ValueList'] ?? '[]';
            $_sql .= ", value_list";
            $_params['value_list'] = $_valueList;
        } elseif ($Type == 4) {
            $_jsonList = $_POST['JsonList'] ?? '[]';
            $_sql .= ", json_list";
            $_params['json_list'] = $_jsonList;
        }

        $_sql .= ") VALUES (:code, :type";
        if ($Type == 1) $_sql .= ", :coin_num";
        elseif ($Type == 2) $_sql .= ", :vip_days";
        elseif ($Type == 3) $_sql .= ", :value_list";
        elseif ($Type == 4) $_sql .= ", :json_list";
        $_sql .= ")";

        $_stmt = $_pdo->prepare($_sql);
        $_stmt->execute($_params);
        $_cards[] = $_code;
    }

    echo json_encode([
        'code' => 0,
        'message' => "已生成{$Count}张卡密",
        'cards' => $_cards
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}