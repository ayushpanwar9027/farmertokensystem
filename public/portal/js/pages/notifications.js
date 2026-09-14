'use strict';

const NotificationsPage = {
  state: { status: '', event: '', from: '', to: '', q: '', page: 1 },

  async fetchRaw() {
    const q = U.qsFrom({ status: this.state.status || undefined, event: this.state.event || undefined, date_from: this.state.from || undefined, date_to: this.state.to || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    return Api.get('/api/v1/admin/notifications' + q, { handleMaintenance: false });
  },

  async paintSummary(host) {
    const res = await Api.get('/api/v1/admin/notifications/summary', { handleMaintenance: false });
    if (!res.ok) return;
    const s = Api.unwrap(res.data);
    if (!s || !s.summary) return;
    const sum = s.summary;
    const grid = U.el('div', { class: 'card-grid' });
    [
      ['Sent', sum.sent || 0, 'var(--success)'],
      ['Failed', sum.failed || 0, 'var(--danger)'],
      ['Pending', sum.pending || 0, 'var(--info)'],
      ['Retry', sum.retry || 0, 'var(--warning)'],
    ].forEach(([l, v, c]) => {
      const card = U.el('div', { class: 'stat-card' });
      card.style.borderLeft = '4px solid ' + c;
      card.appendChild(U.el('div', { class: 'stat-label', text: 'Push Today - ' + l }));
      card.appendChild(U.el('div', { class: 'stat-value', text: String(v) }));
      grid.appendChild(card);
    });
    host.appendChild(grid);
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/notifications/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const n = Api.unwrap(res.data);
    const row = n && n.notification ? n.notification : n;
    const grid = U.el('div', { class: 'detail-grid' });
    [
      ['ID', row.id],
      ['User', row.user_name],
      ['Event', row.event],
      ['Channel', row.channel],
      ['Status', Pg.badge(row.status)],
      ['Attempts', row.attempts],
      ['Sent at', U.fmtDateTime(row.sent_at)],
      ['Created', U.fmtDateTime(row.created_at)],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v !== null && v !== undefined ? v : '-') })])));
    const payloadEl = U.el('div', { style: 'margin-top:12px' });
    if (row.payload) payloadEl.appendChild(U.el('pre', { text: typeof row.payload === 'string' ? row.payload : JSON.stringify(row.payload, null, 2), style: 'background:var(--bg);border-radius:8px;padding:10px;font-size:12px;overflow:auto' }));
    if (row.error_message) payloadEl.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Error' }), U.el('div', { class: 'v', text: U.esc(row.error_message) })]));
    openModal({ title: 'Notification Detail', size: 'lg', body: U.el('div', null, [grid, payloadEl]) });
  },

  async testPush() {
    if (!Auth.can('notifications.test')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Send a test push. Provide a player id or send to all devices.', style: 'margin-bottom:12px;color:var(--text-light)' }));
    const f1 = Pg.input('OneSignal player id', 'onesignal_player_id', '');
    const chk = U.el('label', { style: 'display:flex;gap:6px;align-items:center;font-size:14px;margin:8px 0' });
    const cb = U.el('input', { type: 'checkbox', style: 'width:auto' });
    chk.appendChild(cb);
    chk.appendChild(U.el('span', { text: 'Send to all devices' }));
    body.appendChild(f1);
    body.appendChild(chk);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Send' });
    const modal = openModal({ title: 'Test Push', size: 'sm', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      const payload = {};
      if (cb.checked) payload.all = true;
      else payload.onesignal_player_id = f1.querySelector('input').value.trim();
      if (!payload.onesignal_player_id && !payload.all) { toast('Enter a player id or choose all', 'warn'); return; }
      okBtn.disabled = true;
      const res = await Api.post('/api/v1/admin/notifications/test-push', payload, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); okBtn.disabled = false; return; }
      toast('Push sent', 'success');
      modal.close();
    });
  },

  paint(host, items, pg) {
    host.innerHTML = '';
    this.paintSummary(host);
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['', 'PENDING', 'SENT', 'RETRY', 'FAILED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    const eventI = U.el('input', { placeholder: 'Event', value: this.state.event, style: 'width:auto' });
    toolbar.appendChild(eventI);
    const fromI = U.el('input', { type: 'date', value: this.state.from, style: 'width:auto' });
    const toI = U.el('input', { type: 'date', value: this.state.to, style: 'width:auto' });
    toolbar.appendChild(fromI);
    toolbar.appendChild(toI);
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.status = sel.querySelector('select').value;
      this.state.event = eventI.value.trim();
      this.state.from = fromI.value;
      this.state.to = toI.value;
      this.state.q = qI.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('notifications.test')) toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: 'Test Push', onclick: () => this.testPush() }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Notification Log' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'ID', render: (r) => String(r.id) },
          { title: 'User', render: (r) => U.esc(r.user_name || '') },
          { title: 'Event', render: (r) => U.esc(r.event || '') },
          { title: 'Channel', render: (r) => U.esc((r.channel || '').toUpperCase()) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Sent', render: (r) => U.esc(U.fmtDateTime(r.sent_at)) },
          { title: 'Created', render: (r) => U.esc(U.fmtDateTime(r.created_at)) },
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