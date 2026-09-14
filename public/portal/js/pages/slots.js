'use strict';

const SlotsPage = {
  state: { centreId: '', status: '', from: '', to: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ q: undefined, centre_id: this.state.centreId || undefined, status: this.state.status || undefined, date_from: this.state.from || undefined, date_to: this.state.to || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/admin/slots' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    const d = Api.unwrap(res.data);
    return d;
  },

  async openGenerate() {
    if (!Auth.can('slots.manage')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fFrom = Pg.input('From date', 'date_from', U.nowIso());
    const fTo = Pg.input('To date', 'date_to', U.nowIso());
    const fDays = Pg.input('Days (comma, e.g. MON,TUE or empty=all)', 'days', '');
    [fFrom, fTo, fDays].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Generate' });
    const modal = openModal({ title: 'Generate Slots', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const payload = { date_from: fFrom.querySelector('input').value, date_to: fTo.querySelector('input').value };
      const daysStr = fDays.querySelector('input').value.trim();
      if (daysStr) payload.days = daysStr.split(',').map((s) => s.trim().toUpperCase());
      const res = await Api.post('/api/v1/admin/slots/generate', payload, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); okBtn.disabled = false; return; }
      const d = Api.unwrap(res.data);
      const s = d && d.summary;
      toast('Generated ' + (s ? s.generated : 0) + ' slots', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async openForm(slot) {
    if (!Auth.can('slots.manage')) { toast('Permission denied', 'error'); return; }
    const centres = await Api.get('/api/v1/centres' + U.qsFrom({ status: 'ACTIVE', per_page: 100 }));
    const centreOptions = centres.ok ? ((Api.unwrap(centres.data).data || [])).map((c) => ({ value: String(c.id), label: c.name })) : [];
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fCentre = Pg.select('Centre', 'centre_id', slot ? String(slot.centre_id) : '', centreOptions);
    const fDate = Pg.input('Date', 'date', slot ? slot.date : U.nowIso());
    const fStart = Pg.input('Start (HH:MM)', 'start_time', slot ? slot.start_time.slice(0, 5) : '08:00');
    const fEnd = Pg.input('End (HH:MM)', 'end_time', slot ? slot.end_time.slice(0, 5) : '08:15');
    const fCap = Pg.input('Capacity', 'capacity', slot ? slot.capacity : '10');
    [fCentre, fDate, fStart, fEnd, fCap].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: slot ? 'Edit Slot' : 'New Slot', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const payload = {
        centre_id: parseInt(fCentre.querySelector('select').value, 10),
        date: fDate.querySelector('input').value,
        start_time: fStart.querySelector('input').value + ':00',
        end_time: fEnd.querySelector('input').value + ':00',
        capacity: parseInt(fCap.querySelector('input').value, 10) || 1,
      };
      const res = slot
        ? await Api.put('/api/v1/admin/slots/' + slot.id, payload, { handleMaintenance: false })
        : await Api.post('/api/v1/admin/slots', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Saved', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async cancelSlot(s) {
    if (!Auth.can('slots.cancel')) { toast('Permission denied', 'error'); return; }
    const want = await confirmDialog('Cancel slot ' + s.id + '?', { danger: true });
    if (!want) return;
    const res = await Api.put('/api/v1/admin/slots/' + s.id, { status: 'CANCELLED' }, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Slot cancelled', 'success');
    this.render(this.host);
  },

  async deleteSlot(s) {
    if (!Auth.can('slots.manage')) { toast('Permission denied', 'error'); return; }
    const want = await confirmDialog('Delete slot ' + s.id + '?', { danger: true });
    if (!want) return;
    const res = await Api.del('/api/v1/admin/slots/' + s.id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Deleted', 'success');
    this.render(this.host);
  },

  async paint(host, list, pg) {
    const items = (list && list.data) || (list && Array.isArray(list) ? list : []);
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['', 'ACTIVE', 'INACTIVE', 'CANCELLED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    const fromI = U.el('input', { type: 'date', value: this.state.from, style: 'width:auto' });
    const toI = U.el('input', { type: 'date', value: this.state.to, style: 'width:auto' });
    toolbar.appendChild(fromI);
    toolbar.appendChild(toI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.status = sel.querySelector('select').value;
      this.state.from = fromI.value;
      this.state.to = toI.value;
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('slots.manage')) {
      toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ Slot', onclick: () => this.openForm(null) }));
      toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'Generate', onclick: () => this.openGenerate() }));
    }
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('slots.csv', ['ID', 'Date', 'Start', 'End', 'Capacity', 'Booked', 'Status', 'Centre'], items.map((s) => [s.id, s.date, s.start_time, s.end_time, s.capacity, s.booked, s.status, s.centre ? s.centre.name : ''])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Slots' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'ID', render: (r) => String(r.id) },
          { title: 'Date', render: (r) => U.esc(r.date) },
          { title: 'Start', render: (r) => U.esc(String(r.start_time).slice(0, 5)) },
          { title: 'End', render: (r) => U.esc(String(r.end_time).slice(0, 5)) },
          { title: 'Capacity', render: (r) => String(r.capacity) },
          { title: 'Booked', render: (r) => String(r.booked || 0) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Centre', render: (r) => (r.centre ? U.esc(r.centre.name) : '') },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            if (Auth.can('slots.manage') && r.status !== 'CANCELLED') span.appendChild(Pg.actionBtn('Edit', 'btn-outline', () => this.openForm(r)));
            if (Auth.can('slots.cancel') && r.status !== 'CANCELLED') span.appendChild(Pg.actionBtn('Cancel', 'btn-danger', () => this.cancelSlot(r)));
            if (Auth.can('slots.manage')) span.appendChild(Pg.actionBtn('Del', 'btn-danger', () => this.deleteSlot(r)));
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