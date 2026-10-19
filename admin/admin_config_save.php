<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    $_fields = [
        'login_switch','register_switch','change_name_switch','change_nick_switch',
        'change_avatar_switch','api_score_switch','name_min_len','name_max_len',
        'pwd_min_len','pwd_max_len','nick_min_len','nick_max_len','ip_register_limit',
        'register_gold','register_exp','register_vip_minutes','sign_gold','sign_exp',
        'cardkey_length','cardkey_type','cardkey_api_switch','cardkey_generate_switch',
        'notice_switch','notice_api_switch','stats_switch',
        'server_status','api_token_switch','api_token','sub_pwd_hash',
        'custom_vars','custom_tables','custom_vars_defaults','custom_table_defaults'
    ];

    $_sets = []; $_params = [];
    foreach ($_fields as $_f) {
        if (isset($_POST[$_f]) && $_POST[$_f] !== '') {
            $_sets[] = "`$_f` = :$_f";
            $_params[$_f] = $_POST[$_f];
        }
    }

    if (empty($_sets)) throw new Exception('没有要更新的字段');

    $_params['cid'] = $ConfigId;
    $_sql = "UPDATE admin_config SET " . implode(', ', $_sets) . " WHERE config_id = :cid";
    $_stmt2 = $_pdo->prepare($_sql);
    $_stmt2->execute($_params);

    // 自定义变量/JSON 表现在存侧表（{ConfigId}_vars / {ConfigId}_json），
    // 不再 ALTER 子用户主表加列；这里只确保侧表存在，默认值在读取时按配置生效
    $_subTableExists = $_pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='user_data' AND table_name='$ConfigId'"
    )->fetchColumn() > 0;
    if ($_subTableExists) {
        ensureVarStore($ConfigId);
        ensureJsonStore($ConfigId);
    }

    echo json_encode(['code' => 0, 'message' => '保存成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}