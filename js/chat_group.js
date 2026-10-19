
// ========== Search User ==========
function showSearchPopup() {
    closePlusMenu();

    const _old = document.getElementById('SearchOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'SearchOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:300;display:flex;align-items:flex-end;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 30px;width:100%;max-width:500px;max-height:70vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;color:#1a1a1a;">添加聊天</div>';

    // 搜索框
    _html += `
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <input type="text" id="SearchKeyword" placeholder="输入昵称或用户ID" 
                   style="flex:1;padding:12px 14px;border:2px solid #e0d5c1;border-radius:12px;font-size:14px;outline:none;background:#faf7f2;font-family:inherit;"
                   onkeydown="if(event.key==='Enter') doSearchUser()">
            <button onclick="doSearchUser()" 
                    style="padding:12px 20px;background:#c84628;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;">搜索</button>
        </div>`;

    // 结果列表
    _html += '<div id="SearchResult" style="display:flex;flex-direction:column;gap:4px;"></div>';
    _html += '<div style="text-align:center;margin-top:12px;"><button onclick="document.getElementById(\'SearchOverlay\').remove()" style="padding:10px 40px;background:#e8e3d9;border:none;border-radius:12px;font-size:14px;cursor:pointer;">取消</button></div>';
    _html += '</div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);

    setTimeout(() => document.getElementById('SearchKeyword').focus(), 200);
}

async function doSearchUser() {
    const _input = document.getElementById('SearchKeyword');
    const _result = document.getElementById('SearchResult');
    if (!_input || !_result) return;

    const _kw = _input.value.trim();
    if (!_kw) return;

    _result.innerHTML = '<div style="text-align:center;padding:20px;color:#b8a48c;">搜索中...</div>';

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('Keyword', _kw);

    try {
        const _res = await fetch('/user/user_search.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0 && _data.data.length > 0) {
            _result.innerHTML = _data.data.map(u => `
                <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;cursor:pointer;background:#faf7f2;"
                     onclick="createAndOpenChat(${u.user_id})">
                    <img src="avatars/${u.avatar}" style="width:44px;height:44px;border-radius:12px;object-fit:cover;background:#e8e3d9;" onerror="this.src='/images/icon.png'">
                    <div style="flex:1;">
                        <div style="font-size:15px;font-weight:600;color:#1a1a1a;">${u.nickname}</div>
                        <div style="font-size:11px;color:#b8a48c;">ID: ${u.user_id}</div>
                    </div>
                </div>
            `).join('');
        } else {
            _result.innerHTML = '<div style="text-align:center;padding:20px;color:#b8a48c;">未找到用户</div>';
        }
    } catch (_err) {
        _result.innerHTML = '<div style="text-align:center;padding:20px;color:#c84628;">搜索失败</div>';
    }
}

async function createAndOpenChat(targetUserId) {
    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('TargetUserId', targetUserId);

    try {
        const _res = await fetch('/chat/chat_create.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0) {
            // 关闭搜索弹窗
            const _overlay = document.getElementById('SearchOverlay');
            if (_overlay) _overlay.remove();
            // 刷新 chat_list 缓存
            delete pageCache['send'];
            // 打开聊天详情
            openChatDetail(_data.chat_id);
        } else {
            alert(_data.message || '创建失败');
        }
    } catch (_err) {
        alert('网络错误');
    }
}


// ========== Create Group ==========
async function showGroupCreatePopup() {
    const UserId = localStorage.getItem('UserId');
    if (!UserId) return;

    // 获取好友列表
    let Friends = [];
    try {
        const _formData = new FormData();
        _formData.append('UserId', UserId);
        const _res = await fetch('/user/friend_list.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) Friends = _data.data;
    } catch (_err) {
        alert('获取好友列表失败');
        return;
    }

    if (Friends.length < 2) {
        alert('至少需要 2 位好友才能创建群聊');
        return;
    }

    const _old = document.getElementById('GroupCreateOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'GroupCreateOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:300;display:flex;align-items:flex-end;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 30px;width:100%;max-width:500px;max-height:80vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">创建群聊</div>';

    // 群名
    _html += '<input type="text" id="GroupNameInput" placeholder="输入群聊名称" style="width:100%;padding:12px 14px;border:2px solid #e0d5c1;border-radius:12px;font-size:14px;outline:none;background:#faf7f2;font-family:inherit;margin-bottom:12px;box-sizing:border-box;">';

    // 好友列表
    _html += '<div style="font-size:13px;color:#8b7355;margin-bottom:8px;">选择好友（至少 2 人）</div>';
    _html += '<div id="FriendList" style="max-height:40vh;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">';

    Friends.forEach(f => {
        _html += `
            <label style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;background:#faf7f2;cursor:pointer;">
                <input type="checkbox" value="${f.user_id}" class="friend-check" onchange="updateSelectedCount()">
                <img src="avatars/${f.avatar}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;background:#e8e3d9;" onerror="this.src='/images/icon.png'">
                <span style="font-size:14px;font-weight:500;">${f.nickname}</span>
            </label>`;
    });

    _html += '</div>';
    _html += '<div id="SelectedCount" style="font-size:12px;color:#b8a48c;margin-top:6px;">已选 0 人</div>';

    // 按钮
    _html += `
        <div style="display:flex;gap:10px;margin-top:14px;">
            <button onclick="document.getElementById('GroupCreateOverlay').remove()" style="flex:1;padding:12px;background:#e8e3d9;border:none;border-radius:12px;font-size:14px;cursor:pointer;">取消</button>
            <button onclick="createGroup()" style="flex:1;padding:12px;background:#c84628;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;">创建</button>
        </div>`;

    _html += '</div>';
    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);
}

function updateSelectedCount() {
    const _checks = document.querySelectorAll('.friend-check:checked');
    const _label = document.getElementById('SelectedCount');
    if (_label) _label.textContent = '已选 ' + _checks.length + ' 人';
}

async function createGroup() {
    const GroupName = document.getElementById('GroupNameInput').value.trim();
    if (!GroupName) { alert('请输入群聊名称'); return; }

    const _checks = document.querySelectorAll('.friend-check:checked');
    const _selected = [];
    _checks.forEach(c => _selected.push(parseInt(c.value)));

    if (_selected.length < 2) { alert('至少选择 2 位好友'); return; }

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('GroupName', GroupName);
    _formData.append('Members', JSON.stringify(_selected));

    try {
        const _res = await fetch('/group/group_create.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            document.getElementById('GroupCreateOverlay').remove();
            delete pageCache['send'];
            openChatDetail(_data.chat_id);
        } else {
            alert(_data.message || '创建失败');
        }
    } catch (_err) {
        alert('网络错误');
    }
}
// ========== Group Detail ==========
async function openGroupDetail() {
    if (!CurrentChatId) return;

    const UserId = localStorage.getItem('UserId');
    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ChatId', CurrentChatId);

    try {
        const _res = await fetch('/group/group_members.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            showGroupDetailPopup(_data);
        }
    } catch (_err) { alert('加载失败'); }
}

function showGroupDetailPopup(data) {
    const _old = document.getElementById('GroupDetailOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'GroupDetailOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:310;display:flex;align-items:flex-end;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 30px;width:100%;max-width:500px;max-height:85vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">群聊详情</div>';

    // 群头像 + 名字
    _html += `
        <div style="display:flex;align-items:center;gap:14px;padding:16px;background:linear-gradient(135deg,#fafaf8,#f2efe9);border-radius:18px;margin-bottom:16px;">
            <img src="group_avatars/${data.group_avatar}" style="width:60px;height:60px;border-radius:16px;object-fit:cover;background:#e8e3d9;" onerror="this.src='/images/icon.png'">
            <div>
                <div style="font-size:18px;font-weight:700;">${data.group_name}</div>
                <div style="font-size:13px;color:#8b7355;">${data.member_count} / ${data.max_members} 人</div>
            </div>
        </div>`;

    // 成员网格
    _html += '<div style="font-size:14px;font-weight:600;margin-bottom:8px;">群成员</div>';
    _html += '<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;max-height:240px;overflow-y:auto;margin-bottom:16px;">';
    data.members.forEach(m => {
        _html += `
            <div style="text-align:center;cursor:pointer;padding:6px;" onclick="showMemberDetail(${m.user_id}, '${m.name}', '${m.avatar}', '${(m.bio || '').replace(/'/g, "\\'")}', ${m.is_owner}, ${data.is_owner}, '${CurrentChatId}')">
                <img src="avatars/${m.avatar}" style="width:44px;height:44px;border-radius:12px;object-fit:cover;background:#e8e3d9;margin:0 auto;" onerror="this.src='/images/icon.png'">
                <div style="font-size:11px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${m.name}${m.is_owner ? ' 👑' : ''}</div>
            </div>`;
    });
    _html += '</div>';

    // 邀请好友
    _html += `<button onclick="showGroupInvitePopup()" style="width:100%;padding:12px;background:#faf7f2;border:1px solid #e0d5c1;border-radius:12px;font-size:14px;cursor:pointer;margin-bottom:8px;">+ 邀请好友进群</button>`;

    // 群主专属
    if (data.is_owner) {
        _html += `<button onclick="clearGroupChat()" style="width:100%;padding:12px;background:#fff;border:1px solid #e0d5c1;border-radius:12px;font-size:14px;color:#c84628;cursor:pointer;margin-bottom:8px;">清空聊天记录</button>`;
    }

    // 退出/解散
    _html += `<button onclick="leaveGroup()" style="width:100%;padding:12px;background:#fff;border:1px solid #fecaca;border-radius:12px;font-size:14px;color:#c84628;cursor:pointer;">${data.is_owner ? '解散群聊' : '退出群聊'}</button>`;
    _html += '</div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);

    // 缓存非成员好友供邀请使用
    window._groupNonMembers = data.non_members;
    window._groupOwnerId = data.owner_id;
    window._groupIsOwner = data.is_owner;
}

// 成员详情弹窗
function showMemberDetail(userId, name, avatar, bio, isOwner, isMeOwner, chatId) {
    const _old = document.getElementById('MemberDetailOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'MemberDetailOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:320;display:flex;align-items:center;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px;padding:24px;width:90%;max-width:340px;">';
    _html += `<div style="text-align:center;">
        <img src="avatars/${avatar}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;background:#e8e3d9;margin-bottom:12px;" onerror="this.src='/images/icon.png'">
        <div style="font-size:18px;font-weight:700;">${name}${isOwner ? ' 👑' : ''}</div>
        <div style="font-size:13px;color:#8b7355;margin:6px 0;">${bio || '这个人很懒，什么都没写'}</div>
        <div style="font-size:12px;color:#b8a48c;">ID: ${userId}</div>`;

    // 踢人按钮（群主对非群主）
    if (isMeOwner && !isOwner && userId != localStorage.getItem('UserId')) {
        _html += `<button onclick="kickMember(${userId})" style="margin-top:16px;padding:10px 30px;background:#c84628;color:#fff;border:none;border-radius:10px;font-size:14px;cursor:pointer;">踢出群聊</button>`;
    }

    _html += '</div></div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);
}

// 邀请进群弹窗
function showGroupInvitePopup() {
    const Friends = window._groupNonMembers || [];
    if (Friends.length === 0) { alert('没有可邀请的好友'); return; }

    const _old = document.getElementById('GroupInviteOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'GroupInviteOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:330;display:flex;align-items:flex-end;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 30px;width:100%;max-width:500px;max-height:70vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">邀请好友进群</div>';

    Friends.forEach(f => {
        _html += `
            <label style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;background:#faf7f2;cursor:pointer;margin-bottom:4px;">
                <input type="checkbox" value="${f.user_id}" class="invite-check">
                <img src="avatars/${f.avatar}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;background:#e8e3d9;" onerror="this.src='/images/icon.png'">
                <span style="font-size:14px;font-weight:500;">${f.nickname}</span>
            </label>`;
    });

    _html += `
        <div style="display:flex;gap:10px;margin-top:14px;">
            <button onclick="document.getElementById('GroupInviteOverlay').remove()" style="flex:1;padding:12px;background:#e8e3d9;border:none;border-radius:12px;font-size:14px;cursor:pointer;">取消</button>
            <button onclick="inviteToGroup()" style="flex:1;padding:12px;background:#c84628;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;">邀请</button>
        </div>`;
    _html += '</div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);
}

async function inviteToGroup() {
    const _checks = document.querySelectorAll('.invite-check:checked');
    const _selected = [];
    _checks.forEach(c => _selected.push(parseInt(c.value)));
    if (_selected.length === 0) { alert('请选择好友'); return; }

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);
    _formData.append('Members', JSON.stringify(_selected));

    try {
        const _res = await fetch('/group/group_invite.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            document.getElementById('GroupInviteOverlay').remove();
            document.getElementById('GroupDetailOverlay').remove();
            alert('邀请成功');
            openGroupDetail();
        } else alert(_data.message);
    } catch (_err) { alert('网络错误'); }
}

async function kickMember(targetUserId) {
    if (!confirm('确定踢出该成员吗？')) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);
    _formData.append('TargetUserId', targetUserId);

    try {
        const _res = await fetch('/group/group_kick.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            document.getElementById('MemberDetailOverlay').remove();
            document.getElementById('GroupDetailOverlay').remove();
            if (_data.become_private) {
                delete pageCache['send'];
                openChatDetail(CurrentChatId);
            } else {
                openGroupDetail();
            }
        } else alert(_data.message);
    } catch (_err) { alert('网络错误'); }
}

async function clearGroupChat() {
    if (!confirm('确定清空所有聊天记录吗？此操作不可恢复！')) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);

    try {
        const _res = await fetch('/group/group_clear.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            alert('已清空');
            document.getElementById('GroupDetailOverlay').remove();
            CurrentOffset = 0;
            openChatDetail(CurrentChatId);
        } else alert(_data.message);
    } catch (_err) { alert('网络错误'); }
}

async function leaveGroup() {
    const isOwner = window._groupIsOwner;
    if (!confirm(isOwner ? '确定解散该群聊吗？此操作不可恢复！' : '确定退出该群聊吗？')) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);

    try {
        const _res = await fetch('/group/group_leave.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            document.getElementById('GroupDetailOverlay').remove();
            delete pageCache['send'];
            if (_data.become_private) {
                openChatDetail(CurrentChatId);
            } else {
                loadPage('send').then(() => loadMsgList());
            }
        } else alert(_data.message);
    } catch (_err) { alert('网络错误'); }
}
// 在 index.html 中添加函数
function openDatabaseManager() {
    delete subPageCache['My_Database.html'];
    loadSubPage('My_Database.html').then(() => {
        setTimeout(() => loadDbList(), 200);
    });
}
// 加载数据库列表
async function loadDbList() {
    const UserId = localStorage.getItem('UserId');
    const el = document.getElementById('DbList');
    if (!el) return;
    try {
        const f = new FormData(); f.append('UserId', UserId);
        const r = await fetch('/database/database_list.php', {method:'POST', body:f});
        const d = await r.json();
        if (d.code===0 && d.data.length>0) {
            el.innerHTML = d.data.map(item => {
                const roleText = item.is_owner ? '创建者' : '成员';
                const nameColor = item.is_owner ? (item.my_vip==='svip'?'#dc2626':item.my_vip==='vip'?'#d4a017':'#1a1a1a') : '#1a1a1a';
                const ownerColor = item.owner_vip==='svip'?'#dc2626':item.owner_vip==='vip'?'#d4a017':'#8b7355';
                const displayName = item.is_member && item.owner_name ? `${item.name}--<span style="color:${ownerColor}">${item.owner_name}</span>` : item.name;
                return `<div class="db-item">
                    <div class="db-icon">${item.is_owner?'📁':'🏢'}</div>
                    <div class="db-info">
                        <div class="db-name-line"><span class="db-name" style="color:${nameColor}">${displayName}</span><span style="font-size:11px;color:#8b7355;">${roleText}</span></div>
                        <div class="db-detail">ID: ${item.table} | 密钥: ${item.tokey}</div>
                    </div>
                    <div class="db-actions">
                        ${item.is_member ? `<button class="btn-remove" onclick="removeDb('${item.table}')">移除</button>` : ''}
                        ${item.is_owner ? `<button class="btn-tokey" onclick="showTokeyPopup('${item.table}','${item.tokey}')">修改密钥</button>` : ''}
                   ${item.is_owner ? `<button class="btn-tokey" onclick="showMembersPopup('${item.table}')">详情</button>` : ''}
                    </div>
                </div>`;
            }).join('');
        } else el.innerHTML = '<div style="text-align:center;color:#b8a48c;padding:20px;">没有数据库</div>';
    } catch(e) { el.innerHTML = '加载失败'; }
}

// 加入弹窗
function showJoinPopup() {
    const ov = document.createElement('div'); ov.className='overlay'; ov.id='JoinOverlay';
    ov.innerHTML = `<div class="popup">
        <h3>加入数据库</h3>
        <input id="JoinId" placeholder="数据库ID">
        <input id="JoinTokey" placeholder="Tokey">
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="document.getElementById('JoinOverlay').remove()" style="background:#e8e3d9;">取消</button>
            <button onclick="joinDb()" style="background:#c84628;color:#fff;">加入</button>
        </div>
    </div>`;
    document.body.appendChild(ov);
}
async function joinDb() {
    const id = document.getElementById('JoinId').value.trim();
    const key = document.getElementById('JoinTokey').value.trim();
    if(!id||!key) return alert('填写完整');
    const f = new FormData(); f.append('UserId',localStorage.getItem('UserId')); f.append('ConfigId',id); f.append('Tokey',key);
    try {
        const r = await fetch('/database/database_join.php',{method:'POST',body:f}); const d = await r.json();
        if(d.code===0){ document.getElementById('JoinOverlay').remove(); loadDbList(); }
        else alert(d.message);
    } catch(e) { alert('网络错误'); }
}

// 创建弹窗
function showCreatePopup() {
    const ov = document.createElement('div'); ov.className='overlay'; ov.id='CreateOverlay';
    ov.innerHTML = `<div class="popup">
        <h3>创建数据库</h3>
        <input id="DbName" placeholder="数据库名称">
        <div style="display:flex;align-items:center;gap:8px;">
            <input id="DbTokey" placeholder="Tokey (可选)" style="flex:1;">
            <button class="dice-btn" onclick="randomTokey()">🎲</button>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px;">
            <button onclick="document.getElementById('CreateOverlay').remove()" style="background:#e8e3d9;">取消</button>
            <button onclick="createDb()" style="background:#c84628;color:#fff;">创建</button>
        </div>
    </div>`;
    document.body.appendChild(ov);
}
function randomTokey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let t = ''; for(let i=0;i<21;i++) t += chars[Math.floor(Math.random()*chars.length)];
    document.getElementById('DbTokey').value = t;
}
async function createDb() {
    const name = document.getElementById('DbName').value.trim();
    const tokey = document.getElementById('DbTokey').value.trim();
    if(!name) return alert('输入名称');
    const f = new FormData(); f.append('UserId',localStorage.getItem('UserId')); f.append('DbName',name); f.append('Tokey',tokey);
    try {
        const r = await fetch('/database/database_create.php',{method:'POST',body:f}); const d = await r.json();
        if(d.code===0){ document.getElementById('CreateOverlay').remove(); loadDbList(); }
        else alert(d.message);
    } catch(e) { alert('网络错误'); }
}

