// ========== 卡密系统 ==========
// 依赖 admin_custom.js: getVarNames(), getVarType(), getColType(), CustomTablesData

var ckActiveType = 'all';
var ckPage = 1;
var ckHasMore = true;
var ckLoading = false;
var customRewardId = 0;

function getCkAuth() {
    return {
        UserId: localStorage.getItem('UserId') || '',
        ConfigId: localStorage.getItem('CurrentDbId') || '',
        Tokey: localStorage.getItem('CurrentDbTokey') || ''
    };
}

async function loadCards(page, append) {
    if (ckLoading) return;
    // 卡密列表容器不存在或不可见（不在卡密页）时，直接跳过，避免在其他页面误请求
    var _ckEl = document.getElementById('cardList');
    if (!_ckEl || _ckEl.offsetParent === null) return;
    var auth = getCkAuth();
    // 缺少必要鉴权参数时不请求，避免服务端 400 刷屏
    if (!auth.UserId || !auth.ConfigId || !auth.Tokey) { ckHasMore = false; return; }
    ckLoading = true;
    var f = new FormData();
    f.append('UserId', auth.UserId);
    f.append('ConfigId', auth.ConfigId);
    f.append('Tokey', auth.Tokey);
    f.append('Page', page || 1);
    f.append('PageSize', 20);
    if (ckActiveType !== 'all') f.append('Type', ckActiveType);

    try {
        var r = await fetch('cardkey/list.php', { method: 'POST', body: f });
        var d = await r.json();
        if (d.code === 0) {
            ckHasMore = d.list.length >= d.pageSize;
            renderCards(d.list, append);
        } else {
            // 请求失败（如参数错误/鉴权失败），停止继续加载，避免滚动刷屏
            ckHasMore = false;
        }
    } catch (e) { console.error(e); ckHasMore = false; }
    ckLoading = false;
}

function renderCards(list, append) {
    var el = document.getElementById('cardList');
    if (!el) return;
    if (!append) el.innerHTML = '';

    if (!list || list.length === 0) {
        if (!append) el.innerHTML = '<div class="ck-empty">暂无卡密，点击右上角 + 生成</div>';
        return;
    }

    var html = list.map(function(c) {
        var rewardsHtml = '';
        var isUsed = c.used_at !== null && c.used_at !== '0000-00-00 00:00:00';
        var statusClass = isUsed ? 'used' : 'unused';
        var statusText = isUsed ? '已使用' : '未使用';
        var userHtml = c.used_username ? '<div class="ck-user">' + c.used_username + '</div>' : '';
        var timeStr = c.created_at || '';

        if (c.type == 1) {
            rewardsHtml = '<div class="ck-card-rewards">';
            if (c.coin_num) rewardsHtml += '<span class="ck-reward gold">' + c.coin_num + ' 金币</span>';
            rewardsHtml += '</div>';
        } else if (c.type == 2) {
            rewardsHtml = '<div class="ck-card-rewards"><span class="ck-reward vip">' + (c.vip_days || 0) + ' 天VIP</span></div>';
        } else if (c.type == 3) {
            rewardsHtml = '<div class="ck-card-rewards">';
            try {
                var vl = typeof c.value_list === 'string' ? JSON.parse(c.value_list) : (c.value_list || []);
                vl.forEach(function(r) {
                    rewardsHtml += '<span class="ck-reward var">' + r.name + '=' + r.value + '</span>';
                });
            } catch(e) {}
            rewardsHtml += '</div>';
        } else if (c.type == 4) {
            rewardsHtml = '<div class="ck-card-rewards">';
            try {
                var jl = typeof c.json_list === 'string' ? JSON.parse(c.json_list) : (c.json_list || []);
                jl.forEach(function(r) {
                    rewardsHtml += '<span class="ck-reward json">' + r.table + '.' + r.col + '=' + r.value + '</span>';
                });
            } catch(e) {}
            rewardsHtml += '</div>';
        }

        // 已使用的卡密不可删除（后端也只删未使用的），故仅未使用时显示删除按钮
        var delBtn = isUsed ? '' : '<button class="ck-del-btn" onclick="doDeleteCard(' + c.id + ')">删除</button>';
        return '<div class="ck-card">' +
            '<div class="ck-card-left"><div class="ck-card-code">' + c.code + '</div>' + rewardsHtml + '<div class="ck-card-time">' + (timeStr || '') + '</div></div>' +
            '<div class="ck-card-right"><span class="ck-status ' + statusClass + '">' + statusText + '</span>' + userHtml + delBtn + '</div></div>';
    }).join('');

    el.innerHTML = html;
}

