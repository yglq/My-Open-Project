
// ========== Sub Users ==========
let CurrentUserPage = 1;

async function loadUserList(page) {
    CurrentUserPage = page || 1;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    const PageSize = document.getElementById('PageSizeSelect')?.value || '20';
    const ListEl = document.getElementById('UserList');
    if (!ListEl || !ConfigId) return;

    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('Page', String(page || 1));
    _f.append('PageSize', PageSize);

    try {
        const _r = await apiFetch('/sub_user/sub_users_list.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            renderUserList(_d.data, _d.total, _d.totalPages);
        }
    } catch (_e) { ListEl.innerHTML = '<div style="text-align:center;padding:20px;color:#c84628;">加载失败</div>'; }
}

function renderUserList(users, total, totalPages) {
    const ListEl = document.getElementById('UserList');
      const ConfigId = localStorage.getItem('CurrentDbId') || '';
    if (!users || users.length === 0) {
        ListEl.innerHTML = '<div style="text-align:center;padding:30px;color:#b8a48c;">暂无子用户</div>';
        renderPagination(0, 1);
        return;
    }

    ListEl.innerHTML = users.map(u => {
        const isBanned = u.ban_time && new Date(u.ban_time) > new Date();
        const isVip = u.vip_time && new Date(u.vip_time) > new Date();
        const signedToday = u.sign_date === new Date().toISOString().split('T')[0];
        const genderIcon = u.gender == 1 ? '♂️' : u.gender == 2 ? '♀️' : '<span style="font-size:10px;color:#b8a48c;">未知</span>';

        return `
        <div class="user-item" onclick="parent.showUserDetail(${u.id})">
<img src="sub_avatars/${ConfigId}/${u.avatar || 'icon.png'}"  class="user-avatar" onerror="this.src='/images/icon.png'">
            <div class="user-info">
                <div class="user-info-top">
                    <span class="user-gender">${genderIcon}</span>
                    <span class="user-username ${isBanned ? 'banned' : ''}">${u.username}</span>
                    ${signedToday ? '<span class="user-icon">✅</span>' : ''}
                    ${isBanned ? '<span class="user-icon">🚫</span>' : ''}
                    ${isVip ? '<span class="user-icon">👑</span>' : ''}
                </div>
                <div class="user-nickname">昵称: ${u.nickname || '-'}</div>
            </div>
            <div class="user-time">
                <span>注册: ${u.created_at ? u.created_at.substring(0, 10) : '-'}</span>
                <span>登录: ${u.login_at ? u.login_at.substring(0, 10) : '-'}</span>
            </div>
        </div>`;
    }).join('');

    renderPagination(total, totalPages);
}

function renderPagination(total, totalPages) {
    const El = document.getElementById('PageNumbers');
    if (!El) return;
    let html = '';
    const maxShow = 7;
    let start = Math.max(1, CurrentUserPage - 3);
    let end = Math.min(totalPages, start + maxShow - 1);
    if (end - start < maxShow) start = Math.max(1, end - maxShow + 1);

    html += `<button class="page-btn" ${CurrentUserPage === 1 ? 'disabled' : ''} onclick="parent.loadUserList(${CurrentUserPage - 1})">‹</button>`;
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === CurrentUserPage ? 'active' : ''}" onclick="parent.loadUserList(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" ${CurrentUserPage >= totalPages ? 'disabled' : ''} onclick="parent.loadUserList(${CurrentUserPage + 1})">›</button>`;
    El.innerHTML = html;
}

// 新建子用户弹窗
function showCreateUserPopup() {
    const ov = document.createElement('div'); ov.className = 'overlay'; ov.id = 'CreateUserOverlay';
    ov.onclick = function(e) { if (e.target === ov) ov.remove(); };
    ov.innerHTML = `<div class="popup">
        <h3>新建子用户</h3>
        <input id="NewUsername" placeholder="账号">
        <input id="NewNickname" placeholder="昵称">
        <input id="NewPassword" placeholder="密码">
        <div style="display:flex;gap:10px;margin-top:10px;">
            <button onclick="document.getElementById('CreateUserOverlay').remove()" style="flex:1;background:#e8e3d9;">取消</button>
            <button onclick="parent.createSubUser()" style="flex:1;background:#c84628;color:#fff;">创建</button>
        </div>
    </div>`;
    document.body.appendChild(ov);
}

