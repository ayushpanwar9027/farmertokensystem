'use strict';

const Auth = {
  user: null,
  permissions: [],

  async boot() {
    const res = await Api.get('/web/me', { handleMaintenance: false });
    if (res.ok) {
      this.applySession(Api.unwrap(res.data));
      return true;
    }
    if (res.status === 503) {
      this.renderMaintenance(Api.errorMessage(res.data, Locale.t('maintenanceMsg')));
      return false;
    }
    return false;
  },

  applySession(data) {
    this.user = data && data.user;
    this.permissions = (data && data.permissions) || [];
  },

  can(perm) {
    return this.permissions.includes(perm);
  },
  canAny(list) {
    return list.some((p) => this.can(p));
  },
  role() { return (this.user && this.user.role) || ''; },
  isSuperAdmin() { return !!(this.user && this.user.is_super_admin); },

  async login(username, password) {
    const res = await Api.post('/web/login', { username, password });
    if (!res.ok) return { ok: false, message: Api.errorMessage(res.data, Locale.t('invalidCredentials')) };
    const data = Api.unwrap(res.data);
    if (res.status === 202) {
      return { ok: false, twoFaRequired: true, data: data };
    }
    this.applySession({ user: data.user, permissions: data.permissions });
    return { ok: true, data };
  },

  async verify2fa(verificationId, otp) {
    const res = await Api.post('/web/verify-2fa', { verification_id: verificationId, otp });
    if (!res.ok) return { ok: false, message: Api.errorMessage(res.data, Locale.t('invalidOtp')) };
    const data = Api.unwrap(res.data);
    this.applySession({ user: data.user, permissions: data.permissions });
    return { ok: true, data };
  },

  async resend2fa(verificationId) {
    return Api.post('/api/v1/auth/resend-2fa', { verification_id: verificationId });
  },

  async logout() {
    await Api.post('/web/logout', {});
    this.user = null;
    this.permissions = [];
    Router.goTo('login');
  },

  renderLogin() {
    const wrap = U.el('div', { class: 'login-wrap' });
    const card = U.el('div', { class: 'login-card' });
    card.appendChild(U.el('div', { class: 'login-brand' }, [
      U.el('h1', { text: Locale.t('brand') }),
      U.el('p', { text: Locale.t('portal') }),
    ]));
    const errBox = U.el('div', { class: 'login-error', style: 'display:none' });
    card.appendChild(errBox);

    const fields = [];
    const field = (label, id, type, placeholder, autocomplete) => {
      const wrapEl = U.el('div', { class: 'field' });
      wrapEl.appendChild(U.el('label', { text: label, for: id }));
      const input = U.el('input', { id, type: type || 'text', placeholder, autocomplete });
      wrapEl.appendChild(input);
      fields.push(input);
      return wrapEl;
    };

    const username = field(Locale.t('username'), 'login-username', 'text', '', 'username');
    const password = field(Locale.t('password'), 'login-password', 'password', '', 'current-password');
    card.appendChild(username);
    card.appendChild(password);

    const submitBtn = U.el('button', { class: 'btn btn-primary', style: 'width:100%;justify-content:center', text: Locale.t('signIn') });
    card.appendChild(submitBtn);

    const langRow = U.el('div', { style: 'display:flex;justify-content:space-between;align-items:center;margin-top:18px' });
    langRow.appendChild(U.el('span', { text: Locale.t('locale'), style: 'font-size:13px;color:var(--text-light)' }));
    const langSel = U.el('select', { style: 'width:auto' }, [
      U.el('option', { value: 'en', text: Locale.t('english'), selected: Locale.current === 'en' ? 'selected' : null }),
      U.el('option', { value: 'hi', text: Locale.t('hindi'), selected: Locale.current === 'hi' ? 'selected' : null }),
    ]);
    langSel.addEventListener('change', () => {
      Locale.set(langSel.value);
      Router.refresh();
    });
    langRow.appendChild(langSel);
    card.appendChild(langRow);

    const state = { verificationId: null, submitting: false };
    const showErr = (msg) => { errBox.style.display = 'block'; errBox.textContent = msg; };
    const hideErr = () => { errBox.style.display = 'none'; };

    async function doLogin() {
      if (state.submitting) return;
      hideErr();
      const uname = username.value.trim();
      const pass = password.value;
      if (!uname || !pass) { showErr(Locale.t('errorRequired')); return; }
      state.submitting = true;
      submitBtn.disabled = true;
      const result = await Auth.login(uname, pass);
      state.submitting = false;
      submitBtn.disabled = false;
      if (result.ok) {
        Router.afterLogin();
        return;
      }
      if (result.twoFaRequired) {
        state.verificationId = result.data.verification_id;
        show2fa();
        return;
      }
      showErr(result.message);
    }

    function show2fa() {
      submitBtn.style.display = 'none';
      username.style.display = 'none';
      password.style.display = 'none';
      U.qsa('.field', card).forEach((f) => {
        if (f.querySelector('input') === username || f.querySelector('input') === password) f.style.display = 'none';
      });
      const otpInput = U.el('input', { id: 'login-otp', type: 'text', inputmode: 'numeric', placeholder: Locale.t('otp'), maxlength: '6', autocomplete: 'one-time-code' });
      const otpField = U.el('div', { class: 'field' });
      otpField.appendChild(U.el('label', { text: Locale.t('signIn2fa'), for: 'login-otp' }));
      otpField.appendChild(otpInput);
      card.insertBefore(otpField, submitBtn);
      const verifyBtn = U.el('button', { class: 'btn btn-primary', style: 'width:100%;justify-content:center', text: Locale.t('verify') });
      card.insertBefore(verifyBtn, submitBtn);
      const resendBtn = U.el('button', { class: 'btn btn-outline', style: 'width:100%;justify-content:center;margin-top:8px', text: Locale.t('resend') });
      card.insertBefore(resendBtn, langRow);

      async function doVerify() {
        if (state.submitting) return;
        hideErr();
        const code = otpInput.value.trim();
        if (!/^\d{6}$/.test(code)) { showErr(Locale.t('errorRequired')); return; }
        state.submitting = true;
        verifyBtn.disabled = true;
        const result = await Auth.verify2fa(state.verificationId, code);
        state.submitting = false;
        verifyBtn.disabled = false;
        if (result.ok) { Router.afterLogin(); return; }
        showErr(result.message);
      }

      async function doResend() {
        if (state.submitting) return;
        state.submitting = true;
        resendBtn.disabled = true;
        await Auth.resend2fa(state.verificationId);
        state.submitting = false;
        toast(Locale.t('resend') + ' ✓', 'success');
        startCooldown(30);
      }

      let cooldownTimer = null;
      function startCooldown(secs) {
        if (cooldownTimer) clearInterval(cooldownTimer);
        resendBtn.disabled = true;
        const tick = () => {
          if (secs <= 0) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
            resendBtn.disabled = false;
            if (resendBtn.firstChild) resendBtn.firstChild.textContent = Locale.t('resend');
            return;
          }
          if (resendBtn.firstChild) resendBtn.firstChild.textContent = Locale.t('resend') + ' (' + secs + 's)';
          secs--;
        };
        tick();
        cooldownTimer = setInterval(tick, 1000);
      }

      verifyBtn.addEventListener('click', doVerify);
      resendBtn.addEventListener('click', doResend);
      otpInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') doVerify(); });
      otpInput.focus();
      startCooldown(30);
    }

    submitBtn.addEventListener('click', doLogin);
    card.addEventListener('keydown', (e) => { if (e.key === 'Enter') doLogin(); });
    wrap.appendChild(card);
    return wrap;
  },

  renderMaintenance(message) {
    const wrap = U.el('div', { class: 'login-wrap' });
    const card = U.el('div', { class: 'login-card' }, [
      U.el('div', { class: 'login-brand', html: `<svg width="44" height="44" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#d4a017"/><path d="M7 15h4l3-7 6 13 3-6h2" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>` }),
      U.el('h1', { text: Locale.t('brand'), style: 'text-align:center;font-size:20px;color:var(--warning)' }),
      U.el('p', { text: message || Locale.t('maintenanceMsg'), style: 'text-align:center;color:var(--text-light);margin-top:12px' }),
      U.el('button', { class: 'btn btn-outline', style: 'width:100%;justify-content:center;margin-top:18px', text: Locale.t('retry'), onclick: () => Router.refresh() }),
    ]);
    wrap.appendChild(card);
    return wrap;
  },
};