import { useState } from 'react';
import { api } from '../../api.js';
import { useLoad } from '../../hooks.js';
import { Alert, Loading, Pager, StatusBadge, date, money } from '../../ui.jsx';

const STATUTS = ['', 'actif', 'en_attente', 'suspendu', 'expire', 'annule'];

export default function AdminSubscriptions({ onManageTokens }) {
  const [statut, setStatut] = useState('');
  const [planId, setPlanId] = useState('');
  const [q, setQ] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [message, setMessage] = useState({ type: '', text: '' });
  const [assign, setAssign] = useState({ user_id: '', plan_id: '' });

  const plans = useLoad(() => api.get('/admin/billing/plans'));
  const subs = useLoad(() => {
    const params = new URLSearchParams({ page });
    if (statut) params.set('statut', statut);
    if (planId) params.set('plan_id', planId);
    if (search) params.set('q', search);
    return api.get(`/admin/billing/subscriptions?${params}`);
  }, [statut, planId, search, page]);

  const act = async (sub, action, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) return;
    try {
      await api.post(`/admin/billing/subscriptions/${sub.id}/${action}`);
      setMessage({ type: 'success', text: 'Action effectuée.' });
      subs.reload();
    } catch (e) {
      setMessage({ type: 'error', text: e.message });
    }
  };

  const doAssign = async (e) => {
    e.preventDefault();
    try {
      await api.post(`/admin/billing/users/${assign.user_id}/subscribe`, { plan_id: Number(assign.plan_id) });
      setMessage({ type: 'success', text: 'Offre attribuée.' });
      setAssign({ user_id: '', plan_id: '' });
      subs.reload();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  return (
    <>
      <h1>Abonnements</h1>
      <Alert type={message.type || 'error'}>{message.text}</Alert>

      <form className="card" onSubmit={doAssign}>
        <h3>Attribuer une offre à un utilisateur</h3>
        <div className="form-grid">
          <label>ID utilisateur<input type="number" min="1" value={assign.user_id} onChange={(e) => setAssign({ ...assign, user_id: e.target.value })} required /></label>
          <label>Offre
            <select value={assign.plan_id} onChange={(e) => setAssign({ ...assign, plan_id: e.target.value })} required>
              <option value="">Choisir…</option>
              {(plans.data || []).filter((p) => p.actif).map((p) => <option key={p.id} value={p.id}>{p.nom}</option>)}
            </select>
          </label>
        </div>
        <button className="btn">Attribuer</button>
      </form>

      <div className="card">
        <div className="filters">
          <select value={statut} onChange={(e) => { setStatut(e.target.value); setPage(1); }}>
            {STATUTS.map((s) => <option key={s} value={s}>{s ? s.replace('_', ' ') : 'Tous les statuts'}</option>)}
          </select>
          <select value={planId} onChange={(e) => { setPlanId(e.target.value); setPage(1); }}>
            <option value="">Toutes les offres</option>
            {(plans.data || []).map((p) => <option key={p.id} value={p.id}>{p.nom}</option>)}
          </select>
          <form onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1); }} className="inline">
            <input placeholder="Nom ou email…" value={q} onChange={(e) => setQ(e.target.value)} />
            <button className="btn btn-light">Rechercher</button>
          </form>
        </div>

        <Alert>{subs.error}</Alert>
        <Loading loading={subs.loading} />
        {subs.data && (
          <>
            <table>
              <thead>
                <tr><th>#</th><th>Utilisateur</th><th>Offre</th><th>Statut</th><th>Début</th><th>Fin</th><th className="num">Payé</th><th /></tr>
              </thead>
              <tbody>
                {subs.data.data.map((s) => (
                  <tr key={s.id}>
                    <td>{s.id}</td>
                    <td>{s.user?.name}<br /><span className="muted">#{s.user_id} · {s.user?.email}</span></td>
                    <td>{s.plan?.nom}</td>
                    <td><StatusBadge statut={s.statut} /></td>
                    <td>{date(s.date_debut)}</td>
                    <td>{date(s.date_fin)}</td>
                    <td className="num">{money(s.prix_paye, s.devise)}</td>
                    <td className="actions">
                      {s.statut === 'en_attente' && <button className="btn" onClick={() => act(s, 'activate', 'Confirmer le paiement et activer ?')}>Activer</button>}
                      {s.statut === 'actif' && <button className="btn btn-light" onClick={() => act(s, 'suspend', 'Suspendre cet abonnement ?')}>Suspendre</button>}
                      {s.statut === 'suspendu' && <button className="btn btn-light" onClick={() => act(s, 'reactivate')}>Réactiver</button>}
                      {['actif', 'suspendu', 'en_attente'].includes(s.statut) && (
                        <button className="btn btn-danger" onClick={() => act(s, 'cancel', "Annuler l'abonnement ? Les tokens restants seront retirés.")}>Annuler</button>
                      )}
                      <button className="btn btn-light" onClick={() => onManageTokens(s.user_id)}>Tokens</button>
                    </td>
                  </tr>
                ))}
                {subs.data.data.length === 0 && <tr><td colSpan="8" className="muted">Aucun abonnement.</td></tr>}
              </tbody>
            </table>
            <Pager page={subs.data.current_page} last={subs.data.last_page} onChange={setPage} />
          </>
        )}
      </div>
    </>
  );
}