async function createSubUser() {
    const username = document.getElementById('NewUsername').value.trim();
    const nickname = document.getElementById('NewNickname').value.trim();
    const password = document.getElementById('NewPassword').value;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';

    if (!username || !nickname || !password) { alert('请填写完整'); return; }

    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('Username', username);
    _f.append('Nickname', nickname);
    _f.append('Password', password);

    try {
        const _r = await apiFetch('/sub_user/sub_user_create.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            document.getElementById('CreateUserOverlay').remove();
            loadUserList(1);
        } else { alert(_d.message); }
    } catch (_e) { alert('网络错误'); }
}

// // 编辑子用户（点击列表项）
// function editUserPopup(userId) {
//     // 预留，后续做编辑界面
//     alert('编辑功能后续完善');
// }



// ========== Sub User Detail ==========
let CurrentSubUserId = 0;
let OriginalDetailData = {};

async function showUserDetail(userId) {
    CurrentSubUserId = userId;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';

    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('SubUserId', userId);

    try {
        const _r = await apiFetch('/sub_user/sub_user_detail.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            OriginalDetailData = JSON.parse(JSON.stringify(_d.data));
            renderUserDetailPopup(_d.data);
        } else {
            alert(_d.message);
        }
    } catch (_e) { alert('加载失败'); }
}

