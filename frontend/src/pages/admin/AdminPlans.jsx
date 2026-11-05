import { useState } from 'react';
import { api } from '../../api.js';
import { useLoad } from '../../hooks.js';
import { Alert, Loading, fmt, money } from '../../ui.jsx';

const EMPTY = { nom: '', description: '', prix: '0', tokens_inclus: '0', duree_jours: '', actif: true };

export default function AdminPlans() {
  const { data, error, loading, reload } = useLoad(() => api.get('/admin/billing/plans'));
  const [form, setForm] = useState(EMPTY);
  const [editingId, setEditingId] = useState(null);
  const [message, setMessage] = useState({ type: '', text: '' });

  const set = (key) => (e) =>
    setForm({ ...form, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  const edit = (plan) => {
    setEditingId(plan.id);
    setForm({
      nom: plan.nom,
      description: plan.description || '',
      prix: String(plan.prix),
      tokens_inclus: String(plan.tokens_inclus),
      duree_jours: plan.duree_jours ?? '',
      actif: !!plan.actif,
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const reset = () => {
    setEditingId(null);
    setForm(EMPTY);
  };

  const submit = async (e) => {
    e.preventDefault();
    setMessage({ type: '', text: '' });
    const payload = {
      nom: form.nom,
      description: form.description || null,
      prix: Number(form.prix),
      tokens_inclus: Number(form.tokens_inclus),
      duree_jours: form.duree_jours === '' ? null : Number(form.duree_jours),
      actif: form.actif,
    };
    try {
      if (editingId) await api.put(`/admin/billing/plans/${editingId}`, payload);
      else await api.post('/admin/billing/plans', payload);
      setMessage({ type: 'success', text: editingId ? 'Offre mise à jour.' : 'Offre créée.' });
      reset();
      reload();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  const remove = async (plan) => {
    if (!window.confirm(`Supprimer l'offre ${plan.nom} ? (elle sera seulement désactivée si des abonnements y sont rattachés)`)) return;
    try {
      const res = await api.del(`/admin/billing/plans/${plan.id}`);
      setMessage({ type: 'success', text: res.message });
      reload();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  const toggle = async (plan) => {
    try {
      await api.put(`/admin/billing/plans/${plan.id}`, { actif: !plan.actif });
      reload();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    }
  };

  return (
    <>
      <h1>Gestion des offres</h1>
      <Alert type={message.type || 'error'}>{message.text}</Alert>

      <form className="card" onSubmit={submit}>
        <h3>{editingId ? "Modifier l'offre" : 'Nouvelle offre'}</h3>
        <div className="form-grid">
          <label>Nom<input value={form.nom} onChange={set('nom')} required maxLength={50} /></label>
          <label>Prix (FCFA)<input type="number" min="0" step="1" value={form.prix} onChange={set('prix')} required /></label>
          <label>Tokens inclus<input type="number" min="0" value={form.tokens_inclus} onChange={set('tokens_inclus')} required /></label>
          <label>Durée en jours<input type="number" min="1" value={form.duree_jours} onChange={set('duree_jours')} placeholder="vide = sans limite" /></label>
        </div>
        <label>Description<textarea rows="2" value={form.description} onChange={set('description')} /></label>
        <label className="check"><input type="checkbox" checked={form.actif} onChange={set('actif')} /> Offre active (visible et souscriptible)</label>
        <div className="row-left">
          <button className="btn">{editingId ? 'Enregistrer' : 'Créer'}</button>
          {editingId && <button type="button" className="btn btn-light" onClick={reset}>Annuler</button>}
        </div>
        {editingId && <p className="muted">Les abonnements existants gardent leurs tokens et leur prix d'origine.</p>}
      </form>

      <div className="card">
        <Alert>{error}</Alert>
        <Loading loading={loading} />
        {data && (
          <table>
            <thead>
              <tr><th>Offre</th><th className="num">Prix</th><th className="num">Tokens</th><th>Durée</th><th className="num">Abonnés actifs</th><th>Statut</th><th /></tr>
            </thead>
            <tbody>
              {data.map((p) => (
                <tr key={p.id}>
                  <td><strong>{p.nom}</strong><br /><span className="muted">{p.description}</span></td>
                  <td className="num">{money(p.prix)}</td>
                  <td className="num">{fmt(p.tokens_inclus)}</td>
                  <td>{p.duree_jours ? `${p.duree_jours} j` : 'Illimitée'}</td>
                  <td className="num">{fmt(p.abonnes_actifs_count)}</td>
                  <td><span className={`badge badge-${p.actif ? 'ok' : 'off'}`}>{p.actif ? 'active' : 'inactive'}</span></td>
                  <td className="actions">
                    <button className="btn btn-light" onClick={() => edit(p)}>Modifier</button>
                    <button className="btn btn-light" onClick={() => toggle(p)}>{p.actif ? 'Désactiver' : 'Activer'}</button>
                    <button className="btn btn-danger" onClick={() => remove(p)}>Supprimer</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
