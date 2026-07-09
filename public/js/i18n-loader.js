/**
 * GreenBite Client-Side i18n Loader (ES6+)
 * @file public/js/i18n-loader.js
 * @version 1.0.0 (Sprint 1 Day 2)
 * @description Frontend micro-SPA i18n helper used by static HTML prototypes and
 *              any pre-Vue/Blade page that needs on-demand translations.
 *
 * Public API (exposed on window):
 *   - window.i18n(key, locale?, ...args)  -> string
 *       Nested key resolution: "survey.q1.title" -> dict.survey.q1.title
 *       Array index:          "orders.demoOrders.0.id"
 *       Fallback chain:       arg locale -> current locale -> 'en' -> key
 *       Placeholders:         t('survey.questionOf', null, 1) -> "Question 1 of 6"
 *   - window.i18n.load(locale)  -> Promise<object>   (fetch + cache)
 *   - window.i18n.setLocale(locale) -> Promise<void> (switch + persist)
 *   - window.i18n.locale        -> currently active locale code
 *   - window.i18n.supported    -> ['zh-HK', 'en', 'zh-CN']
 *
 * Locale files are fetched from `i18n/locales/{locale}.json` (relative to the
 * page). For the docs/ static prototypes the path is `../docs/i18n/locales/`;
 * for the Laravel public/ assets the path is `i18n/locales/`. We auto-detect
 * based on `document.currentScript.src`.
 */

const LOCALE_STORAGE_KEY = 'gb_locale';
const DEFAULT_LOCALE = 'zh';
const FALLBACK_LOCALE = 'en';
const SUPPORTED_LOCALES = ['zh', 'en', 'zhhk'];

/** In-memory cache: { [locale]: parsedDict } */
const cache = new Map();

/** Current active locale (mutable). */
let currentLocale = detectInitialLocale();

/**
 * Detect initial locale from URL > localStorage > navigator.
 * @returns {string} one of SUPPORTED_LOCALES
 */
function detectInitialLocale() {
  try {
    const url = new URL(window.location.href);
    const q = url.searchParams.get('lang');
    if (q && SUPPORTED_LOCALES.includes(q)) return q;
  } catch (_) { /* ignore */ }

  try {
    const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY);
    if (stored && SUPPORTED_LOCALES.includes(stored)) return stored;
  } catch (_) { /* ignore */ }

  const nav = (navigator.language || navigator.userLanguage || '').toLowerCase();
  if (nav.startsWith('zh-tw') || nav.startsWith('zh-hk') || nav.startsWith('zh-mo')) return 'zhhk';
  if (nav.startsWith('zh-cn') || nav.startsWith('zh-sg') || nav.startsWith('zh')) return 'zh';
  if (nav.startsWith('en')) return 'en';
  return DEFAULT_LOCALE;
}

/**
 * Resolve the base URL of the locale folder based on where this script
 * was loaded from. Falls back to a relative `i18n/locales/` path.
 * @returns {string} base URL ending with a slash
 */
function resolveLocaleBaseUrl() {
  try {
    const script = document.currentScript || (() => {
      const s = document.getElementsByTagName('script');
      return s[s.length - 1];
    })();
    if (script && script.src) {
      const u = new URL(script.src);
      // strip the trailing "i18n-loader.js" segment -> go up one level
      const dir = u.pathname.replace(/[^/]*$/, '');
      return u.origin + dir;
    }
  } catch (_) { /* ignore */ }
  // Fallback: relative to page root
  return 'i18n/locales/';
}

/**
 * Walk a nested object/array via a dot-separated path.
 * Supports numeric segments (e.g. "demoOrders.0.id") to address arrays.
 * @param {object|Array} obj source dictionary
 * @param {string} path dot-separated key path
 * @returns {*|undefined} resolved value or undefined
 */
function resolvePath(obj, path) {
  if (obj == null || !path) return undefined;
  const parts = String(path).split('.');
  let cur = obj;
  for (const seg of parts) {
    if (cur == null) return undefined;
    cur = cur[seg];
  }
  return cur;
}

/**
 * Replace {0}, {1} ... placeholders in a string with positional args.
 * @param {string} str source string
 * @param {Array} args positional values
 * @returns {string}
 */
