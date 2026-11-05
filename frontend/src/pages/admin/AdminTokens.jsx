import { useEffect, useState } from 'react';
import { api } from '../../api.js';
import { Alert, Loading, Pager, datetime, fmt } from '../../ui.jsx';

export default function AdminTokens({ initialUserId = '' }) {
  const [userId, setUserId] = useState(initialUserId);
  const [loadedId, setLoadedId] = useState(initialUserId);
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });
  const [credit, setCredit] = useState({ quantite: '', description: '' });
  const [adjust, setAdjust] = useState({ quantite: '', description: '' });

  const load = async (id = loadedId, p = page) => {
    if (!id) return;
    setLoading(true);
    try {
      setData(await api.get(`/admin/billing/users/${id}/tokens?page=${p}`));
      setMessage((m) => (m.type === 'error' ? { type: '', text: '' } : m));
    } catch (e) {
      setData(null);
      setMessage({ type: 'error', text: e.message });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load(loadedId, page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadedId, page]);

  const search = (e) => {
    e.preventDefault();
    setPage(1);
    setLoadedId(userId);
  };

  const send = (kind, form, reset) => async (e) => {
    e.preventDefault();
    try {
      await api.post(`/admin/billing/users/${loadedId}/tokens/${kind}`, {
        quantite: Number(form.quantite),
        description: form.description || undefined,
      });
      setMessage({ type: 'success', text: kind === 'credit' ? 'Tokens crédités.' : 'Ajustement enregistré.' });
      reset({ quantite: '', description: '' });
      load(loadedId, 1);
      setPage(1);
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  return (
    <>
      <h1>Tokens d'un utilisateur</h1>
      <Alert type={message.type || 'error'}>{message.text}</Alert>

      <form className="card inline" onSubmit={search}>
        <label>ID utilisateur<input type="number" min="1" value={userId} onChange={(e) => setUserId(e.target.value)} required /></label>
        <button className="btn">Afficher</button>
      </form>

      <Loading loading={loading} />

      {data && (
        <>
          <div className="card">
            <h2>{data.user.name}</h2>
            <p className="muted">{data.user.email}</p>
            <p className="big">{fmt(data.tokens_restants)} <span className="muted">tokens restants</span></p>
            <p className="muted">{fmt(data.tokens_consommes)} tokens consommés au total</p>
          </div>

          <div className="grid">
            <form className="card" onSubmit={send('credit', credit, setCredit)}>
              <h3>Recharger</h3>
              <label>Quantité<input type="number" min="1" value={credit.quantite} onChange={(e) => setCredit({ ...credit, quantite: e.target.value })} required /></label>
              <label>Motif<input value={credit.description} onChange={(e) => setCredit({ ...credit, description: e.target.value })} placeholder="Recharge manuelle (admin)" /></label>
              <button className="btn">Créditer</button>
            </form>

            <form className="card" onSubmit={send('adjust', adjust, setAdjust)}>
              <h3>Ajuster (± correction)</h3>
              <label>Quantité (négative pour retirer)<input type="number" value={adjust.quantite} onChange={(e) => setAdjust({ ...adjust, quantite: e.target.value })} required /></label>
              <label>Motif (obligatoire)<input value={adjust.description} onChange={(e) => setAdjust({ ...adjust, description: e.target.value })} required /></label>
              <button className="btn btn-light">Enregistrer l'ajustement</button>
            </form>
          </div>

          <div className="card">
            <h3>Historique</h3>
            <table>
              <thead><tr><th>Date</th><th>Type</th><th>Description</th><th className="num">Quantité</th></tr></thead>
              <tbody>
                {data.historique.data.map((m) => (
                  <tr key={m.id}>
                    <td>{datetime(m.created_at)}</td><td>{m.type_operation}</td><td>{m.description || '—'}</td>
                    <td className={`num ${m.quantite < 0 ? 'neg' : 'pos'}`}>{m.quantite > 0 ? '+' : ''}{fmt(m.quantite)}</td>
                  </tr>
                ))}
                {data.historique.data.length === 0 && <tr><td colSpan="4" className="muted">Aucun mouvement.</td></tr>}
              </tbody>
            </table>
            <Pager page={data.historique.current_page} last={data.historique.last_page} onChange={setPage} />
          </div>
        </>
      )}
    </>
  );
}
