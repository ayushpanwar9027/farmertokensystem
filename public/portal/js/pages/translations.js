'use strict';

const TranslationsPage = {
  state: { locale: 'en', q: '', page: 1 },

  async fetchRaw() {
    const q = U.qsFrom({ locale: this.state.locale, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    return Api.get('/api/v1/admin/translations' + q, { handleMaintenance: false });
  },

  async editInline(host, row) {
    if (!Auth.can('translations.manage')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: row.key, style: 'font-weight:600;margin-bottom:12px' }));
    const fValue = Pg.textarea('Value (' + this.state.locale + ')', 'value', row.value);
    body.appendChild(fValue);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: 'Edit Translation', size: 'lg', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const val = fValue.querySelector('textarea').value;
      const payload = { translations: {} };
      payload.translations[row.key] = val;
      const res = await Api.put('/api/v1/admin/languages/' + this.state.locale + '/translations', payload, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); okBtn.disabled = false; return; }
      toast('Saved', 'success');
      modal.close();
      this.render(host);
    });
  },

  async importCsv(host) {
    if (!Auth.can('translations.manage')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Import JSON or CSV file for locale ' + this.state.locale, style: 'margin-bottom:12px;color:var(--text-light)' }));
    const fileInput = U.el('input', { type: 'file', accept: '.json,.csv' });
    body.appendChild(fileInput);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Import' });
    const modal = openModal({ title: 'Import Translations', size: 'sm', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      const f = fileInput.files && fileInput.files[0];
      if (!f) { toast('Choose a file', 'warn'); return; }
      const res = await Api.upload('/api/v1/admin/translations/import?' + U.qsFrom({ locale: this.state.locale }), f);
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      const d = Api.unwrap(res.data);
      toast('Imported: updated ' + (d ? d.updated : '?') + ', skipped ' + (d ? d.skipped : '?') + ', invalid ' + (d ? d.invalid : '?'), 'success');
      modal.close();
      this.render(host);
    });
  },

  paint(host, translations, pg, languages) {
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const langSel = Pg.select('', 'locale', this.state.locale, languages.map((l) => ({ value: l, label: l })));
    toolbar.appendChild(langSel);
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.locale = langSel.querySelector('select').value;
      this.state.q = qI.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('translations.view')) toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'Export', onclick: async () => {
      const res = await Api.get('/api/v1/admin/translations/export' + U.qsFrom({ locale: this.state.locale }), { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      const map = Api.unwrap(res.data);
      const rows = Object.entries(map || {});
      Pg.exportCsv('translations-' + this.state.locale + '.csv', ['Key', 'Value'], rows.map(([k, v]) => [k, v]));
    } }));
    if (Auth.can('translations.manage')) toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'Import', onclick: () => this.importCsv(host) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Translations (' + this.state.locale + ')' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!translations.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Key', render: (r) => U.esc(r.key) },
          { title: 'Value', render: (r) => U.esc((r.value || '').slice(0, 80)) },
          { title: 'Source', render: (r) => U.esc(r.source || '') },
          { title: 'Override', render: (r) => (r.is_override ? '<span class="badge badge-blue">override</span>' : '<span class="badge badge-grey">base</span>') },
          { title: '', render: (r) => (Auth.can('translations.manage') ? Pg.actionBtn('Edit', 'btn-outline', () => this.editInline(host, r)) : '') },
        ],
        rows: translations,
      }));
    }
    panel.appendChild(pbody);
    if (pg) panel.appendChild(renderPagination(pg, (n) => { this.state.page = n; this.load(host); }));
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const langsRes = await Api.get('/api/v1/languages', { handleMaintenance: false });
    const langs = langsRes.ok ? (Api.unwrap(langsRes.data) || []) : ['en', 'hi'];
    const res = await this.fetchRaw();
    if (!res || !res.ok) { host.innerHTML = ''; return; }
    const list = Api.unwrap(res.data);
    let pg = null;
    if (res.data && res.data.meta && res.data.meta.pagination) pg = res.data.meta.pagination;
    this.paint(host, Pg.itemsOf(list), pg, langs);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};