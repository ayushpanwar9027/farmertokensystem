'use strict';

const DashboardPage = {
  render(host) {
    const wrap = U.el('div');
    const grid = U.el('div', { class: 'card-grid' });
    wrap.appendChild(grid);

    const addCard = (label, value, sub, accent) => {
      const card = U.el('div', { class: 'stat-card' });
      if (accent) card.style.borderLeft = '4px solid ' + accent;
      card.appendChild(U.el('div', { class: 'stat-label', text: label }));
      card.appendChild(U.el('div', { class: 'stat-value', text: String(value !== undefined ? value : '-') }));
      if (sub) card.appendChild(U.el('div', { class: 'stat-sub', text: sub }));
      grid.appendChild(card);
    };

    wrap.appendChild(Pg.loading(Locale.t('loading')));

    const quick = U.el('div', { style: 'margin-top:18px' });
    quick.appendChild(U.el('div', { class: 'panel-title', style: 'margin-bottom:10px', text: Locale.t('quickLinks').toUpperCase() }));
    const qc = U.el('div', { class: 'toolbar', style: 'background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px' });
    [
      ['queue', 'queue'],
      ['procurements', 'procurements'],
      ['payments', 'payments'],
      ['approvals', 'approvals'],
      ['bookings', 'bookings'],
      ['notifications', 'notifications'],
    ].forEach(([route]) => {
      if (!Router.canAccess(route)) return;
      const b = U.el('button', { class: 'btn btn-outline', text: Locale.t(route), onclick: () => Router.goTo(route) });
      qc.appendChild(b);
    });
    quick.appendChild(qc);
    wrap.appendChild(quick);

    const results = [];
    const push = (key, fn) => { results.push({ key, fn }); };

    if (Auth.can('notifications.view')) {
      push('notif', async () => {
        const res = await Api.get('/api/v1/admin/notifications/summary');
        if (!res.ok) return null;
        const sum = Api.unwrap(res.data);
        return sum && sum.summary;
      });
    }
    if (Auth.can('queue.stats') || Auth.can('view_queue') || Auth.can('queue.view')) {
      push('queue', async () => {
        const centres = await Api.get('/api/v1/centres' + U.qsFrom({ status: 'ACTIVE', per_page: 1 }), { handleMaintenance: false });
        let cid = '';
        if (centres.ok) {
          const first = Pg.itemsOf(Api.unwrap(centres.data))[0];
          if (first) cid = first.id;
        }
        if (!cid) return null;
        const res = await Api.get('/api/v1/operator/queue/stats' + U.qsFrom({ centre_id: cid, date: U.nowIso() }), { handleMaintenance: false });
        if (!res.ok) return null;
        const d = Api.unwrap(res.data);
        return d && d.stats;
      });
    }
    if (Auth.can('payments.view')) {
      push('payments', async () => {
        const res = await Api.get('/api/v1/operator/payments' + U.qsFrom({ status: 'PENDING', per_page: 5 }));
        if (!res.ok) return null;
        const d = Api.unwrap(res.data);
        const items = d && d.items ? d.items : (Array.isArray(d) ? d : []);
        return { pending: items.filter((p) => p.status === 'PENDING').length };
      });
    }
    if (Auth.can('procurements.view_any')) {
      push('proc', async () => {
        const res = await Api.get('/api/v1/operator/procurements' + U.qsFrom({ status: 'PENDING_APPROVAL', per_page: 5 }));
        if (!res.ok) return null;
        const d = Api.unwrap(res.data);
        const items = d && d.items ? d.items : [];
        return { pending: items.filter((p) => p.status === 'PENDING_APPROVAL').length };
      });
    }

    const load = async () => {
      wrap.innerHTML = '';
      grid.innerHTML = '';
      const resolved = await Promise.all(results.map((r) => r.fn()));
      let any = false;
      results.forEach((r, i) => {
        const v = resolved[i];
        if (v === null || v === undefined) return;
        any = true;
        if (r.key === 'notif') {
          addCard('Notifications - Today', (v.sent || 0) + (v.failed || 0) + (v.pending || 0), `Sent ${v.sent || 0} failed ${v.failed || 0} pending ${v.pending || 0}`, 'var(--info)');
        } else if (r.key === 'queue') {
          addCard('Queue - Waiting', v.waiting || 0, `Called ${v.called || 0} In progress ${v.in_progress || 0}`, 'var(--success)');
          addCard('Queue - Served Today', v.served_today || 0, `Avg wait ${Math.round(v.avg_wait_minutes || 0)} min`, 'var(--primary)');
        } else if (r.key === 'payments') {
          addCard('Payments - Pending Release', v.pending || 0, '', 'var(--warning)');
        } else if (r.key === 'proc') {
          addCard('Procurements - Awaiting Approval', v.pending || 0, '', 'var(--warning)');
        }
      });
      if (!any) {
        const c = U.el('div', { class: 'panel', style: 'padding:24px' }, [
          U.el('p', { text: 'Welcome, ' + ((Auth.user && Auth.user.name) || '') + '. Use the menu to get started.', style: 'font-size:16px' }),
        ]);
        grid.appendChild(c);
      }
      wrap.prepend(grid);
      wrap.appendChild(quick);
    };

    load().catch(() => { wrap.innerHTML = ''; wrap.appendChild(grid); });
    return {};
  },
};