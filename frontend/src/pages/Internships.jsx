import { useState } from 'react';
import { FilePlus2, Pencil, Save, Trash2, X } from 'lucide-react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading, date } from '../ui.jsx';

const EMPTY = {
  titre: '', entreprise: '', poste: '', adresse_entreprise: '', encadrant_nom: '',
  encadrant_email: '', date_debut: '', date_fin: '', theme: '', problematique: '',
};
const DATE_FIELDS = new Set(['date_debut', 'date_fin']);

export default function Internships({ onOpenReports }) {
  const internships = useLoad(() => api.get('/internships'));
  const [form, setForm] = useState(EMPTY);
  const [editingId, setEditingId] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const set = (key) => (event) => setForm((value) => ({ ...value, [key]: event.target.value }));
  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setSuccess('');
    try {
      if (editingId) await api.put(`/internships/${editingId}`, form);
      else await api.post('/internships', form);
      setSuccess(editingId ? 'Stage mis à jour.' : 'Stage enregistré.');
      setForm(EMPTY);
      setEditingId(null);
      internships.reload();
    } catch (e) {
      setError(e.message);
    }
  };

  const edit = (internship) => {
    setEditingId(internship.id);
    setForm(Object.fromEntries(Object.keys(EMPTY).map((key) => [
      key,
      internship[key]
        ? DATE_FIELDS.has(key) ? String(internship[key]).slice(0, 10) : String(internship[key])
        : '',
    ])));
  };

  const remove = async (internship) => {
    if (!window.confirm(`Supprimer le stage « ${internship.titre} » ?`)) return;
    try {
      await api.del(`/internships/${internship.id}`);
      setSuccess('Stage supprimé.');
      internships.reload();
    } catch (e) {
      setError(e.message);
    }
  };

  const createReport = async (internship) => {
    try {
      await api.post(`/internships/${internship.id}/reports`, {});
      setSuccess('Rapport créé.');
      onOpenReports();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <>
      <h1>Mes stages</h1>
      <Alert>{error || internships.error}</Alert>
      <Alert type="success">{success}</Alert>

      <form className="card" onSubmit={submit}>
        <h3>{editingId ? 'Modifier le stage' : 'Enregistrer un stage'}</h3>
        <div className="form-grid">
          <label>Intitulé<input value={form.titre} onChange={set('titre')} required maxLength="180" /></label>
          <label>Entreprise<input value={form.entreprise} onChange={set('entreprise')} required maxLength="180" /></label>
          <label>Poste occupé<input value={form.poste} onChange={set('poste')} maxLength="180" /></label>
          <label>Adresse de l’entreprise<input value={form.adresse_entreprise} onChange={set('adresse_entreprise')} maxLength="255" /></label>
          <label>Nom de l’encadrant<input value={form.encadrant_nom} onChange={set('encadrant_nom')} maxLength="180" /></label>
          <label>Email de l’encadrant<input type="email" value={form.encadrant_email} onChange={set('encadrant_email')} /></label>
          <label>Date de début<input type="date" value={form.date_debut} onChange={set('date_debut')} /></label>
          <label>Date de fin<input type="date" value={form.date_fin} onChange={set('date_fin')} min={form.date_debut || undefined} /></label>
        </div>
        <label>Thème du stage<textarea rows="2" value={form.theme} onChange={set('theme')} maxLength="10000" /></label>
        <label>Problématique<textarea rows="2" value={form.problematique} onChange={set('problematique')} maxLength="10000" /></label>
        <div className="row-left">
          <button className="btn">{editingId ? <><Save size={16} /> Enregistrer les modifications</> : <><FilePlus2 size={16} /> Enregistrer le stage</>}</button>
          {editingId && <button type="button" className="btn btn-light" onClick={() => { setEditingId(null); setForm(EMPTY); }}><X size={15} /> Annuler</button>}
        </div>
      </form>

      <Loading loading={internships.loading} />
      <div className="grid">
        {(internships.data || []).map((item) => (
          <article className="card" key={item.id}>
            <h2>{item.titre}</h2>
            <p><strong>{item.entreprise}</strong>{item.poste ? ` · ${item.poste}` : ''}</p>
            <p className="muted">{item.date_debut ? date(item.date_debut) : 'Date non définie'}{item.date_fin ? ` – ${date(item.date_fin)}` : ''}</p>
            {item.theme && <p><strong>Thème :</strong> {item.theme}</p>}
            {item.problematique && <p><strong>Problématique :</strong> {item.problematique}</p>}
            <div className="row-left">
              <button className="btn" onClick={() => createReport(item)}><FilePlus2 size={15} /> Créer un rapport</button>
              <button className="btn btn-light" onClick={() => edit(item)}><Pencil size={14} /> Modifier</button>
              <button className="btn btn-danger" onClick={() => remove(item)}><Trash2 size={14} /> Supprimer</button>
            </div>
          </article>
        ))}
        {!internships.loading && internships.data?.length === 0 && <p className="muted">Aucun stage enregistré.</p>}
      </div>
    </>
  );
}