// 删除卡密（仅未使用的可删）
async function doDeleteCard(cardId) {
    if (!confirm('确定删除这张卡密吗？删除后不可恢复。')) return;
    var auth = getCkAuth();
    var f = new FormData();
    f.append('UserId', auth.UserId);
    f.append('ConfigId', auth.ConfigId);
    f.append('Tokey', auth.Tokey);
    f.append('CardId', cardId);
    try {
        var r = await fetch('cardkey/delete.php', { method: 'POST', body: f });
        var d = await r.json();
        if (d.code === 0) {
            ckPage = 1; ckHasMore = true;
            loadCards(1, false);
        } else { alert(d.message); }
    } catch (e) { alert('网络错误'); }
}

// 下拉加载
window.addEventListener('scroll', function() {
    if (ckLoading || !ckHasMore) return;
    // 仅在卡密页（cardList 可见）时才触发，避免在聊天等其他页面误请求
    var _ckEl = document.getElementById('cardList');
    if (!_ckEl || _ckEl.offsetParent === null) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 400) {
        ckPage++;
        loadCards(ckPage, true);
    }
});

// 类型切换
function setupCardKeyEvents() {
    var tabs = document.getElementById('typeTabs');
    if (tabs) {
        tabs.addEventListener('click', function(e) {
            if (e.target.classList.contains('ck-tab')) {
                document.querySelectorAll('.ck-tab').forEach(function(t) { t.classList.remove('active'); });
                e.target.classList.add('active');
                ckActiveType = e.target.dataset.type;
                ckPage = 1;
                ckHasMore = true;
                loadCards(1, false);
            }
        });
    }
    var overlay = document.getElementById('generateOverlay');
    if (overlay) {
        overlay.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    }
    loadCards(1, false);
}

// 弹窗
function openGenerate() {
    var overlay = document.getElementById('generateOverlay');
    overlay.classList.add('show');
    renderGenSheet();
}

function renderGenSheet() {
    var type = ckActiveType === 'all' ? 'gold' : ckActiveType;
    var html = '<div class="ck-sheet-title">生成卡密</div>';
    if (type === 'gold') html += renderGoldSheet();
    else if (type === 'vip') html += renderVipSheet();
    else if (type === 'custom') html += renderCustomSheet();
    document.getElementById('genSheet').innerHTML = html;
    if (type === 'custom') setupCustomEvents();
}

function renderGoldSheet() {
    return '<div class="ck-row"><label>数量</label><input type="number" id="genCount" value="10" min="1" max="100"></div>' +
        '<div class="ck-row"><label>金币</label><input type="number" id="genGold" value="500" min="0"></div>' +
        '<button class="ck-sheet-submit" onclick="doGenerateGold()">生成卡密</button>';
}

function renderVipSheet() {
    return '<div class="ck-row"><label>数量</label><input type="number" id="genCount" value="10" min="1" max="100"></div>' +
        '<div class="ck-row"><label>VIP(天)</label><input type="number" id="genVipDays" value="3" min="1"></div>' +
        '<button class="ck-sheet-submit" onclick="doGenerateVip()">生成卡密</button>';
}

