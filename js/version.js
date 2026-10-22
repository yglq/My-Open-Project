// 站点版本检查：服务器 version.json 的版本号和本地记录不一致时，
// 清掉网站缓存刷新拿新文件，但保留 localStorage 里的登录凭证（UserId/SessionToken/Tokey）。
(function () {
    window.SITE_VERSION = '';
    fetch('/version.json?t=' + Date.now(), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (v) {
            window.SITE_VERSION = v.version || '';
            var cur = localStorage.getItem('SiteVersion');
            if (cur && cur !== v.version) {
                var finish = function () {
                    localStorage.setItem('SiteVersion', v.version);
                    location.reload();
                };
                if (window.caches && caches.keys) {
                    caches.keys()
                        .then(function (ks) {
                            return Promise.all(ks.map(function (k) { return caches.delete(k); }));
                        })
                        .then(finish).catch(finish);
                } else {
                    finish();
                }
            } else {
                localStorage.setItem('SiteVersion', v.version);
            }
        })
        .catch(function () { /* 网络失败不处理，下次再查 */ });
})();
