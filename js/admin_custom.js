// ========== Custom Vars & Tables Management ==========
// 新数据结构:
// CustomVarsData: [{"name":"score","type":"value"}, ...]
// CustomTablesData: {"backpack":[{"name":"item_id","type":"value"}, ...], ...}
let CustomVarsData = [];
let CustomTablesData = {};
let CustomVarsDefaults = {};
let CustomTableDefaults = {};

async function loadCustomConfig() {
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    if (!ConfigId) return;

    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    const _r = await apiFetch('/admin/admin_config_get.php', { method: 'POST', body: _f });
    const _d = await _r.json();
    if (_d.code === 0) {
        // 兼容旧格式（纯字符串数组）
        var rawVars = _d.data.custom_vars;
        if (typeof rawVars === 'string') rawVars = JSON.parse(rawVars);
        if (Array.isArray(rawVars)) {
            CustomVarsData = rawVars.map(function(v) {
                return typeof v === 'string' ? {name:v, type:'value'} : v;
            });
        } else {
            CustomVarsData = [];
        }

        var rawTables = _d.data.custom_tables;
        if (typeof rawTables === 'string') rawTables = JSON.parse(rawTables);
        if (rawTables && typeof rawTables === 'object' && !Array.isArray(rawTables)) {
            CustomTablesData = {};
            Object.keys(rawTables).forEach(function(tbl) {
                var cols = rawTables[tbl];
                CustomTablesData[tbl] = (Array.isArray(cols) ? cols : []).map(function(c) {
                    return typeof c === 'string' ? {name:c, type:'value'} : c;
                });
            });
        } else {
            CustomTablesData = {};
        }

        CustomVarsDefaults = (typeof _d.data.custom_vars_defaults === 'string' ? JSON.parse(_d.data.custom_vars_defaults) : (_d.data.custom_vars_defaults || {}));
        CustomTableDefaults = (typeof _d.data.custom_table_defaults === 'string' ? JSON.parse(_d.data.custom_table_defaults) : (_d.data.custom_table_defaults || {}));
        renderCustomVars();
        renderCustomTables();
    }
}

function renderCustomVars() {
    var el = document.getElementById('VarList');
    if (!el) return;
    if (!Array.isArray(CustomVarsData)) CustomVarsData = [];
    el.innerHTML = CustomVarsData.map(function(v, i) {
        var vname = v.name || v;
        var vtype = v.type || 'value';
        return '<div class="var-item">'
            + '<span class="var-index">' + (i + 1) + '</span>'
            + '<span class="var-name">' + vname + '</span>'
            + '<span class="var-value" style="font-size:10px;background:#f0f4ff;color:#4f6ef7;padding:1px 6px;border-radius:4px;">' + vtype + '</span>'
            + '<input id="VarDefault_' + i + '" value="' + (CustomVarsDefaults[vname] || (vtype === 'value' ? '0' : '')) + '" '
            + 'style="width:60px;padding:4px 6px;border:1px solid #e0d5c1;border-radius:6px;font-size:11px;outline:none;" '
            + 'onchange="parent.updateVarDefault(\'' + vname + '\', this.value)">'
            + '<div class="var-actions" style="margin-left:0;">'
            + '<button class="btn-sm" onclick="parent.moveVar(' + i + ', -1)" ' + (i === 0 ? 'disabled' : '') + '>↑</button>'
            + '<button class="btn-sm" onclick="parent.moveVar(' + i + ', 1)" ' + (i === CustomVarsData.length - 1 ? 'disabled' : '') + '>↓</button>'
            + '<button class="btn-sm" onclick="parent.renameVar(' + i + ')">改名</button>'
            + '<button class="btn-sm red" onclick="parent.deleteVar(' + i + ')">删除</button>'
            + '</div></div>';
    }).join('');
}