function interpolate(str, args) {
  if (typeof str !== 'string' || !args || args.length === 0) return str;
  return str.replace(/\{(\d+)\}/g, (_, idx) => {
    const n = parseInt(idx, 10);
    return (n >= 0 && n < args.length) ? String(args[n]) : '';
  });
}

/**
 * Fetch and cache a locale dictionary. Rejects on network/HTTP error.
 * @param {string} locale locale code
 * @returns {Promise<object>}
 */
async function fetchLocale(locale) {
  if (cache.has(locale)) return cache.get(locale);
  const base = resolveLocaleBaseUrl();
  const url = `${base}${locale}.json`;
  const res = await fetch(url, { credentials: 'omit', cache: 'no-cache' });
  if (!res.ok) throw new Error(`i18n fetch failed (${res.status}): ${url}`);
  const json = await res.json();
  cache.set(locale, json);
  return json;
}

/**
 * Public t() function. Looks up `key` in the requested locale (or the
 * current one), then falls back to FALLBACK_LOCALE, then returns the key.
 * @param {string} key dot-separated key path (supports numeric array indexes)
 * @param {string} [locale] explicit locale override; otherwise uses current
 * @param {...*} args positional values for {0} {1} placeholders
 * @returns {string} translated string
 */
function t(key, locale, ...args) {
  if (typeof key !== 'string' || !key) return '';
  // Allow callers to omit the locale and pass positional args directly
  if (typeof locale !== 'string') {
    args.unshift(locale);
    locale = currentLocale;
  }
  const dict = cache.get(locale);
  let val = dict ? resolvePath(dict, key) : undefined;
  if (val == null && locale !== FALLBACK_LOCALE) {
    const fb = cache.get(FALLBACK_LOCALE);
    val = fb ? resolvePath(fb, key) : undefined;
  }
  if (val == null) return key; // missing -> return key for easier debugging
  return interpolate(val, args);
}

/**
 * Preload the active locale and the fallback locale in parallel.
 * Call this once on DOMContentLoaded. Safe to call multiple times.
 * @returns {Promise<void>}
 */
async function loadInitial() {
  const tasks = [fetchLocale(currentLocale).catch(() => null)];
  if (currentLocale !== FALLBACK_LOCALE) {
    tasks.push(fetchLocale(FALLBACK_LOCALE).catch(() => null));
  }
  await Promise.all(tasks);
}

/**
 * Switch active locale: fetch (if not cached), update state, persist.
 * @param {string} locale
 * @returns {Promise<void>}
 */
async function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) locale = DEFAULT_LOCALE;
  await fetchLocale(locale);
  if (locale !== FALLBACK_LOCALE) {
    // ensure fallback is also warm for graceful degradation
    fetchLocale(FALLBACK_LOCALE).catch(() => null);
  }
  currentLocale = locale;
  try { window.localStorage.setItem(LOCALE_STORAGE_KEY, locale); } catch (_) { /* ignore */ }
  // Notify subscribers (e.g. data-i18n-* auto-apply scripts)
  try {
    document.documentElement.setAttribute('lang', locale);
    document.dispatchEvent(new CustomEvent('i18n:change', { detail: { locale } }));
  } catch (_) { /* ignore */ }
}

/** Public i18n facade. */
const i18n = {
  supported: SUPPORTED_LOCALES,
  get locale() { return currentLocale; },
  t,
  load: fetchLocale,
  setLocale,
  loadInitial,
  // Exposed for advanced consumers / testing
  _resolvePath: resolvePath,
  _interpolate: interpolate,
};

// Expose globally as `i18n` (the task spec) AND `window.i18n` (alias).
window.i18n = i18n;
if (typeof globalThis !== 'undefined') globalThis.i18n = i18n;

// Auto-initialize on DOM ready so consumers can call `i18n('key')` immediately.
if (typeof document !== 'undefined') {
  const ready = () => {
    i18n.loadInitial().catch((err) => {
      // eslint-disable-next-line no-console
      console.warn('[i18n] initial load failed:', err && err.message);
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready, { once: true });
  } else {
    ready();
  }
}

export default i18n;
export { t, setLocale, fetchLocale, loadInitial, resolvePath, interpolate, SUPPORTED_LOCALES, FALLBACK_LOCALE, DEFAULT_LOCALE };
