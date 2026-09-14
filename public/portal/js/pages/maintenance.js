'use strict';

const MaintenancePage = {
  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const res = await Api.get('/api/v1/admin/settings', { handleMaintenance: false });
    if (!res.ok) { host.innerHTML = ''; host.appendChild(Pg.errorBanner(Api.errorMessage(res.data))); return; }
    const groups = Api.unwrap(res.data);
    const m = {};
    ((Array.isArray(groups) ? groups : groups.data) || []).forEach((g) => {
      g.settings.forEach((s) => { m[s.key] = s; });
    });
    const enabled = m['maintenance_mode'] && m['maintenance_mode'].value;
    const msg = m['maintenance_message'] ? m['maintenance_message'].value : '';
    const eta = m['maintenance_expected_available_at'] ? m['maintenance_expected_available_at'].value : '';
    host.innerHTML = '';
    host.appendChild(Pg.infoBanner('Current maintenance mode: ' + (enabled ? 'ON' : 'OFF')));
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Toggle Maintenance Mode' })]));
    const body = U.el('div', { class: 'panel-body' });
    const grid = U.el('div', { class: 'form-grid' });
    const fOn = Pg.select('Mode', 'enabled', enabled ? '1' : '0', [['1', 'Enabled'], ['0', 'Disabled']].map(([v, l]) => ({ value: v, label: l })));
    const fMsg = Pg.input('Message', 'message', msg || '');
    const fEta = Pg.input('Expected available at', 'expected_available_at', eta || '');
    grid.appendChild(fOn);
    grid.appendChild(fMsg);
    grid.appendChild(fEta);
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Apply' });
    body.appendChild(okBtn);
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const payload = { enabled: fOn.querySelector('select').value === '1' };
      const message = fMsg.querySelector('input').value.trim();
      const expected = fEta.querySelector('input').value.trim();
      if (message) payload.message = message;
      if (expected) payload.expected_available_at = expected;
      const upd = await Api.put('/api/v1/admin/maintenance', payload, { handleMaintenance: false });
      if (!upd.ok) { toast(Api.errorMessage(upd.data), 'error'); okBtn.disabled = false; return; }
      toast('Maintenance updated', 'success');
      okBtn.disabled = false;
      this.load(host);
    });
    panel.appendChild(body);
    host.appendChild(panel);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};