function renderCustomTables() {
    var el = document.getElementById('TableList');
    if (!el) return;
    var entries = Object.entries(CustomTablesData);
    el.innerHTML = entries.map(function(entry, i) {
        var tblName = entry[0];
        var cols = entry[1] || [];
        var colsHtml = cols.map(function(c, ci) {
            var cname = c.name || c;
            var ctype = c.type || 'value';
            return '<div class="var-item" style="margin-left:32px;">'
                + '<span class="var-index">' + (ci + 1) + '</span>'
                + '<span class="var-name">' + cname + '</span>'
                + '<span class="var-value" style="font-size:10px;background:#f0f4ff;color:#4f6ef7;padding:1px 6px;border-radius:4px;">' + ctype + '</span>'
                + '<input id="ColDefault_' + tblName.replace(/[^a-zA-Z0-9]/g, '_') + '_' + cname.replace(/[^a-zA-Z0-9]/g, '_') + '" '
                + 'value="' + ((CustomTableDefaults[tblName] && CustomTableDefaults[tblName][cname]) || '') + '" '
                + 'style="width:60px;padding:4px 6px;border:1px solid #e0d5c1;border-radius:6px;font-size:11px;outline:none;" '
                + 'onchange="parent.updateTableColDefault(\'' + tblName + '\', \'' + cname + '\', this.value)">'
                + '<div class="var-actions" style="margin-left:0;">'
                + '<button class="btn-sm" onclick="parent.moveTableCol(\'' + tblName + '\', ' + ci + ', -1)" ' + (ci === 0 ? 'disabled' : '') + '>↑</button>'
                + '<button class="btn-sm" onclick="parent.moveTableCol(\'' + tblName + '\', ' + ci + ', 1)" ' + (ci === cols.length - 1 ? 'disabled' : '') + '>↓</button>'
                + '<button class="btn-sm" onclick="parent.renameTableCol(\'' + tblName + '\', ' + ci + ')">改名</button>'
                + '<button class="btn-sm red" onclick="parent.deleteTableCol(\'' + tblName + '\', ' + ci + ')">删除</button>'
                + '</div></div>';
        }).join('');
        return '<div class="tbl-container">'
            + '<div class="tbl-header">'
            + '<span class="var-index">' + (i + 1) + '</span>'
            + '<span class="tbl-name">' + tblName + '</span>'
            + '<div class="var-actions" style="margin-left:auto;">'
            + '<button class="btn-sm" onclick="parent.moveTable(' + i + ', -1)" ' + (i === 0 ? 'disabled' : '') + '>↑</button>'
            + '<button class="btn-sm" onclick="parent.moveTable(' + i + ', 1)" ' + (i === entries.length - 1 ? 'disabled' : '') + '>↓</button>'
            + '<button class="btn-sm" onclick="parent.renameTable(\'' + tblName + '\')">改名</button>'
            + '<button class="btn-sm red" onclick="parent.deleteTable(\'' + tblName + '\')">删除</button>'
            + '</div></div>'
            + colsHtml
            + '<div class="add-row" style="margin-left:32px;">'
            + '<input id="NewColName_' + tblName.replace(/[^a-zA-Z0-9]/g, '_') + '" placeholder="例如: col:value">'
            + '<button class="btn-add" onclick="parent.addTableCol(\'' + tblName + '\')">添加列</button>'
            + '</div></div>';
    }).join('');
}

function getVarNames() {
    return CustomVarsData.map(function(v) { return v.name || v; });
}

function getVarType(name) {
    var v = CustomVarsData.find(function(v) { return (v.name || v) === name; });
    return v ? (v.type || 'value') : 'value';
}

function getColType(table, colName) {
    var cols = CustomTablesData[table] || [];
    var c = cols.find(function(c) { return (c.name || c) === colName; });
    return c ? (c.type || 'value') : 'value';
}