function renderUserDetailPopup(data) {
    const _old = document.getElementById('UserDetailOverlay');
    if (_old) _old.remove();

    const ov = document.createElement('div');
    ov.id = 'UserDetailOverlay';
    ov.className = 'overlay';
    ov.onclick = function(e) { if (e.target === ov) ov.remove(); };

    const u = data.user;
    const customVars = data.custom_vars || {};
    const customTables = data.custom_tables || {};
    const ConfigId = localStorage.getItem('CurrentDbId') || '';

    let html = `
    <div class="popup" style="max-width:550px;padding:16px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <button onclick="document.getElementById('UserDetailOverlay').remove()" class="users-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b7355" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span style="font-size:16px;font-weight:700;">用户名: ${u.username}</span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <img src="sub_avatars/${ConfigId}/${u.avatar || 'icon.png'}" id="DetailAvatar"
                 style="width:60px;height:60px;border-radius:16px;object-fit:cover;background:#e8e3d9;"
                 onerror="this.src='/images/icon.png'"
                 onclick="document.getElementById('AvatarFileInput').click()">
            <div style="flex:1;">
                <div><span style="color:#8b7355;">昵称:</span> <input id="EditNickname" value="${u.nickname || ''}" style="border:1px solid #e0d5c1;border-radius:6px;padding:4px 8px;font-size:13px;width:140px;"></div>
                <div style="margin-top:4px;"><span style="color:#8b7355;">密码:</span> <input id="EditPassword" value="${u.password || ''}" style="border:1px solid #e0d5c1;border-radius:6px;padding:4px 8px;font-size:13px;width:140px;"></div>
            </div>
        </div>
        <input type="file" id="AvatarFileInput" accept="image/*" style="display:none;" onchange="parent.uploadSubAvatar()">

        <div style="margin-bottom:8px;">
            <span style="color:#8b7355;">介绍:</span>
            <textarea id="EditBio" style="width:100%;border:1px solid #e0d5c1;border-radius:8px;padding:6px;font-size:13px;resize:vertical;min-height:40px;box-sizing:border-box;">${u.bio || ''}</textarea>
        </div>

        <div style="margin-bottom:8px;display:flex;gap:16px;">
            <label><input type="radio" name="editGender" value="1" ${u.gender == 1 ? 'checked' : ''}> 男</label>
            <label><input type="radio" name="editGender" value="2" ${u.gender == 2 ? 'checked' : ''}> 女</label>
            <label><input type="radio" name="editGender" value="0" ${!u.gender || u.gender == 0 ? 'checked' : ''}> 未知</label>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
            <span style="color:#8b7355;">金币:</span> <input id="EditGold" value="${u.gold || 0}" style="width:70px;border:1px solid #e0d5c1;border-radius:6px;padding:4px;font-size:13px;">
            <span style="color:#8b7355;margin-left:8px;">经验:</span> <input id="EditExp" value="${u.exp || 0}" style="width:70px;border:1px solid #e0d5c1;border-radius:6px;padding:4px;font-size:13px;">
            <span style="color:#8b7355;margin-left:8px;">称号:</span> <input id="EditTitle" value="${u.title || ''}" style="width:100px;border:1px solid #e0d5c1;border-radius:6px;padding:4px;font-size:13px;">
        </div>

        <div style="margin-bottom:8px;">
            <span>关注: ${(u.follow ? JSON.parse(u.follow).length : 0)}人</span>
            <span style="margin-left:16px;">粉丝: ${(u.fans ? JSON.parse(u.fans).length : 0)}人</span>
            <label style="margin-left:16px;"><input type="checkbox" id="EditCanPwd" ${u.can_change_pwd == 1 ? 'checked' : ''}> 可修改密码</label>
        </div>

        <div style="background:#faf7f2;border-radius:12px;padding:10px;margin-bottom:8px;">
            <div style="font-weight:600;margin-bottom:6px;">自定义变量</div>
            ${Object.keys(customVars).length === 0 ? '<div style="color:#b8a48c;font-size:12px;">暂无变量</div>' :
                Object.entries(customVars).map(([k, v]) => `
                    <div style="display:flex;gap:6px;margin-bottom:4px;align-items:center;">
                        <span style="width:80px;font-size:12px;color:#8b7355;">${k}:</span>
                        <input value="${v}" data-var-name="${k}" class="edit-custom-var" style="width:120px;border:1px solid #e0d5c1;border-radius:4px;padding:2px 6px;font-size:12px;">
                    </div>`).join('')}
        </div>
        ${Object.keys(customTables).length === 0 ? '<div style="color:#b8a48c;font-size:12px;">暂无表格</div>' :
    Object.entries(customTables).map(([tblName, tblData]) => {
        const cols = tblData.columns || [];
        let rows = tblData.rows || [];
        // 空数据时用 columns 构建一行空对象
        if (rows.length === 0 && cols.length > 0) {
            const emptyRow = {};
            cols.forEach(c => { emptyRow[c] = ''; });
            rows = [emptyRow];
        }
        let tblHtml = `<div style="margin-bottom:8px;background:#fff;border-radius:8px;padding:8px;">
            <div style="font-size:12px;font-weight:600;margin-bottom:6px;">${tblName}</div>`;
        rows.forEach((row, ri) => {
            tblHtml += '<div style="margin-bottom:4px;">';
            // 按 columns 顺序遍历，保证显示顺序
            const displayKeys = cols.length > 0 ? cols : Object.keys(row);
            displayKeys.forEach(k => {
                tblHtml += `<div style="display:flex;gap:6px;margin-bottom:2px;align-items:center;">
                    <span style="width:80px;font-size:12px;color:#8b7355;">${k}:</span>
                    <input value="${row[k] || ''}" data-tbl="${tblName}" data-row="${ri}" data-col="${k}"
                           style="flex:1;border:1px solid #e0d5c1;border-radius:4px;padding:2px 6px;font-size:12px;">
                </div>`;
            });
            tblHtml += '</div>';
        });
        tblHtml += '</div>';
        return tblHtml;
    }).join('')}
        <div style="margin-bottom:8px;">
            <div><span style="color:#8b7355;">拉黑时间:</span> <input type="datetime-local" id="EditBanTime" value="${u.ban_time ? u.ban_time.substring(0, 16) : '1999-01-01T01:00'}"></div>
            <div><span style="color:#8b7355;">会员时间:</span> <input type="datetime-local" id="EditVipTime" value="${u.vip_time ? u.vip_time.substring(0, 16) : '1999-01-01T01:00'}"></div>
            <div><span style="color:#b8a48c;">登录日期:</span> ${u.login_at || '-'} (不可改)</div>
            <div><span style="color:#b8a48c;">注册日期:</span> ${u.created_at || '-'} (不可改)</div>
            <div><span style="color:#b8a48c;">注册IP:</span> ${u.reg_ip || '-'} (不可改)</div>
        </div>

        <div style="display:flex;gap:8px;margin-top:10px;">
            <button onclick="parent.saveUserDetail()" style="flex:1;padding:10px;background:#c84628;color:#fff;border:none;border-radius:10px;font-weight:600;">保存</button>
            <button onclick="parent.deleteSubUser(${u.id})" style="padding:10px 16px;background:#fff;color:#c84628;border:1px solid #fecaca;border-radius:10px;">删除账号</button>
        </div>
    </div>`;

    ov.innerHTML = html;
    document.body.appendChild(ov);
}

