import { useState } from 'react';
import { Bot, FilePlus2, History, Pencil, Plus, RotateCcw, Save, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading } from '../ui.jsx';

export default function Reports({ onOpenAssistant }) {
  const reports = useLoad(() => api.get('/reports'));
  const internships = useLoad(() => api.get('/internships'));
  const [selectedId, setSelectedId] = useState(null);
  const selected = useLoad(
    () => selectedId ? api.get(`/reports/${selectedId}`) : Promise.resolve(null),
    [selectedId]
  );
  const [title, setTitle] = useState('');
  const [internshipId, setInternshipId] = useState('');
  const [newSectionTitle, setNewSectionTitle] = useState('');
  const [parentId, setParentId] = useState('');
  const [editing, setEditing] = useState(null);
  const [versions, setVersions] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const refresh = () => {
    reports.reload();
    selected.reload();
  };

  const createReport = async (event) => {
    event.preventDefault();
    setError('');
    try {
      const report = await api.post('/reports', { titre: title, internship_id: internshipId || null });
      setTitle('');
      setInternshipId('');
      setSelectedId(report.id);
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  const createSection = async (event) => {
    event.preventDefault();
    if (!selectedId) return;
    try {
      await api.post(`/reports/${selectedId}/sections`, {
        titre: newSectionTitle,
        type: parentId ? 'sous_section' : 'chapitre',
        parent_id: parentId || null,
      });
      setNewSectionTitle('');
      setParentId('');
      setSuccess('Section ajoutée.');
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  const saveSection = async (section) => {
    try {
      await api.put(`/reports/${selectedId}/sections/${section.id}`, {
        titre: section.titre, contenu: section.contenu || '',
      });
      setEditing(null);
      setSuccess('Modifications enregistrées.');
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  const loadVersions = async (section) => {
    try {
      const data = await api.get(`/reports/${selectedId}/sections/${section.id}/versions`);
      setVersions(data.data.map((version) => ({ ...version, sectionId: section.id })));
    } catch (e) {
      setError(e.message);
    }
  };

  const restore = async (version) => {
    try {
      await api.post(`/reports/${selectedId}/sections/${version.sectionId}/versions/${version.id}/restore`);
      setVersions([]);
      refresh();
      setSuccess('Version restaurée.');
    } catch (e) {
      setError(e.message);
    }
  };

  const removeReport = async (report) => {
    if (!window.confirm(`Supprimer le rapport « ${report.titre} » et son contenu ?`)) return;
    try {
      await api.del(`/reports/${report.id}`);
      if (selectedId === report.id) setSelectedId(null);
      reports.reload();
    } catch (e) {
      setError(e.message);
    }
  };

  const sections = selected.data?.sections || [];
  const roots = sections.filter((section) => !section.parent_id);

  return (
    <>
      <h1>Mes rapports</h1>
      <Alert>{error || reports.error || internships.error || selected.error}</Alert>
      <Alert type="success">{success}</Alert>
      <form className="card inline" onSubmit={createReport}>
        <label>Titre du rapport<input value={title} onChange={(e) => setTitle(e.target.value)} required maxLength="180" /></label>
        <label>Stage associé
          <select value={internshipId} onChange={(e) => setInternshipId(e.target.value)}>
            <option value="">Aucun stage</option>
            {(internships.data || []).map((item) => <option key={item.id} value={item.id}>{item.titre}</option>)}
          </select>
        </label>
        <button className="btn"><FilePlus2 size={16} /> Créer un rapport</button>
      </form>

      <div className="grid">
        <div className="card">
          <h3>Rapports enregistrés</h3>
          <Loading loading={reports.loading} />
          {(reports.data || []).map((report) => (
            <div className="report-list-item" key={report.id}>
              <button className={`link ${selectedId === report.id ? 'selected-link' : ''}`} onClick={() => { setSelectedId(report.id); setVersions([]); }}>
                {report.titre}
              </button>
              <span className="muted">{report.progression || 0}%</span>
              <button className="link" aria-label="Supprimer le rapport" onClick={() => removeReport(report)}>×</button>
            </div>
          ))}
          {!reports.loading && reports.data?.length === 0 && <p className="muted">Aucun rapport créé.</p>}
        </div>

        {selected.data && (
          <div className="card">
            <div className="row">
              <div><h2>{selected.data.titre}</h2><p className="muted">{selected.data.progression}% complété</p></div>
              <button className="btn btn-light" onClick={() => onOpenAssistant(selected.data.id)}><Bot size={16} /> Ouvrir l’assistant IA</button>
            </div>
            <div className="progress-track"><div style={{ width: `${selected.data.progression || 0}%` }} /></div>

            <form className="inline" onSubmit={createSection}>
              <label>Ajouter un chapitre ou sous-chapitre<input value={newSectionTitle} onChange={(e) => setNewSectionTitle(e.target.value)} required maxLength="180" /></label>
              <label>Parent
                <select value={parentId} onChange={(e) => setParentId(e.target.value)}>
                  <option value="">Nouveau chapitre</option>
                  {roots.map((section) => <option key={section.id} value={section.id}>{section.titre}</option>)}
                </select>
              </label>
              <button className="btn"><Plus size={16} /> Ajouter</button>
            </form>

            {roots.map((section) => (
              <SectionEditor
                key={section.id}
                section={section}
                selectedId={selectedId}
                editing={editing}
                setEditing={setEditing}
                saveSection={saveSection}
                loadVersions={loadVersions}
                onSave={refresh}
                onDelete={async (id) => {
                  try { await api.del(`/reports/${selectedId}/sections/${id}`); refresh(); }
                  catch (e) { setError(e.message); }
                }}
              />
            ))}
            {versions.length > 0 && (
              <div className="card version-list">
                <h3>Versions précédentes</h3>
                {versions.map((version) => (
                  <div key={version.id}>
                    <span className="muted">{new Date(version.created_at).toLocaleString('fr-FR')}</span>
                    <p className="version-preview">{version.contenu || '(contenu vide)'}</p>
                    <button className="btn btn-light" onClick={() => restore(version)}><RotateCcw size={14} /> Restaurer cette version</button>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </>
  );
}

function SectionEditor({ section, selectedId, editing, setEditing, saveSection, loadVersions, onSave, onDelete }) {
  const active = editing?.id === section.id ? editing : section;
  return (
    <article className="section-editor">
      <div className="row">
        {editing?.id === section.id
          ? <input aria-label="Titre de la section" value={active.titre} onChange={(e) => setEditing({ ...active, titre: e.target.value })} />
          : <h3>{section.titre}</h3>}
        <div className="actions">
          {editing?.id === section.id
            ? <button className="btn" onClick={() => saveSection(active)}><Save size={14} /> Enregistrer</button>
            : <button className="btn btn-light" onClick={() => setEditing({ ...section })}><Pencil size={14} /> Modifier</button>}
          <button className="btn btn-light" onClick={() => loadVersions(section)}><History size={14} /> Historique</button>
          <button className="btn btn-danger" onClick={() => onDelete(section.id)}><Trash2 size={14} /> Supprimer</button>
        </div>
      </div>
      {editing?.id === section.id
        ? <textarea rows="7" value={active.contenu || ''} onChange={(e) => setEditing({ ...active, contenu: e.target.value })} />
        : <p className="section-preview">{section.contenu || 'Aucun contenu pour le moment.'}</p>}
      {(section.children || []).map((child) => (
        <div className="subsection" key={child.id}>
          <h4>{child.titre}</h4>
          <p className="section-preview">{child.contenu || 'Aucun contenu pour le moment.'}</p>
          <button className="btn btn-light" onClick={async () => {
            const content = window.prompt('Modifier le contenu', child.contenu || '');
            if (content === null) return;
            try {
              await api.put(`/reports/${selectedId}/sections/${child.id}`, { titre: child.titre, contenu: content });
              onSave();
            } catch (e) {
              window.alert(e.message);
            }
          }}>Modifier le texte</button>
        </div>
      ))}
    </article>
  );
}
