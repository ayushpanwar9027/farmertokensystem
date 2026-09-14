'use strict';

const QueuePage = {
  state: { centreId: '', date: U.nowIso(), stats: null, entries: [] },

  async poll(host) {
    if (!this.state.date) return;
    const q = U.qsFrom({ centre_id: this.state.centreId || undefined, date: this.state.date });
    const res = await Api.get('/api/v1/operator/queue' + q, { handleMaintenance: false });
    if (!res.ok) return;
    const d = Api.unwrap(res.data);
    if (!d) return;
    this.state.entries = d.entries || [];
    const sRes = await Api.get('/api/v1/operator/queue/stats' + q, { handleMaintenance: false });
    if (sRes.ok) {
      const sd = Api.unwrap(sRes.data);
      this.state.stats = (sd && sd.stats) || null;
    }
    this.paintQueue(host);
  },

  paintQueue(host) {
    const container = host.querySelector('#queue-entries');
    const statsEl = host.querySelector('#queue-stats');
    if (!container) return;
    container.innerHTML = '';
    const es = this.state.entries || [];
    if (es.length === 0) {
      container.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    }
    es.forEach((e) => {
      const card = U.el('div', { class: 'queue-card ' + String(e.status || '').toLowerCase() });
      card.appendChild(U.el('div', { class: 'q-token', text: '#' + String(e.token || '').padStart(3, '0') }));
      card.appendChild(U.el('div', { class: 'q-name', text: (e.farmer && e.farmer.name) || '' }));
      card.appendChild(U.el('div', { class: 'q-sub', text: 'Book: ' + (e.booking_number || '') }));
      card.appendChild(U.el('div', { class: 'q-sub', text: 'Pos ' + (e.position || '') + ' | ' + String(e.status || '') + ' | ~' + (e.eta_minutes || 0) + ' min' }));
      const actions = U.el('div', { class: 'q-actions' });
      this.actionsFor(e, actions);
      card.appendChild(actions);
      container.appendChild(card);
    });
    if (statsEl) {
      const s = this.state.stats;
      statsEl.innerHTML = '';
      if (s) {
        const wrap = U.el('div', { class: 'card-grid', style: 'margin-bottom:0' });
        const stacks = [
          ['Served', s.served_today || 0],
          ['Waiting', s.waiting || 0],
          ['Called', s.called || 0],
          ['In Progress', s.in_progress || 0],
          ['Avg Wait', Math.round(s.avg_wait_minutes || 0) + 'm'],
        ];
        stacks.forEach(([l, v]) => {
          const c = U.el('div', { class: 'stat-card' });
          c.appendChild(U.el('div', { class: 'stat-label', text: l }));
          c.appendChild(U.el('div', { class: 'stat-value', text: String(v) }));
          wrap.appendChild(c);
        });
        statsEl.appendChild(wrap);
      }
    }
  },

  actionsFor(e, container) {
    const post = async (path, body) => {
      const res = await Api.post(path, body, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      const d = Api.unwrap(res.data);
      if (d && d.message) toast(d.message, 'success');
      this.poll(this.host || document.body);
    };
    const entry = e.id;

    if (['WAITING', 'CALLED'].includes(e.status)) {
      const callBtn = Pg.actionBtn('Start', 'btn-primary', () => post('/api/v1/operator/queue/' + entry + '/start', {}));
      container.appendChild(callBtn);
    }
    if (['WAITING', 'CALLED'].includes(e.status)) {
      const recBtn = Pg.actionBtn('Recall', 'btn-outline', async () => {
        const res = await Api.post('/api/v1/operator/queue/' + entry + '/recall', {}, { handleMaintenance: false });
        if (res.ok) { toast(Locale.t('called'), 'success'); this.poll(this.host || document.body); }
        else toast(Api.errorMessage(res.data), 'error');
      });
      container.appendChild(recBtn);
    }
    if (['WAITING', 'CALLED'].includes(e.status)) {
      const skBtn = Pg.actionBtn('Skip', 'btn-outline', async () => {
        const want = await confirmDialog('Skip this token?', { danger: true });
        if (want) post('/api/v1/operator/queue/' + entry + '/skip', { reason: 'Skipped from portal' });
      });
      container.appendChild(skBtn);
      const nsBtn = Pg.actionBtn('No-show', 'btn-outline', async () => {
        const want = await confirmDialog('Mark as no-show?', { danger: true });
        if (want) post('/api/v1/operator/queue/' + entry + '/no-show', {});
      });
      container.appendChild(nsBtn);
    }
    if (e.status === 'IN_PROGRESS' && Auth.can('procurements.update')) {
      container.appendChild(Pg.actionBtn('Capture', 'btn-primary', () => this.captureProcurement(e)));
    }
    if (e.status === 'COMPLETED' && Auth.can('payments.release')) {
      container.appendChild(Pg.actionBtn('Release', 'btn-primary', () => this.releasePayment(e)));
    }
  },

  async captureProcurement(entry) {
    const listRes = await Api.get('/api/v1/operator/procurements' + U.qsFrom({ q: entry.booking_number, per_page: 5 }), { handleMaintenance: false });
    if (!listRes.ok) { toast(Api.errorMessage(listRes.data), 'error'); return; }
    const d = Api.unwrap(listRes.data);
    const items = d && d.items ? d.items : (Array.isArray(d) ? d : []);
    const p = items.find((x) => String(x.booking_number) === String(entry.booking_number));
    if (!p) { toast('Procurement not found for this entry', 'warn'); return; }
    if (p.status !== 'PENDING') { toast('Procurement is already ' + p.status, 'warn'); return; }
    const body = U.el('div', null, [
      U.el('p', { text: 'Capture weight & QC for ' + (p.procurement_number || '') + ' (' + (p.crop_name || '') + ')', style: 'margin-bottom:12px;color:var(--text-light)' }),
    ]);
    const grid = U.el('div', { class: 'form-grid' });
    const fWeight = Pg.input('Accepted weight (kg)', 'accepted_weight', '');
    const fDamage = Pg.input('Damaged qty (kg)', 'damaged_qty', '');
    const fGrade = Pg.select('Grade', 'grade', '', ['A', 'B', 'C', 'REJECT'].map((g) => ({ value: g, label: g })));
    const fMoisture = Pg.input('Moisture %', 'moisture_pct', '');
    const fSkills = Pg.textarea('Quality notes', 'quality_notes', '');
    const fOpNote = Pg.textarea('Operator note', 'operator_note', '');
    [fWeight, fDamage, fGrade, fMoisture, fSkills, fOpNote].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const saveBtn = U.el('button', { class: 'btn btn-primary', text: 'Save' });
    const submitBtn = U.el('button', { class: 'btn btn-outline', text: 'Save & Submit' });
    const modal = openModal({ title: 'Capture Weight — ' + entry.booking_number, size: 'lg', body, footer: [saveBtn, submitBtn] });
    const readPayload = () => ({
      accepted_weight: parseFloat(fWeight.querySelector('input').value),
      damaged_qty: parseFloat(fDamage.querySelector('input').value) || 0,
      grade: fGrade.querySelector('select').value,
      moisture_pct: parseFloat(fMoisture.querySelector('input').value) || 0,
      quality_notes: fSkills.querySelector('textarea').value || '',
      operator_note: fOpNote.querySelector('textarea').value || '',
    });
    const doUpdate = async (thenSubmit) => {
      clearFieldErrors(body);
      const payload = readPayload();
      if (isNaN(payload.accepted_weight)) { fieldError(fWeight.querySelector('input'), 'Required'); return; }
      saveBtn.disabled = true;
      submitBtn.disabled = true;
      const upd = await Api.put('/api/v1/operator/procurements/' + p.id, payload, { handleMaintenance: false });
      if (!upd.ok) { saveBtn.disabled = false; submitBtn.disabled = false; applyApiErrors(body, upd); return; }
      if (thenSubmit) {
        const sub = await Api.post('/api/v1/operator/procurements/' + p.id + '/submit', {}, { handleMaintenance: false });
        if (!sub.ok) { toast(Api.errorMessage(sub.data), 'error'); modal.close(); this.poll(this.host || document.body); return; }
        toast('Captured & submitted for approval', 'success');
      } else {
        toast('Saved', 'success');
      }
      modal.close();
      this.poll(this.host || document.body);
    };
    saveBtn.addEventListener('click', () => doUpdate(false));
    submitBtn.addEventListener('click', () => doUpdate(true));
  },

  async releasePayment(entry) {
    const listRes = await Api.get('/api/v1/operator/payments' + U.qsFrom({ q: entry.booking_number, status: 'PENDING', per_page: 5 }), { handleMaintenance: false });
    if (!listRes.ok) { toast(Api.errorMessage(listRes.data), 'error'); return; }
    const d = Api.unwrap(listRes.data);
    const items = Array.isArray(d) ? d : (d && d.items ? d.items : []);
    const pay = items.find((x) => String(x.booking_number) === String(entry.booking_number));
    if (!pay) { toast('No pending payment for this entry', 'warn'); return; }
    const body = U.el('div', null, [
      U.el('p', { text: 'Release ' + U.fmtMoney(pay.amount) + ' to ' + ((entry.farmer && entry.farmer.name) || 'farmer') + '?', style: 'margin-bottom:12px' }),
    ]);
    const grid = U.el('div', { class: 'form-grid' });
    const fMethod = Pg.select('Method', 'method', 'upi', ['upi', 'bank_transfer', 'cash'].map((m) => ({ value: m, label: m })));
    const fRef = Pg.input('Reference', 'payment_reference', '');
    grid.appendChild(fMethod);
    grid.appendChild(fRef);
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Release' });
    const modal = openModal({ title: 'Release Payment — ' + entry.booking_number, body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const res = await Api.put('/api/v1/operator/payments/' + pay.id + '/release', { method: fMethod.querySelector('select').value, payment_reference: fRef.querySelector('input').value }, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Released', 'success');
      modal.close();
      this.poll(this.host || document.body);
    });
  },

  async callNext(host) {
    const res = await Api.post('/api/v1/operator/queue/call-next', {}, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const d = Api.unwrap(res.data);
    if (d && d.message) toast(d.message, 'success');
    this.poll(host);
  },

  render(host) {
    this.host = host;
    const toolbar = U.el('div', { class: 'toolbar' });
    const centreSel = Pg.select('Centre', 'centre_id', this.state.centreId, [{ value: '', label: 'Select centre...' }]).querySelector('select');
    centreSel.disabled = true;

    const dateInput = U.el('input', { type: 'date', value: this.state.date, style: 'width:auto' });
    dateInput.addEventListener('change', async (e) => {
      this.state.date = e.target.value;
      if (this.state.centreId) await this.poll(host);
    });
    toolbar.appendChild(centreSel.closest('.field') || centreSel);
    toolbar.appendChild(U.el('label', { text: 'Date', style: 'margin:0' }));
    toolbar.appendChild(dateInput);
    const callNextBtn = U.el('button', { class: 'btn btn-primary', text: 'Call Next' });
    callNextBtn.addEventListener('click', () => this.callNext(host));
    toolbar.appendChild(callNextBtn);
    const refreshBtn = U.el('button', { class: 'btn btn-outline', text: Locale.t('retry') });
    refreshBtn.addEventListener('click', () => this.poll(host));
    toolbar.appendChild(refreshBtn);
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    toolbar.appendChild(U.el('span', { text: 'Auto-refresh 5s', style: 'font-size:12px;color:var(--text-light)' }));

    host.innerHTML = '';
    host.appendChild(toolbar);
    const stPanel = U.el('div', { class: 'panel' });
    stPanel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Queue Stats' })]));
    const stBody = U.el('div', { class: 'panel-body', id: 'queue-stats' });
    stPanel.appendChild(stBody);
    host.appendChild(stPanel);
    const qPanel = U.el('div', { class: 'panel' });
    qPanel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Live Queue' })]));
    const qBody = U.el('div', { class: 'panel-body', id: 'queue-entries' });
    qPanel.appendChild(qBody);
    host.appendChild(qPanel);

    Api.get('/api/v1/centres' + U.qsFrom({ status: 'ACTIVE', per_page: 100 }), { handleMaintenance: false }).then((res) => {
      if (!res.ok) { centreSel.disabled = false; return; }
      const list = Api.unwrap(res.data);
      const centres = Pg.itemsOf(list);
      centres.forEach((c) => {
        centreSel.appendChild(U.el('option', { value: String(c.id), text: c.name + ' (' + c.code + ')' }));
      });
      centreSel.disabled = false;
      if (!this.state.centreId && centres.length) {
        this.state.centreId = String(centres[0].id);
        centreSel.value = this.state.centreId;
      }
      if (this.state.centreId) this.poll(host);
    });
    centreSel.addEventListener('change', () => {
      this.state.centreId = centreSel.value;
      if (this.state.centreId) this.poll(host);
    });

    return {
      poll: (h) => this.poll(h),
      destroy: () => { this.host = null; },
    };
  },
};