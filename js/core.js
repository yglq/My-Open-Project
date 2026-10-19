    // ========== Login Check ==========
 (async function checkLogin() {
    const UserId = localStorage.getItem('UserId');
    const Password = localStorage.getItem('Password');

    if (!UserId || !Password) {
        localStorage.clear();
        window.location.href = '/pages/Login.html';
        return;
    }

    try {
        const _formData = new FormData();
        _formData.append('UserId', UserId);
        _formData.append('Password', Password);

        const _res = await fetch('/user/check_login.php', {
            method: 'POST',
            body: _formData
        });
        const _data = await _res.json();

        if (_data.code !== 0) {
            localStorage.clear();
            window.location.href = '/pages/Login.html';
        }
    } catch (_err) {
        // 网络错误不踢，允许离线使用
    }
})();

    // ========== Page Loader (Main Tabs) ==========
    const pageCache = {};
    const pageContainer = document.getElementById('pageContainer');
    const pageFileMap = {
        'home': 'Main_Home.html',
        'forum': 'Main_Forum.html',
        'send': 'Main_Send.html',
        'my': 'Main_My.html'
    };

    async function loadPage(pageName) {
        if (pageCache[pageName]) {
            pageContainer.innerHTML = pageCache[pageName];
            return;
        }

        pageContainer.classList.add('loading');
        pageContainer.innerHTML = '<div class="loading-spinner"></div>';

        try {
            const response = await fetch('pages/' + pageFileMap[pageName]);
            if (!response.ok) throw new Error('Page not found');
            const html = await response.text();
            pageCache[pageName] = html;
            pageContainer.innerHTML = html;
        } catch (err) {
            pageContainer.innerHTML = `
                <div style="text-align:center;padding:40px;color:#999;">
                    <p style="font-size:40px;margin-bottom:12px;">📄</p>
                    <p>Content loading...</p>
                </div>`;
        } finally {
            pageContainer.classList.remove('loading');
        }
    }

    // ========== Sub Page Loader ==========
    const subPageCache = {};
    
    async function loadSubPage(pageName) {
    if (subPageCache[pageName]) {
        pageContainer.innerHTML = subPageCache[pageName];
        triggerPageData(pageName);
        return;
    }

    pageContainer.classList.add('loading');
    pageContainer.innerHTML = '<div class="loading-spinner"></div>';

    try {
        const response = await fetch('pages/' + pageName);
        if (!response.ok) throw new Error('Page not found');
        const html = await response.text();
        subPageCache[pageName] = html;
        pageContainer.innerHTML = html;
        triggerPageData(pageName);
    } catch (err) {
        pageContainer.innerHTML = `<div style="text-align:center;padding:40px;color:#999;"><p style="font-size:40px;margin-bottom:12px;">📄</p><p>页面加载失败</p></div>`;
    } finally {
        pageContainer.classList.remove('loading');
    }
}

function triggerPageData(pageName) {
    if (pageName === 'Home_Admin_Users.html') loadUserList(1);
    if (pageName === 'Home_Admin_Config.html') {
        const cid = localStorage.getItem('CurrentDbId') || '';
        if (cid) loadConfig(cid);
    }
    if (pageName === 'Home_Admin_Notice.html') {
        const cid = localStorage.getItem('CurrentDbId') || '';
        if (cid) loadNoticeConfig(cid);
    }
    if (pageName === 'Home_Admin_Custom.html') loadCustomConfig();
if (pageName === 'Home_Admin_Custom_Ranking.html') { loadVarRanking(); loadTableRanking(); }
    if (pageName === 'Home_Admin.html') loadGlobalDbSelector();
    if (pageName === 'Home_Admin_CardKey.html') { loadCustomConfig(); setupCardKeyEvents(); }
    if (pageName === 'My_Database.html') loadDbList();
}
    
    

    // ========== Tab Switching ==========
    function switchTab(pageName, el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');

        if (pageCache[pageName]) {
            el.classList.add('cached');
        }
loadPage(pageName).then(() => {
    if (pageName === 'my') {
        loadMyData();
    }
    if (pageName === 'send') {
        loadMsgList();
    }
});

    }

    // ========== Banner Dots ==========
    const dots = document.querySelectorAll('.banner-dot');
    let currentDot = 0;

    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            dots.forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            currentDot = parseInt(this.dataset.index);
        });
    });

    setInterval(() => {
        currentDot = (currentDot + 1) % dots.length;
        dots.forEach(d => d.classList.remove('active'));
        dots[currentDot].classList.add('active');
    }, 3500);
