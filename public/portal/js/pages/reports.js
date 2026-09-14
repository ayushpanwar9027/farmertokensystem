'use strict';

const ReportsPage = {
  datasets: [],
  async load(host) {
    host.innerHTML = '';
    const intro = U.el('div', { class: 'panel' });
    intro.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Reports' })]));
    intro.appendChild(U.el('div', { class: 'panel-body' }, [
      U.el('p', { text: 'Generate CSV exports from live data. Pick a report, apply filters, then download.', style: 'color:var(--text-light);margin-bottom:16px' }),
    ]));
    host.appendChild(intro);

    const reportDefs = [];
    const add = (key, title, endpoint, filters, columns, rowsFn, perm) => {
      if (perm && !Auth.can(perm)) return;
      reportDefs.push({ key, title, endpoint, filters, columns, rowsFn });
    };
    add('payments', 'Payments', '/api/v1/operator/payments', [['status', 'Status'], ['date', 'Date'], ['q', 'Search']], ['Proc', 'Farmer', 'Mobile', 'Crop', 'Weight', 'Rate', 'Amount', 'Status', 'Method', 'Released'], (p) => [p.procurement_number, p.farmer ? p.farmer.name : '', p.farmer ? p.farmer.mobile : '', p.crop_name, p.accepted_weight, p.rate_per_kg, p.amount, p.status, p.payment_method, p.released_at], 'payments.view');
    add('procurements', 'Procurements', '/api/v1/operator/procurements', [['status', 'Status'], ['date', 'Date'], ['q', 'Search']], ['Proc', 'Booking', 'Farmer', 'Crop', 'Accepted', 'Grade', 'Amount', 'Status'], (p) => [p.procurement_number, p.booking_number, p.farmer ? p.farmer.name : '', p.crop_name, p.accepted_weight, p.grade, p.approved_amount, p.status], 'procurements.view_any');
    add('bookings', 'Bookings', '/api/v1/admin/bookings', [['status', 'Status'], ['from', 'From'], ['to', 'To']], ['Booking', 'Date', 'Status', 'Centre', 'Slot', 'Qty'], (b) => [b.booking_number, b.date, b.status, b.centre ? b.centre.name : '', b.slot ? b.slot.start_time + '-' + b.slot.end_time : '', b.total_quantity_kg], 'bookings.view_any');
    add('centres', 'Centres', '/api/v1/centres', [['status', 'Status'], ['q', 'Search']], ['Name', 'Code', 'District', 'Status', 'Phone'], (c) => [c.name, c.code, c.district ? c.district.name : '', c.status, c.contact_phone], 'centres.view');

    reportDefs.forEach((def) => {
      const panel = U.el('div', { class: 'panel' });
      panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: def.title })]));
      const body = U.el('div', { class: 'panel-body' });
      const grid = U.el('div', { class: 'filter-form' });
      const inputs = {};
      def.filters.forEach(([name, label]) => {
        const wrap = U.el('div', { class: 'field' });
        wrap.appendChild(U.el('label', { text: label, for: 'rep-' + def.key + '-' + name }));
        const input = U.el('input', { id: 'rep-' + def.key + '-' + name, name, type: ['date', 'from', 'to'].includes(name) ? 'date' : 'text' });
        inputs[name] = input;
        wrap.appendChild(input);
        grid.appendChild(wrap);
      });
      const status = U.el('div', { style: 'font-size:13px;color:var(--text-light)' });
      const runBtn = U.el('button', { class: 'btn btn-primary', text: 'Generate & Download' });
      runBtn.addEventListener('click', async () => {
        runBtn.disabled = true;
        status.textContent = Locale.t('loading');
        const filters = {};
        Object.entries(inputs).forEach(([name, input]) => { filters[name] = input.value; });
        const q = U.qsFrom(filters);
        const res = await Api.get(def.endpoint + q, { handleMaintenance: false });
        if (!res.ok) {
          status.textContent = Api.errorMessage(res.data, 'Error');
          runBtn.disabled = false;
          return;
        }
        const d = Api.unwrap(res.data);
        let rows;
        if (Array.isArray(d)) rows = d;
        else if (d && d.items) rows = d.items;
        else if (d && d.data) rows = d.data;
        else rows = [];
        Pg.exportCsv(def.key + '.csv', def.columns, rows.map(def.rowsFn));
        status.textContent = rows.length + ' rows exported';
        runBtn.disabled = false;
      });
      body.appendChild(grid);
      const row = U.el('div', { class: 'toolbar' });
      row.appendChild(runBtn);
      row.appendChild(status);
      body.appendChild(row);
      panel.appendChild(body);
      host.appendChild(panel);
    });

    if (reportDefs.length === 0) {
      host.appendChild(Pg.infoBanner('No reports available for your role.'));
    }
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};