
// ========== Ranking Pages ==========


async function loadVarRanking() {
    const el = document.getElementById('VarRanking');
    if (!el) return;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    if (!ConfigId) { el.innerHTML = '<div class="empty-text">未选择数据库</div>'; return; }

    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('Page', '1'); _f.append('PageSize', '9999');
    const _r = await apiFetch('/sub_user/sub_users_list.php', { method: 'POST', body: _f });
    const _d = await _r.json();
    if (_d.code !== 0 || !_d.data.length) { el.innerHTML = '<div class="empty-text">暂无子用户数据</div>'; return; }

    const users = _d.data;
    const cfg = await getCustomConfig();
    if (!cfg) { el.innerHTML = '<div class="empty-text">加载配置失败</div>'; return; }
    const varsRaw = cfg.custom_vars;
    const vars = typeof varsRaw === 'string' ? JSON.parse(varsRaw) : (Array.isArray(varsRaw) ? varsRaw : []);

    if (!vars.length) { el.innerHTML = '<div class="empty-text">暂无自定义变量</div>'; return; }

    // 获取第一个用户的详情，触发列自动建
    const userDetails = [];
    for (const u of users) {
        try {
            const _df = new FormData();
            _df.append('UserId', localStorage.getItem('UserId'));
            _df.append('ConfigId', ConfigId);
            _df.append('Tokey', Tokey);
            _df.append('SubUserId', u.id);
            const _dr = await apiFetch('/sub_user/sub_user_detail.php', { method: 'POST', body: _df });
            const _dd = await _dr.json();
            if (_dd.code === 0) {
                userDetails.push({ ...u, custom_vars: _dd.data.custom_vars || {} });
            }
        } catch(e) {}
    }

    el.innerHTML = vars.map((vname, i) => {
        const top = userDetails
            .filter(u => {
                const val = u.custom_vars[vname];
                return val !== null && val !== undefined && val !== '' && parseInt(val) > 0;
            })
            .sort((a, b) => parseInt(b.custom_vars[vname] || 0) - parseInt(a.custom_vars[vname] || 0))
            .slice(0, 3);
        return `<div class="rank-item">
            <div class="rank-header">
                <span class="rank-var-name">${i + 1}. ${vname}</span>
                <span class="rank-more" onclick="parent.loadSubPage('Home_Admin_Custom_Ranking_VarDetail.html');parent.setVarDetail('${vname}')">更多 ›</span>
            </div>
            ${top.length === 0 ? '<div class="empty-text">暂无数据</div>' : top.map((u, j) =>
                `<div class="rank-row">${['🥇','🥈','🥉'][j] || ''} ${u.username} (ID:${u.id}) — ${u.custom_vars[vname] || 0}</div>`
            ).join('')}
        </div>`;
    }).join('');
}

function loadTableRanking() {
    const el = document.getElementById('TableRanking');
    if (!el) return;
    const tables = CustomTablesData || {};
    const tblNames = Object.keys(tables);
    if (!tblNames.length) { el.innerHTML = '<div class="empty-text">暂无自定义JSON表</div>'; return; }

    // 生成表格的简要信息，提供展开功能
    el.innerHTML = tblNames.map((name, i) => `
        <div class="rank-item">
            <div class="rank-header">
                <span class="rank-var-name">${i + 1}. ${name}</span>
                <span class="rank-more" onclick="parent.toggleTableDetail('${name}')">展开/收缩</span>
            </div>
            <div class="table-detail" id="tableDetail_${name}" style="display:none; margin-top:8px; padding: 8px; background:#faf7f2; border-radius: 8px; font-size: 12px;">
                ${(tables[name] || []).length > 0 ? 
                    `<div class="card-title" style="font-size:12px;">列定义</div>
                    ${tables[name].map(col => `<div class="empty-text" style="margin-left:12px;">• ${escapeHtml(col)}</div>`).join('')}
                    <div class="card-title" style="font-size:12px; margin-top:8px;">数据预览</div>
                    <div class="empty-text">点击"更多"查看完整数据</div>
                    <span class="rank-more" onclick="parent.loadSubPage('Home_Admin_Custom_Ranking_TableDetail.html');parent.setTableDetail('${name}')" style="font-size:11px;">更多 ›</span>`
                    : '<div class="empty-text">无列定义</div>'
                }
            </div>
        </div>
    `).join('');
}

// 新增切换展开/收缩的函数
function toggleTableDetail(tableName) {
    const detail = document.getElementById('tableDetail_' + tableName);
    if (detail) {
        detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
    }
}

async function getCustomConfig() {
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    const _r = await apiFetch('/admin/admin_config_get.php', { method: 'POST', body: _f });
    const _d = await _r.json();
    return _d.code === 0 ? _d.data : null;
}



