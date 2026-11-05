import { useState } from 'react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Gauge, Loading, Pager, StatusBadge, date, datetime, fmt, money } from '../ui.jsx';

const TYPES = ['', 'allocation', 'recharge', 'generation', 'humanisation', 'dialogue', 'webographie', 'plan', 'ajustement'];

export default function Account() {
  const [page, setPage] = useState(1);
  const [type, setType] = useState('');
  const [message, setMessage] = useState({ type: '', text: '' });

  const current = useLoad(() => api.get('/billing/subscription'));
  const history = useLoad(
    () => api.get(`/billing/tokens/history?page=${page}${type ? `&type=${type}` : ''}`),
    [page, type]
  );
  const byReport = useLoad(() => api.get('/billing/tokens/by-report'));
  const subs = useLoad(() => api.get('/billing/subscriptions?per_page=5'));

  const sub = current.data?.subscription;
  const restants = current.data?.tokens_restants ?? 0;

  const cancel = async () => {
    if (!window.confirm("Annuler votre abonnement ? Les tokens restants seront retirés et vous repasserez sur l'offre Free.")) return;
    try {
      const res = await api.post('/billing/subscription/cancel');
      setMessage({ type: 'success', text: res.message });
      current.reload();
      history.reload();
      subs.reload();
    } catch (e) {
      setMessage({ type: 'error', text: e.message });
    }
  };

  return (
    <>
      <h1>Mon compte</h1>
      <Alert type={message.type || 'error'}>{message.text}</Alert>
      <Alert>{current.error}</Alert>
      <Loading loading={current.loading} />

      {current.data && (
        <div className="card">
          <div className="row">
            <div>
              <h2>{sub ? sub.plan?.nom : 'Aucun abonnement actif'}</h2>
              {sub && (
                <p className="muted">
                  <StatusBadge statut={sub.statut} /> Depuis le {date(sub.date_debut)}
                  {sub.date_fin ? ` · expire le ${date(sub.date_fin)} (${current.data.jours_restants} j restants)` : ' · sans limite de durée'}
                </p>
              )}
            </div>
            {sub && sub.plan?.nom?.toLowerCase() !== 'free' && (
              <button className="btn btn-danger" onClick={cancel}>Annuler l'abonnement</button>
            )}
          </div>

          <p className="big">{fmt(restants)} <span className="muted">tokens restants</span></p>
          <Gauge value={restants} max={sub?.tokens_alloues || 0} />
          <p className="muted">
            {fmt(current.data.tokens_consommes)} tokens consommés au total
            {sub ? ` · ${fmt(sub.tokens_alloues)} alloués avec cette offre` : ''}
          </p>
        </div>
      )}

      <div className="card">
        <h3>Consommation par rapport</h3>
        <Alert>{byReport.error}</Alert>
        {byReport.data?.length === 0 && <p className="muted">Aucune consommation enregistrée pour l'instant.</p>}
        {byReport.data?.length > 0 && (
          <table>
            <thead><tr><th>Rapport</th><th className="num">Tokens utilisés</th></tr></thead>
            <tbody>
              {byReport.data.map((r) => (
                <tr key={r.rapport_id}><td>{r.titre}</td><td className="num">{fmt(r.tokens_utilises)}</td></tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <div className="card">
        <div className="row">
          <h3>Historique des tokens</h3>
          <select value={type} onChange={(e) => { setType(e.target.value); setPage(1); }}>
            {TYPES.map((t) => <option key={t} value={t}>{t || 'Tous les types'}</option>)}
          </select>
        </div>
        <Alert>{history.error}</Alert>
        <Loading loading={history.loading} />
        {history.data && (
          <>
            <table>
              <thead><tr><th>Date</th><th>Type</th><th>Description</th><th className="num">Quantité</th></tr></thead>
              <tbody>
                {history.data.data.map((m) => (
                  <tr key={m.id}>
                    <td>{datetime(m.created_at)}</td>
                    <td>{m.type_operation}</td>
                    <td>{m.description || '—'}</td>
                    <td className={`num ${m.quantite < 0 ? 'neg' : 'pos'}`}>{m.quantite > 0 ? '+' : ''}{fmt(m.quantite)}</td>
                  </tr>
                ))}
                {history.data.data.length === 0 && <tr><td colSpan="4" className="muted">Aucun mouvement.</td></tr>}
              </tbody>
            </table>
            <Pager page={history.data.current_page} last={history.data.last_page} onChange={setPage} />
          </>
        )}
      </div>

      <div className="card">
        <h3>Mes derniers abonnements</h3>
        <Alert>{subs.error}</Alert>
        {subs.data && (
          <table>
            <thead><tr><th>Offre</th><th>Statut</th><th>Début</th><th>Fin</th><th className="num">Prix payé</th></tr></thead>
            <tbody>
              {subs.data.data.map((s) => (
                <tr key={s.id}>
                  <td>{s.plan?.nom}</td><td><StatusBadge statut={s.statut} /></td>
                  <td>{date(s.date_debut)}</td><td>{date(s.date_fin)}</td><td className="num">{money(s.prix_paye, s.devise)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
