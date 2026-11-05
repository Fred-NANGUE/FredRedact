import { useEffect, useState } from 'react';
import { Check, CircleAlert, LoaderCircle } from 'lucide-react';

export const CURRENCY = import.meta.env.VITE_CURRENCY || 'FCFA';

export const fmt = (n) => new Intl.NumberFormat('fr-FR').format(Number(n) || 0);
export const money = (n, currency = CURRENCY) => {
  const displayCurrency = currency || CURRENCY;
  const currencyCode = displayCurrency.toUpperCase();
  const isCfa = currencyCode === 'FCFA' || currencyCode === 'XAF';
  const amount = new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: isCfa ? 0 : 2,
    maximumFractionDigits: isCfa ? 0 : 2,
  }).format(Number(n) || 0);
  const symbol = currencyCode === 'EUR' ? '€' : displayCurrency;
  return `${amount} ${symbol}`;
};
export const date = (d) => (d ? new Date(d).toLocaleDateString('fr-FR') : '—');
export const datetime = (d) => (d ? new Date(d).toLocaleString('fr-FR') : '—');

export function Alert({ type = 'error', children }) {
  if (!children) return null;
  const Icon = type === 'success' ? Check : CircleAlert;
  return <div className={`alert alert-${type}`} role={type === 'error' ? 'alert' : 'status'}><Icon size={16} /> <span>{children}</span></div>;
}

export function Loading({ loading }) {
  return loading ? <p className="loading-line"><LoaderCircle className="spinner" size={17} /> Chargement…</p> : null;
}

export function UserAvatar({ name, photoUrl, className = '', label }) {
  const [imageFailed, setImageFailed] = useState(false);
  useEffect(() => setImageFailed(false), [photoUrl]);
  const initials = (name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => Array.from(part)[0])
    .filter(Boolean);
  const fallback = `${initials[0] || ''}${initials.length > 1 ? initials[initials.length - 1] : ''}`.toLocaleUpperCase('fr-FR') || '?';

  if (photoUrl && !imageFailed) {
    return (
      <img
        className={`avatar avatar-photo ${className}`.trim()}
        src={photoUrl}
        alt={label || ''}
        referrerPolicy="no-referrer"
        onError={() => setImageFailed(true)}
      />
    );
  }

  return <span className={`avatar ${className}`.trim()} aria-label={label}>{fallback}</span>;
}

const STATUS_CLASS = {
  actif: 'ok',
  en_attente: 'warn',
  suspendu: 'warn',
  expire: 'off',
  annule: 'off',
};

export function StatusBadge({ statut }) {
  return <span className={`badge badge-${STATUS_CLASS[statut] || 'off'}`}>{statut?.replace('_', ' ')}</span>;
}

export function Gauge({ value, max }) {
  const pct = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;
  const level = pct < 15 ? 'low' : pct < 40 ? 'mid' : 'high';
  return (
    <div className="gauge" role="progressbar" aria-valuenow={Math.round(pct)} aria-valuemin="0" aria-valuemax="100">
      <div className={`gauge-fill gauge-${level}`} style={{ width: `${pct}%` }} />
    </div>
  );
}

export function Pager({ page, last, onChange }) {
  if (!last || last <= 1) return null;
  return (
    <div className="pager">
      <button className="btn btn-light" disabled={page <= 1} onClick={() => onChange(page - 1)}>← Précédent</button>
      <span className="muted">Page {page} / {last}</span>
      <button className="btn btn-light" disabled={page >= last} onClick={() => onChange(page + 1)}>Suivant →</button>
    </div>
  );
}