// 移除
async function removeDb(configId) {
    if(!confirm('确定退出该数据库吗？')) return;
    const f = new FormData(); f.append('UserId',localStorage.getItem('UserId')); f.append('ConfigId',configId);
    const r = await fetch('/database/database_remove.php',{method:'POST',body:f}); const d = await r.json();
    if(d.code===0) loadDbList(); else alert(d.message);
}

// 修改密钥弹窗
function showTokeyPopup(configId, oldTokey) {
    const ov = document.createElement('div'); ov.className='overlay'; ov.id='TokeyOverlay';
    ov.innerHTML = `<div class="popup">
        <h3>修改密钥</h3>
        <input id="NewTokey" placeholder="新Tokey" value="${oldTokey}">
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="document.getElementById('TokeyOverlay').remove()" style="background:#e8e3d9;">取消</button>
            <button onclick="updateTokey('${configId}')" style="background:#c84628;color:#fff;">保存</button>
        </div>
    </div>`;
    document.body.appendChild(ov);
}
async function updateTokey(configId) {
    const newKey = document.getElementById('NewTokey').value.trim();
    if(!newKey) return alert('输入新密钥');
    const f = new FormData(); f.append('UserId',localStorage.getItem('UserId')); f.append('ConfigId',configId); f.append('NewTokey',newKey);
    const r = await fetch('/database/database_update_tokey.php',{method:'POST',body:f}); const d = await r.json();
    if(d.code===0){ document.getElementById('TokeyOverlay').remove(); loadDbList(); }
    else alert(d.message);
}


