const config = window.OFFICE_STOCK_CONFIG || {};
export const API_BASE_URL = String(config.API_BASE_URL || '').replace(/\/+$/, '');

const TOKEN_KEY = 'officestock_session_token';

export function getToken() {
  return sessionStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  if (token) sessionStorage.setItem(TOKEN_KEY, token);
  else sessionStorage.removeItem(TOKEN_KEY);
}

export async function api(path, options = {}) {
  if (!API_BASE_URL) {
    throw new Error('API_BASE_URL is not configured. Set it in Vercel and rebuild.');
  }

  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');
  const token = getToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);

  let body = options.body;
  if (body !== undefined && body !== null && !(body instanceof FormData) && typeof body !== 'string') {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    body
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok || !payload?.ok) {
    const error = new Error(payload?.error?.message || `Request failed (${response.status}).`);
    error.status = response.status;
    error.code = payload?.error?.code || 'HTTP_ERROR';
    error.details = payload?.error?.details || null;
    throw error;
  }

  return payload.data;
}

export function qs(params = {}) {
  const out = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') out.set(key, String(value));
  });
  const text = out.toString();
  return text ? `?${text}` : '';
}
