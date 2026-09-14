'use strict';

const Api = {
  base: window.FPS.apiBase || '',
  rateLimitUntil: 0,

  isRateLimited() {
    return Date.now() < this.rateLimitUntil;
  },

  async call(method, path, body, opts) {
    if (this.isRateLimited()) {
      const secs = Math.ceil((this.rateLimitUntil - Date.now()) / 1000);
      return { ok: false, status: 429, data: { error: { message: 'Too many requests. Try again in ' + secs + 's.' } }, rateLimited: true };
    }
    const headers = { 'Accept': 'application/json' };
    let payload = null;
    if (body && body instanceof FormData === false) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    } else if (body instanceof FormData) {
      payload = body;
    }

    const csrf = document.cookie.split('; ').find((c) => c.startsWith('csrf_token='));
    if (csrf) headers['X-CSRF-Token'] = decodeURIComponent(csrf.split('=')[1]);

    let res;
    try {
      res = await fetch(this.base + path, {
        method,
        headers,
        body: payload,
        credentials: 'same-origin',
      });
    } catch (e) {
      return { ok: false, network: true };
    }

    let json = null;
    try { json = await res.json(); } catch (e) { /* no body */ }

    if (res.status === 503 && opts && opts.handleMaintenance === false) {
      return { ok: false, status: 503, data: json };
    }
    if (res.status === 503) {
      this.onMaintenance(json);
      return { ok: false, status: 503, data: json };
    }
    if (res.status === 401) {
      this.onUnauthorized(json);
      return { ok: false, status: 401, data: json };
    }
    if (res.status === 429) {
      const retryAfter = parseInt(res.headers.get('Retry-After') || '30', 10);
      this.rateLimitUntil = Date.now() + (retryAfter * 1000);
      this.onRateLimit(retryAfter);
      return { ok: false, status: 429, data: json, rateLimited: true, retryAfter };
    }

    return {
      ok: res.ok,
      status: res.status,
      data: json,
    };
  },

  get(path, opts) { return this.call('GET', path, null, opts); },
  post(path, body, opts) { return this.call('POST', path, body, opts); },
  put(path, body, opts) { return this.call('PUT', path, body, opts); },
  patch(path, body, opts) { this.call('PATCH', path, body, opts); },
  del(path, opts) { return this.call('DELETE', path, null, opts); },

  async upload(path, file, extraFields) {
    const form = new FormData();
    form.append('file', file);
    if (extraFields) for (const [k, v] of Object.entries(extraFields)) form.append(k, v);
    return this.call('POST', path, form);
  },

  unwrap(json) {
    if (!json) return null;
    return json.success ? json.data : null;
  },

  errorMessage(json, fallback) {
    if (!json) return fallback || Locale.t('networkError');
    if (json.error && json.error.message) {
      const det = json.error.details;
      if (det && typeof det === 'object') {
        const first = Object.values(det)[0];
        if (Array.isArray(first)) return first[0];
        if (typeof first === 'string') return first;
      }
      return json.error.message;
    }
    return fallback || Locale.t('serverError');
  },

  errorDetails(json) {
    if (!json || !json.error || !json.error.details) return null;
    const det = json.error.details;
    if (typeof det !== 'object' || Array.isArray(det)) return null;
    const out = {};
    Object.entries(det).forEach(([k, v]) => {
      out[k] = (Array.isArray(v) ? v.join(', ') : String(v));
    });
    return out;
  },

  onUnauthorized() {},
  onMaintenance() {},
  onRateLimit() {},
};