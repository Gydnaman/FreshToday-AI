/**
 * GreenBite i18n 客户端加载器
 * @file docs/i18n/i18n-loader.js
 * @description 前端 HTML 原型的多语言切换：
 *   - 从 docs/i18n/locales/{locale}.json 加载字典
 *   - 扫描 [data-i18n] / [data-i18n-html] / [data-i18n-placeholder] / [data-i18n-title] / [data-i18n-aria] 元素自动应用翻译
 *   - 提供下拉切换器，localStorage 持久化，URL `?lang=` 同步
 *
 * 数据属性约定：
 *   data-i18n="key.path"             -> 替换 textContent
 *   data-i18n-html="key.path"        -> 替换 innerHTML（用于含标签的字符串）
 *   data-i18n-placeholder="key.path" -> 替换 placeholder
 *   data-i18n-title="key.path"       -> 替换 title 属性
 *   data-i18n-aria="key.path"        -> 替换 aria-label
 *
 * 占位符语法：t('cart.freeDeliveryProgress', 72) 会把 {0} 替换为 72。
 */
(function (global) {
  'use strict';

  var SUPPORTED = ['zh-HK', 'en', 'zh-CN'];
  var LS_KEY = 'gb_locale';
  var DEFAULT_LOCALE = 'zh-HK';

  /**
   * 工具：从 URL/localStorage/Accept-Language 三级回退获取初始语言
   */
  function detectInitialLocale() {
    try {
      var url = new URL(window.location.href);
      var q = url.searchParams.get('lang');
      if (q && SUPPORTED.indexOf(q) !== -1) return q;
    } catch (e) { /* ignore */ }

    try {
      var ls = localStorage.getItem(LS_KEY);
      if (ls && SUPPORTED.indexOf(ls) !== -1) return ls;
    } catch (e) { /* ignore */ }

    var nav = (navigator.language || navigator.userLanguage || '').toLowerCase();
    if (nav.indexOf('zh-tw') !== -1 || nav.indexOf('zh-hk') !== -1 || nav.indexOf('zh-mo') !== -1) return 'zh-HK';
    if (nav.indexOf('zh-cn') !== -1 || nav === 'zh') return 'zh-CN';
    if (nav.indexOf('en') === 0) return 'en';

    return DEFAULT_LOCALE;
  }

  /**
   * 取嵌套属性："home.title" -> dict.home.title
   */
  function getPath(obj, path) {
    if (!obj || !path) return null;
    var parts = path.split('.');
    var cur = obj;
    for (var i = 0; i < parts.length; i++) {
      if (cur == null) return null;
      cur = cur[parts[i]];
    }
    return cur;
  }

  /**
   * 占位符替换：把 {0} {1} 替换为 args
   */
  function interpolate(str, args) {
    if (!str || !args || args.length === 0) return str;
    return String(str).replace(/\{(\d+)\}/g, function (_, idx) {
      var n = parseInt(idx, 10);
      return (n >= 0 && n < args.length) ? args[n] : '';
    });
  }

  var I18N = {
    locales: SUPPORTED,
    labels: {
      'zh-HK': { name: '繁體中文', flag: '🇭🇰' },
      'en':    { name: 'English',  flag: '🇬🇧' },
      'zh-CN': { name: '简体中文', flag: '🇨🇳' }
    },
    current: detectInitialLocale(),
    dict: {},
    ready: false,

    /**
     * 加载某语言字典到内存
     */
    load: function (locale) {
      if (SUPPORTED.indexOf(locale) === -1) locale = DEFAULT_LOCALE;
      var url = 'i18n/locales/' + locale + '.json';
      return fetch(url, { credentials: 'omit', cache: 'no-cache' })
        .then(function (r) {
          if (!r.ok) throw new Error('i18n fetch failed: ' + url);
          return r.json();
        })
        .then(function (json) {
          I18N.dict = json;
          I18N.current = locale;
          I18N.ready = true;
          return json;
        });
    },

    /**
     * 取翻译
     */
    t: function (key) {
      var args = Array.prototype.slice.call(arguments, 1);
      var val = getPath(I18N.dict, key);
      if (val == null) {
        // fallback：找不到 key 时返回 key 自身以便排查
        return key;
      }
      return interpolate(val, args);
    },

    /**
     * 扫描当前 DOM，应用所有 i18n 属性
     */
    apply: function () {
      if (!I18N.ready) return;

      // textContent
      var nodes = document.querySelectorAll('[data-i18n]');
      for (var i = 0; i < nodes.length; i++) {
        var key = nodes[i].getAttribute('data-i18n');
        var txt = I18N.t(key);
        if (txt) nodes[i].textContent = txt;
      }

      // innerHTML（含 <strong> 等）
      var htmlNodes = document.querySelectorAll('[data-i18n-html]');
      for (var j = 0; j < htmlNodes.length; j++) {
        var hkey = htmlNodes[j].getAttribute('data-i18n-html');
        var hval = I18N.t(hkey);
        if (hval) htmlNodes[j].innerHTML = hval;
      }

      // placeholder
      var phNodes = document.querySelectorAll('[data-i18n-placeholder]');
      for (var k = 0; k < phNodes.length; k++) {
        var pkey = phNodes[k].getAttribute('data-i18n-placeholder');
        var pval = I18N.t(pkey);
        if (pval) phNodes[k].setAttribute('placeholder', pval);
      }

      // title
      var tiNodes = document.querySelectorAll('[data-i18n-title]');
      for (var l = 0; l < tiNodes.length; l++) {
        var tkey = tiNodes[l].getAttribute('data-i18n-title');
        var tval = I18N.t(tkey);
        if (tval) tiNodes[l].setAttribute('title', tval);
      }

      // aria-label
      var arNodes = document.querySelectorAll('[data-i18n-aria]');
      for (var m = 0; m < arNodes.length; m++) {
        var akey = arNodes[m].getAttribute('data-i18n-aria');
        var aval = I18N.t(akey);
        if (aval) arNodes[m].setAttribute('aria-label', aval);
      }

      // <html lang> 同步
      document.documentElement.setAttribute('lang', I18N.current);

      // 触发自定义事件，便于页面内其他 JS 二次渲染（如 dashboard 动态 AI menu）
      try {
        document.dispatchEvent(new CustomEvent('i18n:applied', { detail: { locale: I18N.current } }));
      } catch (e) { /* IE11 兼容忽略 */ }
    },

    /**
     * 切换语言：写 localStorage + URL（避免 SEO 重复） + 不刷新的 apply
     */
    switch: function (locale) {
      if (SUPPORTED.indexOf(locale) === -1) locale = DEFAULT_LOCALE;
      if (locale === I18N.current && I18N.ready) {
        I18N.apply();
        return Promise.resolve();
      }
      try { localStorage.setItem(LS_KEY, locale); } catch (e) { /* ignore */ }
      return I18N.load(locale).then(function () {
        // 同步 URL（不强制刷新，便于 SPA 风格切换）
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('lang', locale);
          window.history.replaceState({}, '', url.toString());
        } catch (e) { /* ignore */ }
        I18N.apply();
      });
    },

    /**
     * 在 <span data-i18n-mount="lang-switcher"> 处挂载切换器
     * 调用方只需在 navbar 合适位置放 <span data-i18n-mount="lang-switcher"></span>
     */
    renderSwitcher: function () {
      var mounts = document.querySelectorAll('[data-i18n-mount="lang-switcher"]');
      if (!mounts.length) return;

      var html = '<div class="lang-switcher relative" id="gb-lang-switcher">'
        + '<button type="button" id="gb-lang-btn" class="flex items-center gap-1 text-sm text-gray-600 hover:text-green-600 px-2 py-1 rounded transition" aria-haspopup="true" aria-expanded="false">'
        +   '<span aria-hidden="true">🌐</span>'
        +   '<span id="gb-lang-current" class="font-medium"></span>'
        +   '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 8l5 5 5-5H5z"/></svg>'
        + '</button>'
        + '<div id="gb-lang-menu" class="hidden absolute right-0 mt-1 bg-white border border-gray-100 rounded-lg shadow-lg z-50 min-w-[160px] py-1">'
        +   SUPPORTED.map(function (code) {
              return '<a href="#" data-lang="' + code + '" class="gb-lang-item block px-4 py-2 text-sm hover:bg-green-50 text-gray-700">'
                +   '<span class="mr-2">' + I18N.labels[code].flag + '</span>' + I18N.labels[code].name
                + '</a>';
            }).join('')
        + '</div>'
        + '</div>';

      mounts.forEach(function (m) { m.innerHTML = html; });

      var btn = document.getElementById('gb-lang-btn');
      var menu = document.getElementById('gb-lang-menu');
      var currentLabel = document.getElementById('gb-lang-current');

      if (currentLabel) {
        currentLabel.textContent = I18N.labels[I18N.current].name;
      }

      if (btn && menu) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var hidden = menu.classList.toggle('hidden');
          btn.setAttribute('aria-expanded', String(!hidden));
        });
        document.addEventListener('click', function (e) {
          if (!menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
          }
        });
      }

      document.querySelectorAll('.gb-lang-item').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var code = a.getAttribute('data-lang');
          I18N.switch(code).then(function () {
            if (currentLabel) currentLabel.textContent = I18N.labels[I18N.current].name;
            // 高亮当前
            document.querySelectorAll('.gb-lang-item').forEach(function (i) { i.classList.remove('text-green-700', 'font-bold'); });
            a.classList.add('text-green-700', 'font-bold');
            // 关闭菜单
            if (menu) menu.classList.add('hidden');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          });
        });
      });

      // 高亮当前
      var cur = document.querySelector('.gb-lang-item[data-lang="' + I18N.current + '"]');
      if (cur) cur.classList.add('text-green-700', 'font-bold');
    }
  };

  // 暴露到全局
  global.I18N = I18N;

  // DOM 就绪后自动初始化
  document.addEventListener('DOMContentLoaded', function () {
    I18N.load(I18N.current).then(function () {
      I18N.renderSwitcher();
      I18N.apply();
    }).catch(function (err) {
      // 加载失败时静默回退（控制台可见）
      // eslint-disable-next-line no-console
      console.warn('[i18n]', err && err.message);
      I18N.renderSwitcher();
    });
  });
})(window);