function renderCustomSheet() {
    customRewardId = 0;
    return '<div class="ck-row"><label>数量</label><input type="number" id="genCount" value="5" min="1" max="100"></div>' +
        '<div class="ck-row"><label>类型</label>' +
        '<div class="ck-sheet-types" id="genCustomType">' +
        '<span class="ck-sheet-type active" data-type="var">变量</span>' +
        '<span class="ck-sheet-type" data-type="json">JSON</span>' +
        '</div>' +
        '<button class="ck-add-btn" style="width:34px;height:34px;flex-shrink:0;" onclick="addCustomReward()">+</button>' +
        '</div>' +
        '<div class="ck-reward-list" id="customRewardList"></div>' +
        '<button class="ck-sheet-submit" onclick="doGenerateCustom()">生成卡密</button>';
}

function setupCustomEvents() {
    var genCustomType = document.getElementById('genCustomType');
    if (genCustomType) {
        genCustomType.addEventListener('click', function(e) {
            if (e.target.classList.contains('ck-sheet-type')) {
                document.querySelectorAll('#genCustomType .ck-sheet-type').forEach(function(b) { b.classList.remove('active'); });
                e.target.classList.add('active');
                refreshCustomRewards();
            }
        });
    }
    refreshCustomRewards();
    // 兜底：若自定义配置尚未加载完，加载完成后再刷新一次奖励项下拉
    if (typeof CustomTablesData === 'undefined' || Object.keys(CustomTablesData).length === 0) {
        loadCustomConfig().then(refreshCustomRewards);
    }
}