async function saveUserDetail() {
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('SubUserId', CurrentSubUserId);

    const orig = OriginalDetailData.user || {};
    const nickname = document.getElementById('EditNickname')?.value || '';
    const password = document.getElementById('EditPassword')?.value || '';
    const bio = document.getElementById('EditBio')?.value || '';
    const gold = document.getElementById('EditGold')?.value || '0';
    const exp = document.getElementById('EditExp')?.value || '0';
    const title = document.getElementById('EditTitle')?.value || '';
    const canPwd = document.getElementById('EditCanPwd')?.checked ? '1' : '0';
    const genderEl = document.querySelector('input[name="editGender"]:checked');
    const gender = genderEl ? genderEl.value : '0';

    if (nickname !== (orig.nickname || '')) _f.append('nickname', nickname);
    if (password !== (orig.password || '')) {
        _f.append('password', password);
    }
    if (bio !== (orig.bio || '')) _f.append('bio', bio);
    if (gold !== String(orig.gold || 0)) _f.append('gold', gold);
    if (exp !== String(orig.exp || 0)) _f.append('exp', exp);
    if (title !== (orig.title || '')) _f.append('title', title);
    if (gender !== String(orig.gender || 0)) _f.append('gender', gender);
    if (canPwd !== String(orig.can_change_pwd ?? 1)) _f.append('can_change_pwd', canPwd);

    const banVal = document.getElementById('EditBanTime')?.value || '1999-01-01T01:00';
    const vipVal = document.getElementById('EditVipTime')?.value || '1999-01-01T01:00';
    const banNew = banVal.replace('T', ' ') + ':00';
    const vipNew = vipVal.replace('T', ' ') + ':00';
    const banOld = orig.ban_time ? orig.ban_time.substring(0, 19) : '1999-01-01 01:00:00';
    const vipOld = orig.vip_time ? orig.vip_time.substring(0, 19) : '1999-01-01 01:00:00';
    if (banNew !== banOld) _f.append('ban_time', banNew);
    if (vipNew !== vipOld) _f.append('vip_time', vipNew);

    document.querySelectorAll('.edit-custom-var').forEach(el => {
        const name = el.dataset.varName;
        if (!name) return;
        const val = el.value || '0';
        const origVal = (OriginalDetailData.custom_vars || {})[name];
        if (String(val) !== String(origVal ?? 0)) {
            _f.append(name, val);
        }
    });


        const tblMap = {};
    document.querySelectorAll('[data-tbl]').forEach(el => {
        const tblName = el.dataset.tbl;
        const rowIdx = parseInt(el.dataset.row);
        const colName = el.dataset.col;
        if (!tblMap[tblName]) tblMap[tblName] = {};
        if (!tblMap[tblName][rowIdx]) tblMap[tblName][rowIdx] = {};
        tblMap[tblName][rowIdx][colName] = el.value;
    });

    Object.entries(tblMap).forEach(([tblName, rowObj]) => {
        const newRows = Object.values(rowObj);
        const newJson = JSON.stringify(newRows);
        const origRows = (OriginalDetailData.custom_tables || {})[tblName]?.rows || [];
        const origJson = JSON.stringify(origRows);
        if (newJson !== origJson) {
            _f.append(tblName, newJson);
        }
    });
    try {
        const _r = await apiFetch('/sub_user/sub_user_update.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            alert('保存成功');
            document.getElementById('UserDetailOverlay').remove();
            loadUserList(CurrentUserPage);
        } else {
            alert(_d.message || '保存失败');
        }
    } catch (_e) { alert('网络错误'); }
}

async function uploadSubAvatar() {
    const file = document.getElementById('AvatarFileInput').files[0];
    if (!file) return;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('SubUserId', CurrentSubUserId);
    _f.append('Avatar', file);
    try {
        const _r = await apiFetch('/sub_user/sub_user_avatar.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            const img = document.getElementById('DetailAvatar');
            if (img) img.src = 'sub_avatars/' + ConfigId + '/' + _d.avatar + '?t=' + Date.now();
        }
    } catch (_e) {}
}

async function deleteSubUser(userId) {
    if (!confirm('确定删除该子用户吗？此操作不可恢复！')) return;
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    const _f = new FormData();
    _f.append('UserId', localStorage.getItem('UserId'));
    _f.append('ConfigId', ConfigId);
    _f.append('Tokey', Tokey);
    _f.append('SubUserId', userId);
    try {
        const _r = await apiFetch('/sub_user/sub_user_delete.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0) {
            document.getElementById('UserDetailOverlay').remove();
            loadUserList(CurrentUserPage);
        } else { alert(_d.message); }
    } catch (_e) { alert('网络错误'); }
}
