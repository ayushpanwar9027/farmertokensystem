'use strict';

const SecretsPage = {
  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const res = await Api.get('/api/v1/admin/secrets', { handleMaintenance: false });
    if (!res.ok) { host.innerHTML = ''; host.appendChild(Pg.errorBanner(Api.errorMessage(res.data))); return; }
    const d = Api.unwrap(res.data);
    const secrets = (d && d.secrets) || [];
    host.innerHTML = '';
    if (!Auth.can('settings.secret_manage')) {
      host.appendChild(Pg.warnBanner('Read-only view'));
    }
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Secrets' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!secrets.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Key', render: (r) => U.esc(r.key) },
          { title: 'Status', render: (r) => (r.exists ? '<span class="badge badge-green">Set</span>' : '<span class="badge badge-grey">Not set</span>') },
          { title: 'Last 4', render: (r) => U.esc(r.last4 || '—') },
          { title: 'Updated', render: (r) => U.esc(U.fmtDateTime(r.updated_at)) },
        ],
        rows: secrets,
      }));
    }
    panel.appendChild(pbody);
    host.appendChild(panel);
    if (Auth.can('settings.secret_manage')) {
      const formPanel = U.el('div', { class: 'panel' });
      formPanel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Update Secret' })]));
      const body = U.el('div', { class: 'panel-body' });
      const grid = U.el('div', { class: 'form-grid' });
      const fKey = Pg.select('Key', 'key', '', secrets.map((s) => ({ value: s.key, label: s.key })));
      const fValue = Pg.input('Value', 'value', '', 'password');
      grid.appendChild(fKey);
      grid.appendChild(fValue);
      body.appendChild(grid);
      const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
      body.appendChild(okBtn);
      okBtn.addEventListener('click', async () => {
        const key = fKey.querySelector('select').value;
        const value = fValue.querySelector('input').value;
        if (!key || !value) { toast('Key and value required', 'warn'); return; }
        okBtn.disabled = true;
        const upd = await Api.put('/api/v1/admin/secrets', { key, value }, { handleMaintenance: false });
        if (!upd.ok) { toast(Api.errorMessage(upd.data), 'error'); okBtn.disabled = false; return; }
        toast('Secret stored', 'success');
        fValue.querySelector('input').value = '';
        this.load(host);
      });
      formPanel.appendChild(body);
      host.appendChild(formPanel);
    }
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};