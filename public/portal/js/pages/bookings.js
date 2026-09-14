'use strict';

const BookingsPage = {
  state: { status: '', from: '', to: '', q: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ status: this.state.status || undefined, from: this.state.from || undefined, to: this.state.to || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/admin/bookings' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    const d = Api.unwrap(res.data);
    return d;
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/admin/bookings/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const d = Api.unwrap(res.data);
    const b = d && (d.booking || d);
    if (!b) return;
    const grid = U.el('div', { class: 'detail-grid' });
    [
      ['Booking #', b.booking_number],
      ['Date', b.date],
      ['Status', Pg.badge(b.status)],
      ['Centre', b.centre ? b.centre.name : ''],
      ['Slot', b.slot ? b.slot.start_time + '-' + b.slot.end_time : ''],
      ['Crops', b.crop_count],
      ['Total qty', b.total_quantity_kg + ' kg'],
      ['Created', U.fmtDateTime(b.created_at)],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v) })])));
    const crops = U.el('div', { style: 'margin-top:14px' });
    if (b.crops && b.crops.length) {
      crops.appendChild(U.el('div', { class: 'panel-title', text: 'Crops' }));
      const tbl = renderTable({
        columns: [
          { title: 'Crop', render: (r) => U.esc(r.crop_name) },
          { title: 'Qty (kg)', render: (r) => String(r.quantity_kg) },
          { title: 'Rate', render: (r) => U.fmtMoney(r.rate_per_kg) },
          { title: 'Amount', render: (r) => U.fmtMoney(r.amount) },
        ],
        rows: b.crops,
      });
      crops.appendChild(tbl);
    }
    if (b.token) {
      crops.appendChild(U.el('div', { class: 'panel-title', style: 'margin-top:14px', text: 'Token' }));
      crops.appendChild(U.el('div', { class: 'detail-grid' }, [
        U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Token #' }), U.el('div', { class: 'v', text: String(b.token.token_number) })]),
        U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Status' }), U.el('div', { class: 'v', text: String(b.token.status) })]),
      ]));
    }
    openModal({ title: 'Booking Detail', size: 'lg', body: U.el('div', null, [grid, crops]) });
  },

  async cancel(b) {
    if (!Auth.can('bookings.cancel_any')) { toast('Permission denied', 'error'); return; }
    const modal = openModal({
      title: 'Cancel Booking ' + b.booking_number,
      size: 'sm',
      body: (() => { const w = U.el('div'); w.appendChild(Pg.textarea('Reason', 'reason', '')); return w; })(),
    });
    const reasonEl = modal.overlay.querySelector('textarea');
    const okBtn = U.el('button', { class: 'btn btn-danger', text: Locale.t('confirm') });
    modal.footer.appendChild(okBtn);
    okBtn.addEventListener('click', async () => {
      const reason = reasonEl.value.trim();
      if (!reason) { toast('Reason required', 'warn'); return; }
      okBtn.disabled = true;
      const res = await Api.post('/api/v1/admin/bookings/' + b.id + '/cancel', { reason }, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(modal.overlay, res); return; }
      toast('Booking cancelled', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  paint(host, list, pg) {
    const items = (list && list.data) || (list && list.items) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['', 'PENDING', 'CONFIRMED', 'CANCELLED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    const fromI = U.el('input', { type: 'date', value: this.state.from, style: 'width:auto' });
    const toI = U.el('input', { type: 'date', value: this.state.to, style: 'width:auto' });
    toolbar.appendChild(fromI);
    toolbar.appendChild(toI);
    const qI = U.el('input', { placeholder: 'Booking no / mobile', value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: async () => {
      this.state.status = sel.querySelector('select').value;
      this.state.from = fromI.value;
      this.state.to = toI.value;
      this.state.q = qI.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => {
      Pg.exportCsv('bookings.csv', ['Booking', 'Date', 'Status', 'Centre', 'Slot', 'Crops', 'Qty'], items.map((b) => [b.booking_number, b.date, b.status, b.centre ? b.centre.name : '', b.slot ? b.slot.start_time + '-' + b.slot.end_time : '', b.crop_count, b.total_quantity_kg]));
    } }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Bookings' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Booking', render: (r) => U.esc(r.booking_number) },
          { title: 'Date', render: (r) => U.esc(r.date) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Centre', render: (r) => (r.centre ? U.esc(r.centre.name) : '') },
          { title: 'Slot', render: (r) => (r.slot ? U.esc(r.slot.start_time.slice(0, 5) + ' - ' + r.slot.end_time.slice(0, 5)) : '') },
          { title: 'Qty (kg)', render: (r) => String(r.total_quantity_kg || 0) },
          { title: 'Created', render: (r) => U.esc(U.fmtDateTime(r.created_at)) },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (Auth.can('bookings.cancel_any') && r.status !== 'CANCELLED') span.appendChild(Pg.actionBtn('Cancel', 'btn-danger', () => this.cancel(r)));
            return span;
          } },
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
    const list = await this.fetchList();
    if (!list) { host.innerHTML = ''; return; }
    this.paint(host, list, Pg.pgFrom({ data: list }));
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};