async function setVarDetail(varName) {
    setTimeout(async () => {
        const titleEl = document.getElementById('VarDetailTitle');
        const listEl = document.getElementById('VarDetailList');
        if (titleEl) titleEl.textContent = varName + ' 排行榜';
        if (!listEl) return;

        const ConfigId = localStorage.getItem('CurrentDbId') || '';
        const Tokey = localStorage.getItem('CurrentDbTokey') || '';
        const _f = new FormData();
        _f.append('UserId', localStorage.getItem('UserId'));
        _f.append('ConfigId', ConfigId);
        _f.append('Tokey', Tokey);
        _f.append('Page', '1'); _f.append('PageSize', '9999');
        const _r = await apiFetch('/sub_user/sub_users_list.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code !== 0 || !_d.data.length) { listEl.innerHTML = '<div class="empty-text">暂无数据</div>'; return; }

        const users = _d.data;
        const userDetails = [];

        // 逐个获取详情
        for (const u of users) {
            try {
                const _df = new FormData();
                _df.append('UserId', localStorage.getItem('UserId'));
                _df.append('ConfigId', ConfigId);
                _df.append('Tokey', Tokey);
                _df.append('SubUserId', u.id);
                const _dr = await apiFetch('/sub_user/sub_user_detail.php', { method: 'POST', body: _df });
                const _dd = await _dr.json();
                if (_dd.code === 0) {
                    userDetails.push({
                        ...u,
                        varValue: _dd.data.custom_vars?.[varName] || 0
                    });
                }
            } catch(e) {}
        }

        const sorted = userDetails
            .filter(u => parseInt(u.varValue) > 0)
            .sort((a, b) => parseInt(b.varValue) - parseInt(a.varValue));

        listEl.innerHTML = sorted.map((u, i) => {
            const rankClass = i === 0 ? 'gold' : '';
            return `<div class="detail-row">
                <span class="detail-rank ${rankClass}">${i + 1}</span>
                <img src="sub_avatars/${ConfigId}/${u.avatar || 'icon.png'}" class="detail-avatar" onerror="this.src='/images/icon.png'">
                <div class="detail-info"><div class="detail-name">${u.username}</div><div class="detail-value">${varName}: ${u.varValue}</div></div>
            </div>`;
        }).join('');
    }, 300);
}

async function setTableDetail(tableName) {
    setTimeout(async () => {
        const titleEl = document.getElementById('TableDetailTitle');
        const listEl = document.getElementById('TableDetailList');
        if (titleEl) titleEl.textContent = tableName + ' 数据详情';
        if (!listEl) return;

        const ConfigId = localStorage.getItem('CurrentDbId') || '';
        const Tokey = localStorage.getItem('CurrentDbTokey') || '';

        // 先拿用户列表
        const _f = new FormData();
        _f.append('UserId', localStorage.getItem('UserId'));
        _f.append('ConfigId', ConfigId);
        _f.append('Tokey', Tokey);
        _f.append('Page', '1'); _f.append('PageSize', '9999');
        const _r = await apiFetch('/sub_user/sub_users_list.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code !== 0 || !_d.data.length) { listEl.innerHTML = '<div class="empty-text">暂无数据</div>'; return; }

        const users = _d.data;
        const cols = CustomTablesData[tableName] || [];
        let html = '';

        // 逐个获取用户详情（为了拿到 JSON 列数据）
        for (let i = 0; i < users.length; i++) {
            const u = users[i];
            try {
                const _df = new FormData();
                _df.append('UserId', localStorage.getItem('UserId'));
                _df.append('ConfigId', ConfigId);
                _df.append('Tokey', Tokey);
                _df.append('SubUserId', u.id);
                const _dr = await apiFetch('/sub_user/sub_user_detail.php', { method: 'POST', body: _df });
                const _dd = await _dr.json();
                
                let tableData = [];
                if (_dd.code === 0 && _dd.data.custom_tables && _dd.data.custom_tables[tableName]) {
                    tableData = _dd.data.custom_tables[tableName].rows || [];
                }
                
                const rowCount = tableData.length;
                let rowsHtml = '';
                if (rowCount > 0) {
                    rowsHtml = tableData.map((row, ri) => `
                        <div style="margin-left:16px;font-size:11px;color:#8b7355;">
                            行${ri + 1}: ${escapeHtml(cols.map(c => `${c}=${row[c] || '-'}`).join(', '))}
                        </div>`).join('');
                }
                
                html += `<div class="detail-row" style="flex-direction:column;align-items:flex-start;">
                    <div style="display:flex;align-items:center;gap:8px;width:100%;">
                        <span class="detail-rank">${i + 1}</span>
                        <img src="sub_avatars/${ConfigId}/${u.avatar || 'icon.png'}" class="detail-avatar" onerror="this.src='/images/icon.png'">
                        <div class="detail-info">
                            <div class="detail-name">${u.username}</div>
                            <div class="detail-value">${tableName}: ${rowCount} 行数据</div>
                        </div>
                    </div>
                    ${rowsHtml}
                </div>`;
            } catch(e) {
                html += `<div class="detail-row">
                    <span class="detail-rank">${i + 1}</span>
                    <div class="detail-info"><div class="detail-name">${u.username}</div><div class="detail-value">加载失败</div></div>
                </div>`;
            }
        }

        listEl.innerHTML = html;
    }, 300);
}



function toggleSection(type) {
    const sectionId = type === 'var' ? 'VarSection' : 'TableSection';
    const toggleId = type === 'var' ? 'VarToggle' : 'TableToggle';
    const section = document.getElementById(sectionId);
    const toggle = document.getElementById(toggleId);
    if (!section || !toggle) return;
    if (section.style.display === 'none') {
        section.style.display = 'block';
        toggle.classList.remove('expanded');
    } else {
        section.style.display = 'none';
        toggle.classList.add('expanded');
    }
}




function updateVarDefault(name, value) {
    CustomVarsDefaults[name] = value;
}


function updateTableColDefault(tblName, col, value) {
    if (!CustomTableDefaults[tblName]) CustomTableDefaults[tblName] = {};
    CustomTableDefaults[tblName][col] = value;
}

