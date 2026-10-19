<?php
/**
 * 批量生成卡密（API 版）
 * 仅支持 POST
 * 参数: ConfigId, Tokey, Count, Type(1金币/2会员/3变量/4JSON), 以及类型相关参数
 *   - Type=1: Gold        (金币数量)
 *   - Type=2: VipDays    (会员天数)
 *   - Type=3: ValueList  (JSON 字符串，如 [{"name":"score","value":5}])
 *   - Type=4: JsonList   (JSON 字符串，如 [{"table":"bag","col":"count","value":5}])
 */
require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');
    $Count    = min(intval($_POST['Count'] ?? 10), 100);
    $Type     = intval($_POST['Type'] ?? 1);

    if ($ConfigId === '' || $Tokey === '') throw new Exception('参数错误：ConfigId, Tokey 均为必填');
    if ($Count < 1) throw new Exception('数量至少为 1');
    if (!in_array($Type, [1, 2, 3, 4], true)) throw new Exception('不支持的卡密类型：' . $Type);

    $_pdo = GetPDO();
    validateTokeyGlobal($ConfigId, $Tokey);

    // 生成总开关
    $_genStmt = $_pdo->prepare("SELECT cardkey_generate_switch FROM admin_config WHERE config_id = :cid");
    $_genStmt->execute(['cid' => $ConfigId]);
    $_genRow = $_genStmt->fetch();
    if (!$_genRow || intval($_genRow['cardkey_generate_switch'] ?? 0) !== 1) {
        throw new Exception('卡密生成功能未开启');
    }

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
            if (json_decode($_valueList) === null && trim($_valueList) !== '[]') {
                throw new Exception('ValueList 不是合法的 JSON');
            }
            $_sql .= ", value_list";
            $_params['value_list'] = $_valueList;
        } elseif ($Type == 4) {
            $_jsonList = $_POST['JsonList'] ?? '[]';
            if (json_decode($_jsonList) === null && trim($_jsonList) !== '[]') {
                throw new Exception('JsonList 不是合法的 JSON');
            }
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
        'code'    => 0,
        'message' => "已生成 {$Count} 张卡密",
        'count'   => $Count,
        'cards'   => $_cards
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}
