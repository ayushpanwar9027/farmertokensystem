'use strict';

const SettingsPage = {
  state: { query: '' },

  async fetchAll() {
    const res = await Api.get('/api/v1/admin/settings', { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  paint(host, groups) {
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const qI = U.el('input', { placeholder: Locale.t('search'), style: 'width:auto' });
    toolbar.appendChild(qI);
    qI.addEventListener('input', U.debounce(() => {
      const term = qI.value.trim().toLowerCase();
      host.querySelectorAll('.setting-group').forEach((g) => {
        const show = term === '' || g.textContent.toLowerCase().includes(term);
        g.style.display = show ? '' : 'none';
      });
    }, 250));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('settings.manage')) toolbar.appendChild(U.el('span', { text: 'Changes save immediately on Save', style: 'font-size:12px;color:var(--text-light)' }));
    host.appendChild(toolbar);

    const pending = {};
    const edited = {};
    (groups || []).forEach((g) => {
      const panel = U.el('div', { class: 'panel setting-group' });
      panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: g.group })]));
      const body = U.el('div', { class: 'panel-body' });
      const grid = U.el('div', { class: 'form-grid' });
      g.settings.forEach((s) => {
        const wrap = U.el('div', { class: 'field' });
        const lbl = U.el('label', { text: s.key + (s.description ? ' — ' + s.description : ''), style: 'font-weight:400' });
        wrap.appendChild(lbl);
        if (s.sensitive) {
          wrap.appendChild(U.el('input', { type: 'password', value: s.exists ? '••••••••' : '', disabled: 'disabled', placeholder: s.exists ? 'Stored' : 'Not set' }));
        } else {
          let input;
          if (s.type === 'BOOL') {
            input = U.el('select', { 'data-key': s.key }, [
              U.el('option', { value: '1', label: 'Yes', selected: String(s.value) === '1' ? 'selected' : null }),
              U.el('option', { value: '0', label: 'No', selected: String(s.value) !== '1' ? 'selected' : null }),
            ]);
          } else if (s.type === 'JSON') {
            input = U.el('textarea', { 'data-key': s.key, rows: '3' });
            try { input.value = JSON.stringify(JSON.parse(s.value), null, 2); } catch (e) { input.value = s.value; }
          } else {
            input = U.el('input', { 'data-key': s.key, type: s.type === 'INT' ? 'number' : 'text', value: s.value !== null && s.value !== undefined ? String(s.value) : '' });
          }
          const disabled = !Auth.can('settings.manage');
          if (disabled) input.disabled = true;
          input.addEventListener('change', () => { edited[s.key] = true; });
          wrap.appendChild(input);
        }
        grid.appendChild(wrap);
      });
      body.appendChild(grid);
      if (Auth.can('settings.manage')) {
        const saveBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save'), onclick: async () => {
          saveBtn.disabled = true;
          const settings = {};
          U.qsa('[data-key]', body).forEach((el) => {
            const key = el.dataset.key;
            let val = el.value;
            if (el.tagName === 'SELECT') val = el.value === '1';
            if (el.tagName === 'TEXTAREA') { try { val = JSON.parse(el.value); } catch (e) {} }
            if (el.type === 'number') val = parseFloat(el.value);
            settings[key] = val;
          });
          const res = await Api.put('/api/v1/admin/settings', { settings }, { handleMaintenance: false });
          if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); saveBtn.disabled = false; return; }
          toast('Settings saved', 'success');
          saveBtn.disabled = false;
        } });
        panel.appendChild(U.el('div', { class: 'panel-body' }, [saveBtn]));
      }
      panel.appendChild(body);
      host.appendChild(panel);
    });
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const groups = await this.fetchAll();
    if (!groups) { host.innerHTML = ''; return; }
    this.paint(host, Array.isArray(groups) ? groups : groups.data);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};