function showMembersPopup(configId) {
    const UserId = localStorage.getItem('UserId');
    // 从当前列表数据里找到对应的 members
    // 需要先重新请求数据，或者从缓存的 _lastDbData 中取
    const f = new FormData(); f.append('UserId', UserId);
    fetch('/database/database_list.php', {method:'POST', body:f}).then(r=>r.json()).then(d=>{
        if(d.code===0){
            const item = d.data.find(i=>i.table===configId);
            if(!item || !item.members) return;
            const members = item.members;
            const ov = document.createElement('div'); ov.className='overlay'; ov.id='MembersOverlay';
            let html = '<div class="popup"><h3>成员列表</h3>';
            members.forEach(m => {
                html += `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;">
                    <img src="avatars/${m.avatar}" style="width:36px;height:36px;border-radius:10px;" onerror="this.src='/images/icon.png'">
                    <span style="flex:1;">${m.name} ${m.role==='owner'?'👑':''}</span>
                    ${m.user_id!=UserId && item.is_owner ? `<button onclick="removeMember('${configId}',${m.user_id})" style="color:#c84628;border:1px solid #fecaca;border-radius:8px;padding:4px 10px;background:#fff;cursor:pointer;">删除</button>` : ''}
                </div>`;
            });
            html += '<button onclick="document.getElementById(\'MembersOverlay\').remove()" style="margin-top:10px;background:#e8e3d9;border:none;border-radius:10px;padding:8px 20px;cursor:pointer;">关闭</button></div>';
            ov.innerHTML = html;
            document.body.appendChild(ov);
        }
    });
}

async function removeMember(configId, targetUserId) {
    if(!confirm('确定移除该成员吗？')) return;
    const f = new FormData(); f.append('UserId', localStorage.getItem('UserId')); f.append('ConfigId', configId); f.append('TargetUserId', targetUserId);
    const r = await fetch('/database/database_remove_member.php', {method:'POST', body:f});
    const d = await r.json();
    if(d.code===0){ document.getElementById('MembersOverlay').remove(); loadDbList(); }
    else alert(d.message);
}








