export const BASE = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';
export const LOGIN_PATH = '/auth/login';

let token = localStorage.getItem('token') || '';

export const getToken = () => token;
export const setToken = (value) => {
  token = value || '';
  if (token) localStorage.setItem('token', token);
  else localStorage.removeItem('token');
};

export class ApiError extends Error {
  constructor(message, status, data) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

function headers(accept = 'application/json') {
  return {
    Accept: accept,
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

async function request(method, path, body) {
  const form = body instanceof FormData;
  let res;
  try {
    res = await fetch(BASE + path, {
      method,
      headers: {
        ...headers(),
        ...(body !== undefined && !form ? { 'Content-Type': 'application/json' } : {}),
      },
      body: body === undefined ? undefined : (form ? body : JSON.stringify(body)),
    });
  } catch {
    throw new ApiError("Impossible de joindre l'API. Le serveur Laravel est-il lancé ?", 0, null);
  }

  let data = null;
  try {
    data = await res.json();
  } catch {
    data = null;
  }

  if (!res.ok) {
    let message = data?.message || `Erreur ${res.status}`;
    if (data?.errors) message = Object.values(data.errors).flat().join(' ');
    throw new ApiError(message, res.status, data);
  }
  return data;
}

async function downloadPost(path, body) {
  let res;
  try {
    res = await fetch(BASE + path, {
      method: 'POST',
      headers: { ...headers('application/octet-stream'), 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
  } catch {
    throw new ApiError("Impossible de joindre l'API. Le serveur Laravel est-il lancé ?", 0, null);
  }
  if (!res.ok) {
    const data = await res.json().catch(() => null);
    throw new ApiError(data?.message || `Erreur ${res.status}`, res.status, data);
  }

  const blob = await res.blob();
  const filename = res.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i)?.[1] || 'rapport';
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function downloadGet(path) {
  let res;
  try {
    res = await fetch(BASE + path, { headers: headers('application/octet-stream') });
  } catch {
    throw new ApiError("Impossible de joindre l'API. Le serveur Laravel est-il lancé ?", 0, null);
  }
  if (!res.ok) {
    const data = await res.json().catch(() => null);
    throw new ApiError(data?.message || `Erreur ${res.status}`, res.status, data);
  }
  const blob = await res.blob();
  const filename = res.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i)?.[1] || 'document';
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body ?? {}),
  put: (path, body) => request('PUT', path, body ?? {}),
  del: (path) => request('DELETE', path),
  upload: (path, form) => request('POST', path, form),
  downloadGet,
  downloadPost,
};
