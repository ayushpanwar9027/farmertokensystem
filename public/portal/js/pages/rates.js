'use strict';

const RatesPage = {
  state: { cropId: '', centreId: '', isActive: '', date: '' },

  async fetchList() {
    const q = U.qsFrom({ crop_id: this.state.cropId || undefined, centre_id: this.state.centreId || undefined, is_active: this.state.isActive || undefined, date: this.state.date || undefined });
    const res = await Api.get('/api/v1/admin/crop-rates' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  async openForm(rate) {
    if (!Auth.can('rates.manage')) { toast('Permission denied', 'error'); return; }
    const cropsRes = await Api.get('/api/v1/crops');
    const crops = cropsRes.ok ? (Api.unwrap(cropsRes.data).crops || []).map((c) => ({ value: String(c.id), label: c.name + ' (' + c.unit + ')' })) : [];
    const centresRes = await Api.get('/api/v1/centres' + U.qsFrom({ status: 'ACTIVE', per_page: 100 }));
    const centres = [{ value: '', label: 'All centres' }];
    if (centresRes.ok) {
      (Api.unwrap(centresRes.data).data || []).forEach((c) => centres.push({ value: String(c.id), label: c.name }));
    }
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fCrop = Pg.select('Crop', 'crop_id', rate ? String(rate.crop_id) : '', crops);
    const fCentre = Pg.select('Centre', 'centre_id', rate ? String(rate.centre_id || '') : '', centres);
    const fRate = Pg.input('Rate per kg (₹)', 'rate_per_kg', rate ? rate.rate_per_kg : '');
    const fFrom = Pg.input('Effective from', 'effective_from', rate ? (rate.effective_from || U.nowIso()) : U.nowIso());
    const fTo = Pg.input('Effective to (optional)', 'effective_to', rate ? rate.effective_to || '' : '');
    [fCrop, fCentre, fRate, fFrom, fTo].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: rate ? 'Edit Rate' : 'New Rate', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const payload = {
        crop_id: parseInt(fCrop.querySelector('select').value, 10),
        rate_per_kg: parseFloat(fRate.querySelector('input').value),
        effective_from: fFrom.querySelector('input').value,
      };
      const cid = fCentre.querySelector('select').value;
      if (cid) payload.centre_id = parseInt(cid, 10);
      const to = fTo.querySelector('input').value;
      if (to) payload.effective_to = to;
      const res = rate
        ? await Api.put('/api/v1/admin/crop-rates/' + rate.id, payload, { handleMaintenance: false })
        : await Api.post('/api/v1/admin/crop-rates', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Saved', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async toggleActive(rate) {
    if (!Auth.can('rates.manage')) { toast('Permission denied', 'error'); return; }
    const next = rate.is_active ? false : true;
    const res = await Api.put('/api/v1/admin/crop-rates/' + rate.id, { is_active: next }, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast(next ? 'Rate activated' : 'Rate deactivated', 'success');
    this.render(this.host);
  },

  async paint(host, list) {
    const items = (list && list.items) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const cropsRes = await Api.get('/api/v1/crops');
    const crops = cropsRes.ok ? (Api.unwrap(cropsRes.data).crops || []) : [];
    const cropOpts = []; cropOpts.push({ value: '', label: 'All crops' });
    crops.forEach((c) => cropOpts.push({ value: String(c.id), label: c.name }));
    const sel = Pg.select('', 'crop_id', this.state.cropId, cropOpts);
    toolbar.appendChild(sel);
    const activeSel = Pg.select('', 'is_active', this.state.isActive, [['', 'All'],['1','Active'],['0','Inactive']].map(([v,l]) => ({ value: v, label: l })));
    toolbar.appendChild(activeSel);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.cropId = sel.querySelector('select').value;
      this.state.isActive = activeSel.querySelector('select').value;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('rates.manage')) toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ Rate', onclick: () => this.openForm(null) }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('crop-rates.csv', ['Crop', 'Centre', 'Rate/kg', 'From', 'To', 'Active'], items.map((r) => [r.crop_name, r.centre_name || 'All', r.rate_per_kg, r.effective_from, r.effective_to, r.is_active])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Crop Rates' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Crop', render: (r) => U.esc(r.crop_name) },
          { title: 'Centre', render: (r) => U.esc(r.centre_name || 'All') },
          { title: 'Rate/kg', render: (r) => U.fmtMoney(r.rate_per_kg) },
          { title: 'From', render: (r) => U.esc(r.effective_from || '') },
          { title: 'To', render: (r) => U.esc(r.effective_to || '') },
          { title: 'Active', render: (r) => (r.is_active ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-grey">No</span>') },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            if (Auth.can('rates.manage')) {
              span.appendChild(Pg.actionBtn('Edit', 'btn-outline', () => this.openForm(r)));
              span.appendChild(Pg.actionBtn(r.is_active ? 'Deactivate' : 'Activate', r.is_active ? 'btn-outline' : 'btn-primary', () => this.toggleActive(r)));
            }
            return span;
          } },
        ],
        rows: items,
      }));
    }
    panel.appendChild(pbody);
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const list = await this.fetchList();
    if (!list) { host.innerHTML = ''; return; }
    this.paint(host, list);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};