function validateName(name) {
    if (!/^[a-zA-Z0-9_]+$/.test(name)) return '只能使用英文、数字、下划线';
    if (/^[0-9]/.test(name) && /[a-zA-Z_]/.test(name)) return '不能以数字开头且后面还有字母';
    var reserved = ['id','avatar','username','nickname','password','gender','bio','gold','exp','title','follow','fans','ban_time','vip_time','can_change_pwd','sign_date','created_at','login_at','reg_ip','custom_vars','custom_tables'];
    if (reserved.includes(name)) return '不能与基础字段重名';
    var varNames = getVarNames();
    if (varNames.includes(name)) return '变量名已存在';
    if (Object.keys(CustomTablesData).includes(name)) return '表名已存在';
    return null;
}

async function saveCustomConfig() {
    var ConfigId = localStorage.getItem('CurrentDbId') || '';
    var Tokey = localStorage.getItem('CurrentDbTokey') || '';
    var _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('custom_vars', JSON.stringify(CustomVarsData));
    _f.append('custom_tables', JSON.stringify(CustomTablesData));
    _f.append('custom_vars_defaults', JSON.stringify(CustomVarsDefaults));
    _f.append('custom_table_defaults', JSON.stringify(CustomTableDefaults));
    var _r = await apiFetch('/admin/admin_config_save.php', { method: 'POST', body: _f });
    var _d = await _r.json();
    alert(_d.message || '保存成功');
}

async function addCustomVar() {
    var name = document.getElementById('NewVarName').value.trim();
    var type = document.getElementById('NewVarType').value;
    var defaultVal = document.getElementById('NewVarDefault').value.trim() || (type === 'value' ? '0' : '');
    if (!name) return alert('请输入变量名');
    var err = validateName(name);
    if (err) return alert(err);
    CustomVarsData.push({name:name, type:type});
    CustomVarsDefaults[name] = type === 'string' ? defaultVal : defaultVal;
    document.getElementById('NewVarName').value = '';
    document.getElementById('NewVarDefault').value = '0';
    await saveCustomConfig();
    renderCustomVars();
}

async function moveVar(index, dir) {
    var newIdx = index + dir;
    if (newIdx < 0 || newIdx >= CustomVarsData.length) return;
    var temp = CustomVarsData[index];
    CustomVarsData[index] = CustomVarsData[newIdx];
    CustomVarsData[newIdx] = temp;
    await saveCustomConfig();
    renderCustomVars();
}

async function renameVar(index) {
    var oldName = CustomVarsData[index].name || CustomVarsData[index];
    var newName = prompt('新变量名:', oldName);
    if (!newName || newName === oldName) return;
    CustomVarsData[index].name = newName;
    var err = validateName(newName);
    if (err) { CustomVarsData[index].name = oldName; return alert(err); }
    await saveCustomConfig();
    renderCustomVars();
}

async function deleteVar(index) {
    CustomVarsData.splice(index, 1);
    await saveCustomConfig();
    renderCustomVars();
}

async function addCustomTable() {
    var name = document.getElementById('NewTblName').value.trim();
    var colsStr = document.getElementById('NewTblCols').value.trim();
    if (!name || !colsStr) return alert('请输入表名和列名(Name:Type格式)');
    var err = validateName(name);
    if (err) return alert(err);
    var parts = colsStr.split(',').map(function(c){ return c.trim(); }).filter(function(c){ return c; });
    if (parts.length === 0) return alert('至少需要一列');
    var cols = [];
    for (var i = 0; i < parts.length; i++) {
        var match = parts[i].match(/^([a-zA-Z0-9_]+):(value|string)$/);
        if (!match) return alert('列格式错误: ' + parts[i] + ' (需要 Name:Type)');
        cols.push({name: match[1], type: match[2]});
    }
    CustomTablesData[name] = cols;
    document.getElementById('NewTblName').value = '';
    document.getElementById('NewTblCols').value = '';
    await saveCustomConfig();
    renderCustomTables();
}

async function moveTable(index, dir) {
    var entries = Object.entries(CustomTablesData);
    var newIdx = index + dir;
    if (newIdx < 0 || newIdx >= entries.length) return;
    var temp = entries[index];
    entries[index] = entries[newIdx];
    entries[newIdx] = temp;
    CustomTablesData = Object.fromEntries(entries);
    await saveCustomConfig();
    renderCustomTables();
}

