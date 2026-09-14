'use strict';

function toast(msg, type, ms) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = U.el('div', { id: 'toast-container' });
    document.body.appendChild(container);
  }
  const t = U.el('div', { class: 'toast ' + (type || 'info') });
  if (typeof msg === 'string') t.textContent = msg;
  else t.appendChild(msg);
  container.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transition = 'opacity 0.3s';
    setTimeout(() => t.remove(), 320);
  }, ms || 3600);
}
window.toast = toast;

function escapeHtml(s) { return U.esc(s); }

function openModal({ title, body, footer, size, onMount }) {
  const overlay = U.el('div', { class: 'modal-overlay' });
  const modal = U.el('div', { class: 'modal ' + (size || '') });
  const head = U.el('div', { class: 'modal-head' });
  head.appendChild(U.el('div', { class: 'modal-title', text: title }));
  const closeBtn = U.el('button', { class: 'modal-close', 'aria-label': 'Close' });
  closeBtn.innerHTML = '&times;';
  const close = () => overlay.remove();
  closeBtn.addEventListener('click', close);
  head.appendChild(closeBtn);
  modal.appendChild(head);

  const bodyEl = U.el('div', { class: 'modal-body' });
  if (typeof body === 'string') bodyEl.textContent = body;
  else if (body) bodyEl.appendChild(body);
  modal.appendChild(bodyEl);

  const footEl = U.el('div', { class: 'modal-foot' });
  if (footer) {
    (Array.isArray(footer) ? footer : [footer]).forEach((f) => {
      if (typeof f === 'string') footEl.appendChild(document.createTextNode(f));
      else if (f) footEl.appendChild(f);
    });
  }
  modal.appendChild(footEl);

  overlay.appendChild(modal);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
  document.body.appendChild(overlay);
  if (onMount) onMount(overlay, modal);
  return { overlay, modal, close };
}
window.openModal = openModal;

function closeTopModal() {
  const o = document.querySelector('.modal-overlay');
  if (o) o.remove();
}
window.closeTopModal = closeTopModal;

function confirmDialog(message, { title, danger } = {}) {
  return new Promise((resolve) => {
    const modal = openModal({
      title: title || Locale.t('confirm'),
      size: 'sm',
      body: U.el('p', { text: message, style: 'line-height:1.6' }),
      footer: [
        U.el('button', { class: 'btn btn-outline', text: Locale.t('cancel'), onclick: () => { modal.close(); resolve(false); } }),
        U.el('button', { class: 'btn ' + (danger ? 'btn-danger' : 'btn-primary'), text: Locale.t('confirm'), onclick: () => { modal.close(); resolve(true); } }),
      ],
    });
  });
}
window.confirmDialog = confirmDialog;

function fieldError(input, msg) {
  const wrap = input.closest('.field');
  if (!wrap) return;
  let err = wrap.querySelector('.field-error');
  if (!err) {
    err = U.el('div', { class: 'field-error' });
    wrap.appendChild(err);
  }
  err.textContent = msg;
}

function clearFieldErrors(root) {
  U.qsa('.field-error', root).forEach((el) => el.remove());
}

function applyApiErrors(root, res, fallback) {
  const map = res && res.data ? Api.errorDetails(res.data) : null;
  if (map) {
    let any = false;
    const values = Object.keys(map);
    U.qsa('input,select,textarea', root).forEach((el) => {
      const k = el.name || el.id;
      if (k && values.includes(k)) {
        const val = map[k];
        if (Array.isArray(val)) fieldError(el, val.join(', '));
        else fieldError(el, String(val));
        any = true;
      }
    });
    if (any) return true;
    toast(Api.errorMessage(res.data, fallback), 'error');
    return true;
  }
  toast(Api.errorMessage(res && res.data, fallback), 'error');
  return false;
}

window.fieldError = fieldError;
window.clearFieldErrors = clearFieldErrors;
window.applyApiErrors = applyApiErrors;
window.escapeHtml = escapeHtml;

