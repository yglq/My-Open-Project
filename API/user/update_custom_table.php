<?php
/**
 * 修改指定自定义JSON表数据
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, TableName
 *       Action=replace（默认，Data=JSON数组整表替换）| append（Row=JSON对象追加一行）
 * 存储：user_data.{ConfigId}_json 侧表，一行一条记录
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId  = trim($_POST['ConfigId'] ?? '');
    $ApiToken  = trim($_POST['ApiToken'] ?? '');
    $Username  = trim($_POST['Username'] ?? '');
    $Password  = $_POST['Password'] ?? '';
    $TableName = trim($_POST['TableName'] ?? '');
    $Action    = strtolower(trim($_POST['Action'] ?? 'replace'));
    $Data      = trim($_POST['Data'] ?? '');
    $Row       = trim($_POST['Row'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $TableName === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password, TableName 均为必填');
    }
    if (!in_array($Action, ['replace', 'append'], true)) throw new Exception('Action 只能是 replace 或 append');

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证表名是否在 custom_tables 中
    $_customTables = parseCustomTableDefs($_config['custom_tables'] ?? '{}');
    if (!array_key_exists($TableName, $_customTables)) throw new Exception('JSON表不存在：' . $TableName);
    $_safeTable = _safeIdent($TableName);

    // 校验行/数据的列名是否都在表定义中
    $_allowedCols = $_customTables[$TableName] ?? [];
    $_checkRow = function ($_row) use ($_allowedCols) {
        if (!is_array($_row)) throw new Exception('每行必须是 JSON 对象');
        foreach ($_row as $_col => $_val) {
            if (!in_array($_col, $_allowedCols)) throw new Exception('列名不存在：' . $_col);
        }
    };

    if ($Action === 'replace') {
        if ($Data === '') throw new Exception('缺少 Data（JSON 数组）');
        $_dataDecoded = json_decode($Data, true);
        if (!is_array($_dataDecoded)) throw new Exception('Data 必须是合法的 JSON 数组');
        foreach ($_dataDecoded as $_row) $_checkRow($_row);
    } else {
        if ($Row === '') throw new Exception('缺少 Row（JSON 对象）');
        $_rowDecoded = json_decode($Row, true);
        if (!is_array($_rowDecoded) || array_keys($_rowDecoded) === range(0, count($_rowDecoded) - 1)) {
            throw new Exception('Row 必须是 JSON 对象');
        }
        $_checkRow($_rowDecoded);
    }

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 写侧表
    if ($Action === 'replace') {
        customTableReplace($ConfigId, $_user['id'], $TableName, $_dataDecoded);
    } else {
        customTableAppend($ConfigId, $_user['id'], $TableName, $_rowDecoded);
    }

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}