'use strict';

const U = {
  esc(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },
  el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k === 'html') node.innerHTML = v;
        else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
        else if (v !== null && v !== undefined) node.setAttribute(k, v);
      }
    }
    if (children) {
      (Array.isArray(children) ? children : [children]).forEach((c) => {
        if (c === null || c === undefined) return;
        node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      });
    }
    return node;
  },
  qs(sel, root) { return (root || document).querySelector(sel); },
  qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); },
  params(url) {
    const out = {};
    const q = url.split('?')[1];
    if (!q) return out;
    q.split('&').forEach((pair) => {
      const [k, v] = pair.split('=');
      if (k) out[decodeURIComponent(k)] = decodeURIComponent((v || '').replace(/\+/g, ' '));
    });
    return out;
  },
  qsFrom(obj) {
    const parts = Object.entries(obj)
      .filter(([, v]) => v !== '' && v !== null && v !== undefined)
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
    return parts.length ? '?' + parts.join('&') : '';
  },
  debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), ms); };
  },
  fmtMoney(n, digits) {
    const num = Number(n || 0);
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: digits || 2, maximumFractionDigits: digits || 2 });
  },
  fmtNum(n) {
    return Number(n || 0).toLocaleString('en-IN');
  },
  fmtDateTime(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? String(v) : d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  },
  fmtDate(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? String(v) : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  },
  nowIso() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  },
  statusClass(s) {
    const str = String(s || 'unknown').toLowerCase().replace(/\s+/g, '_');
    const known = ['green', 'red', 'yellow', 'blue', 'grey', 'purple', 'teal', 'sent', 'failed', 'pending', 'retry', 'active', 'inactive', 'cancelled', 'rejected', 'waiting', 'called', 'in_progress', 'pending_approval', 'verified', 'confirmed', 'booked', 'expired', 'elapsed', 'no_show', 'reversed', 'initiated', 'queued', 'available'];
    return known.includes(str) ? 'badge badge-' + str : 'badge badge-grey';
  },
  truncate(s, n) { return s && s.length > n ? s.slice(0, n - 1) + '…' : s; },
  maskMobile(m) { return m ? String(m).replace(/^(\d{2})\d+(\d{2})$/, '$1******$2') : ''; },
};