function renderTable({ columns, rows, emptyText }) {
  if (!rows || rows.length === 0) {
    return U.el('div', { class: 'empty-state' }, [
      U.el('div', { text: Locale.t('noData') }),
    ]);
  }
  const table = U.el('table', { class: 'tbl' });
  const thead = U.el('thead');
  const trh = U.el('tr');
  columns.forEach((c) => trh.appendChild(U.el('th', { text: c.title })));
  thead.appendChild(trh);
  table.appendChild(thead);
  const tbody = U.el('tbody');
  rows.forEach((row) => {
    const tr = U.el('tr');
    columns.forEach((c) => {
      let cell;
      if (c.render) {
        cell = U.el('td');
        const content = c.render(row);
        if (typeof content === 'string') cell.innerHTML = content;
        else if (content) cell.appendChild(content);
      } else {
        const v = c.key ? U.escRow(row[c.key]) : (c.value ? (typeof c.value === 'function' ? c.value(row) : c.value) : '');
        cell = U.el('td', { text: String(v) });
      }
      tr.appendChild(cell);
    });
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  return table;
}
U.escRow = (v) => U.esc(v);

function renderPagination(pg, onPage) {
  const wrap = U.el('div', { class: 'pagination' });
  wrap.appendChild(U.el('span', { text: `${pg.total} ${Locale.t('of')} ${pg.total}` }));
  const info = U.el('span', { text: `${Locale.t('page')} ${pg.current_page} / ${pg.total_pages}` });
  wrap.appendChild(info);
  wrap.appendChild(U.el('div', { class: 'spacer' }));
  const prev = U.el('button', { class: 'btn btn-outline btn-sm', text: Locale.t('prev'), disabled: !pg.prev_page ? 'disabled' : null, onclick: () => onPage(pg.current_page - 1) });
  const nxt = U.el('button', { class: 'btn btn-outline btn-sm', text: Locale.t('next'), disabled: !pg.next_page ? 'disabled' : null, onclick: () => onPage(pg.current_page + 1) });
  wrap.appendChild(prev);
  wrap.appendChild(nxt);
  return wrap;
}
window.renderTable = renderTable;
window.renderPagination = renderPagination;

const Pg = {
  pgFrom(res) {
    if (!res || !res.data) return null;
    const m = res.data.meta && res.data.meta.pagination;
    if (m) return m;
    if (res.meta && res.meta.pagination) return res.meta.pagination;
    if (res.data.pagination) return res.data.pagination;
    return null;
  },

  badge(status) {
    if (!status) return '';
    const cls = U.statusClass(status);
    return `<span class="${cls}">${U.esc(status)}</span>`;
  },

  itemsOf(list) {
    if (Array.isArray(list)) return list;
    if (!list) return [];
    if (Array.isArray(list.data)) return list.data;
    if (Array.isArray(list.items)) return list.items;
    if (Array.isArray(list.translations)) return list.translations;
    if (Array.isArray(list.rows)) return list.rows;
    return [];
  },

  resolve(res) {
    if (!res || !res.ok) return null;
    const list = Api.unwrap(res.data);
    let pg = null;
    if (res.data && res.data.meta && res.data.meta.pagination) pg = res.data.meta.pagination;
    else if (list && list.pagination) pg = list.pagination;
    return { list, items: Pg.itemsOf(list), pg };
  },

  panel(title, body, foot) {
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [
      U.el('div', { class: 'panel-title', text: title }),
    ]));
    const bodyEl = U.el('div', { class: 'panel-body' });
    if (typeof body === 'string') bodyEl.textContent = body;
    else if (body) bodyEl.appendChild(body);
    panel.appendChild(bodyEl);
    if (foot) {
      const f = U.el('div', { class: 'pagination' });
      f.appendChild(foot);
      panel.appendChild(f);
    }
    return panel;
  },

  loading(text) {
    return U.el('div', { class: 'empty-state' }, [U.el('div', { text: text || Locale.t('loading') })]);
  },

  errorBanner(msg) {
    const b = U.el('div', { class: 'status-banner error' });
    b.appendChild(document.createTextNode(msg));
    return b;
  },

  infoBanner(msg) {
    return U.el('div', { class: 'status-banner info' }, [U.el('span', { text: msg })]);
  },

  warnBanner(msg) {
    return U.el('div', { class: 'status-banner warn' }, [U.el('span', { text: msg })]);
  },

  filterForm(fields, onApply) {
    const form = U.el('form', { class: 'filter-form' });
    fields.forEach((f) => {
      const wrap = U.el('div', { class: 'field' });
      wrap.appendChild(U.el('label', { text: f.label, for: 'ff-' + f.name }));
      const input = U.el(f.type === 'select' ? 'select' : 'input', {
        id: 'ff-' + f.name,
        name: f.name,
        type: f.type || 'text',
        value: f.value !== undefined ? f.value : '',
        placeholder: f.placeholder || '',
      });
      if (f.type === 'select' && f.options) {
        f.options.forEach((o) => {
          input.appendChild(U.el('option', { value: o.value, text: o.label, selected: String(f.value) === String(o.value) ? 'selected' : null }));
        });
      }
      wrap.appendChild(input);
      form.appendChild(wrap);
    });
    const btnWrap = U.el('div', { class: 'field', style: 'display:flex;gap:8px;align-items:flex-end' });
    const applyBtn = U.el('button', { class: 'btn btn-primary', type: 'submit', text: Locale.t('filter') });
    const resetBtn = U.el('button', { class: 'btn btn-outline', type: 'button', text: Locale.t('reset') || 'Reset' });
    btnWrap.appendChild(applyBtn);
    btnWrap.appendChild(resetBtn);
    form.appendChild(btnWrap);
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = {};
      fields.forEach((f) => { data[f.name] = form.elements['ff-' + f.name].value; });
      if (onApply) onApply(data);
    });
    resetBtn.addEventListener('click', () => {
      U.qsa('input,select', form).forEach((el) => { el.value = ''; });
      const data = {};
      fields.forEach((f) => { data[f.name] = ''; });
      if (onApply) onApply(data);
    });
    return form;
  },

  input(label, name, value, type) {
    const wrap = U.el('div', { class: 'field' });
    wrap.appendChild(U.el('label', { text: label, for: 'in-' + name }));
    const input = U.el('input', { id: 'in-' + name, name, type: type || 'text', value: value !== undefined && value !== null ? value : '' });
    wrap.appendChild(input);
    return wrap;
  },

  select(label, name, value, options) {
    const wrap = U.el('div', { class: 'field' });
    wrap.appendChild(U.el('label', { text: label, for: 'in-' + name }));
    const sel = U.el('select', { id: 'in-' + name, name });
    options.forEach((o) => {
      sel.appendChild(U.el('option', { value: o.value, text: o.label, selected: String(value) === String(o.value) ? 'selected' : null }));
    });
    wrap.appendChild(sel);
    return wrap;
  },

  textarea(label, name, value) {
    const wrap = U.el('div', { class: 'field' });
    wrap.appendChild(U.el('label', { text: label, for: 'in-' + name }));
    const ta = U.el('textarea', { id: 'in-' + name, name });
    if (value) ta.value = value;
    wrap.appendChild(ta);
    return wrap;
  },

  money(n) { return U.fmtMoney(n); },
  datetime(v) { return U.fmtDateTime(v); },
  date(v) { return U.fmtDate(v); },

  exportCsv(filename, headers, rows) {
    const esc = (v) => {
      const s = v === null || v === undefined ? '' : String(v);
      return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };
    const lines = [headers.map(esc).join(',')];
    rows.forEach((r) => lines.push(r.map(esc).join(',')));
    const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 100);
  },

  actionBtn(label, cls, onclick) {
    return U.el('button', { class: 'btn ' + (cls || 'btn-outline') + ' btn-sm', text: label, onclick });
  },
};
window.Pg = Pg;