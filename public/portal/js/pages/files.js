'use strict';

const FilesPage = {
  state: { scope: '', q: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ scope: this.state.scope || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/admin/files' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  async openUpload() {
    if (!Auth.can('files.upload')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    const f1 = Pg.input('Scope', 'scope', '');
    const f2 = Pg.input('Entity category', 'entity_category', '');
    const f3 = Pg.input('Entity id', 'entity_id', '');
    const f4 = U.el('div', { class: 'field' });
    f4.appendChild(U.el('label', { text: 'File', for: 'up-file' }));
    const file = U.el('input', { id: 'up-file', type: 'file', accept: '.jpg,.jpeg,.png,.webp,.pdf,.csv,.xlsx' });
    f4.appendChild(file);
    const grid = U.el('div', { class: 'form-grid' });
    [f1, f2, f3, f4].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Upload' });
    const modal = openModal({ title: 'Upload File', size: 'sm', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      const f = file.files && file.files[0];
      if (!f) { toast('Choose a file', 'warn'); return; }
      okBtn.disabled = true;
      const extra = {};
      const scope = f1.querySelector('input').value.trim();
      const cat = f2.querySelector('input').value.trim();
      const id = f3.querySelector('input').value.trim();
      if (scope) extra.scope = scope;
      if (cat) extra.entity_category = cat;
      if (id) extra.entity_id = id;
      const res = await Api.upload('/api/v1/files/upload', f, extra);
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); okBtn.disabled = false; return; }
      toast('Uploaded', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async deleteFile(f) {
    const want = await confirmDialog('Delete file ' + f.file_name + '?', { danger: true });
    if (!want) return;
    const res = await Api.del('/api/v1/admin/files/' + f.id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Deleted', 'success');
    this.render(this.host);
  },

  paint(host, list, pg) {
    const items = (list && list.data) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.q = qI.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('files.upload')) toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ Upload', onclick: () => this.openUpload() }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('files.csv', ['Name', 'Type', 'Size', 'Scope', 'Uploaded'], items.map((f) => [f.file_name, f.mime_type, f.size_human, f.scope, f.created_at])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Files' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Name', render: (r) => U.esc(r.file_name) },
          { title: 'Type', render: (r) => U.esc(r.mime_type || '') },
          { title: 'Size', render: (r) => U.esc(r.size_human || '') },
          { title: 'Scope', render: (r) => U.esc(r.scope || '') },
          { title: 'Folder', render: (r) => U.esc(r.folder_name || '') },
          { title: 'Uploaded', render: (r) => U.esc(U.fmtDateTime(r.created_at)) },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            if (r.url && Auth.can('files.download')) span.appendChild(U.el('a', { class: 'btn btn-outline btn-sm', href: r.download_url || r.url, target: '_blank', text: 'View' }));
            if (Auth.can('files.manage_all')) span.appendChild(Pg.actionBtn('Del', 'btn-danger', () => this.deleteFile(r)));
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