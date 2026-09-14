'use strict';

const AuditPage = {
  state: { action: '', module: '', from: '', to: '', q: '', page: 1 },

  async fetchRaw() {
    const q = U.qsFrom({ action: this.state.action || undefined, module: this.state.module || undefined, date_from: this.state.from || undefined, date_to: this.state.to || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    return Api.get('/api/v1/admin/audit' + q, { handleMaintenance: false });
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/admin/audit/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const a = Api.unwrap(res.data);
    const row = a && a.audit ? a.audit : a;
    const grid = U.el('div', { class: 'detail-grid' });
    [
      ['ID', row.id],
      ['User', row.user_name + ' (' + (row.user_role || '') + ')'],
      ['Action', Pg.badge(row.action)],
      ['Module', row.module],
      ['Entity', row.entity_type + ' #' + (row.entity_id || '')],
      ['IP', row.ip_address],
      ['Time', U.fmtDateTime(row.created_at)],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v !== null && v !== undefined ? v : '-') })])));
    const diffs = U.el('div', { style: 'margin-top:14px' });
    const pre = (lbl, obj) => {
      if (!obj) return;
      let txt = '';
      try { txt = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2); } catch (e) { txt = String(obj); }
      diffs.appendChild(U.el('div', { class: 'panel-title', style: 'font-size:13px;margin:8px 0 4px;text-transform:uppercase', text: lbl }));
      diffs.appendChild(U.el('pre', { text: txt, style: 'background:var(--bg);border-radius:8px;padding:10px;font-size:12px;overflow:auto;max-height:300px' }));
    };
    pre('Old value', row.old_value);
    pre('New value', row.new_value);
    openModal({ title: 'Audit Entry', size: 'lg', body: U.el('div', null, [grid, diffs]) });
  },

  paint(host, items, pg) {
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const actionI = U.el('input', { placeholder: 'Action', value: this.state.action, style: 'width:auto' });
    toolbar.appendChild(actionI);
    const modI = U.el('input', { placeholder: 'Module', value: this.state.module, style: 'width:auto' });
    toolbar.appendChild(modI);
    const fromI = U.el('input', { type: 'date', value: this.state.from, style: 'width:auto' });
    const toI = U.el('input', { type: 'date', value: this.state.to, style: 'width:auto' });
    toolbar.appendChild(fromI);
    toolbar.appendChild(toI);
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.action = actionI.value.trim();
      this.state.module = modI.value.trim();
      this.state.from = fromI.value;
      this.state.to = toI.value;
      this.state.q = qI.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('audit.csv', ['ID', 'User', 'Role', 'Action', 'Module', 'Entity', 'IP', 'Time'], items.map((a) => [a.id, a.user_name, a.user_role, a.action, a.module, a.entity_type + '#' + a.entity_id, a.ip_address, a.created_at])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Audit Log' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'ID', render: (r) => String(r.id) },
          { title: 'User', render: (r) => U.esc(r.user_name || 'system') },
          { title: 'Role', render: (r) => U.esc(r.user_role || '') },
          { title: 'Action', render: (r) => Pg.badge(r.action) },
          { title: 'Module', render: (r) => U.esc(r.module || '') },
          { title: 'Entity', render: (r) => U.esc((r.entity_type || '') + '#' + (r.entity_id || '')) },
          { title: 'Time', render: (r) => U.esc(U.fmtDateTime(r.created_at)) },
          { title: '', render: (r) => Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)) },
        ],
        rows: items,
      }));
    }
    panel.appendChild(pbody);
    if (pg) panel.appendChild(renderPagination(pg, (n) => { this.state.page = n; this.load(host); }));
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const res = await this.fetchRaw();
    if (!res || !res.ok) { host.innerHTML = ''; return; }
    const r = Pg.resolve(res);
    this.paint(host, r.items, r.pg);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};