function addCustomReward() {
    var cType = getCustomGenType();
    customRewardId++;
    var rid = customRewardId;
    var list = document.getElementById('customRewardList');
    var div = document.createElement('div');
    div.className = 'ck-reward-item';
    div.id = 'reward_' + rid;
    if (cType === 'var') {
        var varNames = (typeof getVarNames === 'function') ? getVarNames() : [];
        var opts = varNames.map(function(v) { return '<option value="' + v + '">' + v + ' (' + ((typeof getVarType === 'function') ? getVarType(v) : 'value') + ')</option>'; }).join('');
        div.innerHTML = '<select>' + opts + '</select><input type="text" placeholder="值" value="0"><button class="ck-reward-del" onclick="delReward(' + rid + ')">x</button>';
    } else {
        var tables = (typeof CustomTablesData !== 'undefined') ? CustomTablesData : {};
        var tblNames = Object.keys(tables);
        var tblOpts = tblNames.map(function(t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
        var firstTbl = tblNames[0] || '';
        var cols = tables[firstTbl] || [];
        var colOpts = cols.map(function(c) {
            var cname = (typeof c === 'string') ? c : (c.name || c);
            return '<option value="' + cname + '">' + cname + '</option>';
        }).join('');
        div.innerHTML = '<select class="json-tbl" onchange="onJsonTblChange(this)">' + tblOpts + '</select>' +
            '<select class="json-col">' + colOpts + '</select>' +
            '<input type="text" placeholder="值" value="0">' +
            '<button class="ck-reward-del" onclick="delReward(' + rid + ')">x</button>';
    }
    list.appendChild(div);
}

function delReward(id) {
    var el = document.getElementById('reward_' + id);
    if (el) el.remove();
}

function getCustomGenType() {
    var actives = document.querySelectorAll('#genCustomType .ck-sheet-type.active');
    return actives.length > 0 ? actives[0].dataset.type : 'var';
}

function refreshCustomRewards() {
    var list = document.getElementById('customRewardList');
    if (!list) return;
    list.innerHTML = '';
    customRewardId = 0;
    for (var i = 0; i < 2; i++) addCustomReward();
}

function onJsonTblChange(sel) {
    var row = sel.parentElement;
    var colSel = row.querySelector('.json-col');
    var tbl = sel.value;
    var tables = (typeof CustomTablesData !== 'undefined') ? CustomTablesData : {};
    var cols = tables[tbl] || [];
    colSel.innerHTML = cols.map(function(c) {
        var cname = (typeof c === 'string') ? c : (c.name || c);
        return '<option value="' + cname + '">' + cname + '</option>';
    }).join('');
}

async function doGenerateGold() {
    var count = parseInt(document.getElementById('genCount').value) || 10;
    var gold = parseInt(document.getElementById('genGold').value) || 0;
    var auth = getCkAuth();
    var f = new FormData();
    f.append('UserId', auth.UserId);
    f.append('ConfigId', auth.ConfigId);
    f.append('Tokey', auth.Tokey);
    f.append('Count', count);
    f.append('Type', 1);
    f.append('Gold', gold);
    try {
        var r = await fetch('cardkey/generate.php', { method: 'POST', body: f });
        var d = await r.json();
        if (d.code === 0) {
            document.getElementById('generateOverlay').classList.remove('show');
            ckPage = 1; ckHasMore = true;
            loadCards(1, false);
        } else { alert(d.message); }
    } catch (e) { alert('网络错误'); }
}

async function doGenerateVip() {
    var count = parseInt(document.getElementById('genCount').value) || 10;
    var vipDays = parseInt(document.getElementById('genVipDays').value) || 3;
    var auth = getCkAuth();
    var f = new FormData();
    f.append('UserId', auth.UserId);
    f.append('ConfigId', auth.ConfigId);
    f.append('Tokey', auth.Tokey);
    f.append('Count', count);
    f.append('Type', 2);
    f.append('VipDays', vipDays);
    try {
        var r = await fetch('cardkey/generate.php', { method: 'POST', body: f });
        var d = await r.json();
        if (d.code === 0) {
            document.getElementById('generateOverlay').classList.remove('show');
            ckPage = 1; ckHasMore = true;
            loadCards(1, false);
        } else { alert(d.message); }
    } catch (e) { alert('网络错误'); }
}

async function doGenerateCustom() {
    var count = parseInt(document.getElementById('genCount').value) || 5;
    var cType = getCustomGenType();
    var items = document.querySelectorAll('#customRewardList .ck-reward-item');
    var rewards = [];
    items.forEach(function(item) {
        var selects = item.querySelectorAll('select');
        var input = item.querySelector('input');
        if (cType === 'var') {
            var name = selects[0].value;
            var val = input.value;
            if (name && val !== '') rewards.push({ name: name, value: val });
        } else {
            var tbl = selects[0].value;
            var col = selects[1].value;
            var val = input.value;
            if (tbl && col && val !== '') rewards.push({ table: tbl, col: col, value: val });
        }
    });
    if (rewards.length === 0) { alert('请添加至少一个奖励项'); return; }
    if (cType === 'var') {
        var varNames = (typeof getVarNames === 'function') ? getVarNames() : [];
        rewards = rewards.filter(function(r) { return varNames.indexOf(r.name) !== -1; });
    } else {
        rewards = rewards.filter(function(r) {
            var tables = (typeof CustomTablesData !== 'undefined') ? CustomTablesData : {};
            return (tables[r.table] !== undefined);
        });
    }
    if (rewards.length === 0) { alert('所有奖励项均无效（变量/JSON不存在）'); return; }

    var auth = getCkAuth();
    var f = new FormData();
    f.append('UserId', auth.UserId);
    f.append('ConfigId', auth.ConfigId);
    f.append('Tokey', auth.Tokey);
    f.append('Count', count);
    if (cType === 'var') {
        f.append('Type', 3);
        f.append('ValueList', JSON.stringify(rewards));
    } else {
        f.append('Type', 4);
        f.append('JsonList', JSON.stringify(rewards));
    }
    try {
        var r = await fetch('cardkey/generate.php', { method: 'POST', body: f });
        var d = await r.json();
        if (d.code === 0) {
            document.getElementById('generateOverlay').classList.remove('show');
            ckPage = 1; ckHasMore = true;
            loadCards(1, false);
        } else { alert(d.message); }
    } catch (e) { alert('网络错误'); }
}