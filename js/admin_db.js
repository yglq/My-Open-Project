
// ========== Global DB Selector ==========
async function loadGlobalDbSelector() {
    const UserId = localStorage.getItem('UserId');
    const Select = document.getElementById('GlobalDbSelect');
    if (!Select || !UserId) return;

    try {
        const _f = new FormData(); _f.append('UserId', UserId);
        const _r = await fetch('database/database_list.php', { method: 'POST', body: _f });
        const _d = await _r.json();
        if (_d.code === 0 && _d.data.length > 0) {
            Select.innerHTML = _d.data.map(item => {
                const label = item.is_owner ? '创建者' : '成员';
                return `<option value="${item.table}" data-tokey="${item.tokey}">${item.name} [${label}]</option>`;
            }).join('');

            // 恢复上次选择的
            const SavedDb = localStorage.getItem('CurrentDbId');
            if (SavedDb && _d.data.some(i => i.table === SavedDb)) {
                Select.value = SavedDb;
            }
            updateCurrentDbInfo();
        }
    } catch (_e) {}
}

function switchGlobalDb() {
    const Select = document.getElementById('GlobalDbSelect');
    if (!Select) return;
    localStorage.setItem('CurrentDbId', Select.value);
    updateCurrentDbInfo();
}

function updateCurrentDbInfo() {
    const Select = document.getElementById('GlobalDbSelect');
    if (!Select) return;
    const Opt = Select.selectedOptions[0];
    localStorage.setItem('CurrentDbId', Select.value);
    localStorage.setItem('CurrentDbTokey', Opt ? (Opt.dataset.tokey || '') : '');
}

// 获取当前选择的数据库信息
function getCurrentDbInfo() {
    return {
        configId: localStorage.getItem('CurrentDbId') || '',
        tokey: localStorage.getItem('CurrentDbTokey') || ''
    };
}