async function renameTable(oldName) {
    var newName = prompt('新表名:', oldName);
    if (!newName || newName === oldName) return;
    var err = validateName(newName);
    if (err) return alert(err);
    CustomTablesData[newName] = CustomTablesData[oldName];
    delete CustomTablesData[oldName];
    await saveCustomConfig();
    renderCustomTables();
}

async function deleteTable(name) {
    delete CustomTablesData[name];
    await saveCustomConfig();
    renderCustomTables();
}

async function addTableCol(tblName) {
    var safeId = tblName.replace(/[^a-zA-Z0-9]/g, '_');
    var input = document.getElementById('NewColName_' + safeId);
    if (!input) return;
    var raw = input.value.trim();
    if (!raw) return alert('请输入列名:类型(例如 col:value)');
    var match = raw.match(/^([a-zA-Z0-9_]+):(value|string)$/);
    if (!match) return alert('格式错误，例如: teststr:string');
    var colName = match[1], colType = match[2];
    if (!/^[a-zA-Z0-9_]+$/.test(colName)) return alert('只能使用英文、数字、下划线');
    var err = validateName(colName);
    if (err) return alert(err);
    if (!CustomTablesData[tblName]) CustomTablesData[tblName] = [];
    if (CustomTablesData[tblName].some(function(c){ return (c.name || c) === colName; })) return alert('列名已存在');
    CustomTablesData[tblName].push({name: colName, type: colType});
    document.getElementById('NewColName_' + safeId).value = '';
    await saveCustomConfig();
    renderCustomTables();
}

async function moveTableCol(tblName, colIndex, dir) {
    var cols = CustomTablesData[tblName] || [];
    var newIdx = colIndex + dir;
    if (newIdx < 0 || newIdx >= cols.length) return;
    var temp = cols[colIndex];
    cols[colIndex] = cols[newIdx];
    cols[newIdx] = temp;
    await saveCustomConfig();
    renderCustomTables();
}

async function renameTableCol(tblName, colIndex) {
    var oldName = CustomTablesData[tblName][colIndex].name || CustomTablesData[tblName][colIndex];
    var newName = prompt('新列名:', oldName);
    if (!newName || newName === oldName) return;
    if (!/^[a-zA-Z0-9_]+$/.test(newName)) return alert('只能使用英文、数字、下划线');
    if (CustomTablesData[tblName].some(function(c){ return (c.name || c) === newName; })) return alert('列名已存在');
    CustomTablesData[tblName][colIndex].name = newName;
    await saveCustomConfig();
    renderCustomTables();
}

async function deleteTableCol(tblName, colIndex) {
    CustomTablesData[tblName].splice(colIndex, 1);
    if (CustomTablesData[tblName].length === 0) delete CustomTablesData[tblName];
    await saveCustomConfig();
    renderCustomTables();
}

async function clearAllVars() {
    CustomVarsData = [];
    await saveCustomConfig();
    renderCustomVars();
}

async function clearAllTables() {
    CustomTablesData = {};
    await saveCustomConfig();
    renderCustomTables();
}

function showConfirm(msg, callback) {
    var ov = document.createElement('div'); ov.className = 'confirm-overlay'; ov.id = 'ConfirmOverlay';
    ov.innerHTML = '<div class="confirm-box"><p>'+msg+'</p><button class="btn-cancel" onclick="document.getElementById(\'ConfirmOverlay\').remove()">取消</button><button class="btn-confirm" onclick="document.getElementById(\'ConfirmOverlay\').remove();('+callback.toString()+')();">确定</button></div>';
    document.body.appendChild(ov);
}

function updateVarDefault(name, value) {
    CustomVarsDefaults[name] = value;
}

function updateTableColDefault(tblName, col, value) {
    if (!CustomTableDefaults[tblName]) CustomTableDefaults[tblName] = {};
    CustomTableDefaults[tblName][col] = value;
}