import { useState } from 'react';
import { Download, FileDown, Trash2, Upload } from 'lucide-react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading, date, fmt } from '../ui.jsx';

export default function Documents() {
  const documents = useLoad(() => api.get('/documents'));
  const reports = useLoad(() => api.get('/reports'));
  const [file, setFile] = useState(null);
  const [reportId, setReportId] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [exportFormat, setExportFormat] = useState('pdf');
  const [exportReportId, setExportReportId] = useState('');

  const upload = async (event) => {
    event.preventDefault();
    if (!file) return;
    setBusy(true);
    setError('');
    setSuccess('');
    const form = new FormData();
    form.append('file', file);
    if (reportId) form.append('rapport_id', reportId);
    try {
      await api.upload('/documents', form);
      setFile(null);
      event.target.reset();
      setSuccess('Document importé et texte extrait.');
      documents.reload();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  const remove = async (item) => {
    if (!window.confirm(`Supprimer « ${item.nom_original} » ?`)) return;
    try {
      await api.del(`/documents/${item.id}`);
      documents.reload();
    } catch (e) {
      setError(e.message);
    }
  };

  const exportReport = async (event) => {
    event.preventDefault();
    if (!exportReportId) return;
    setBusy(true);
    setError('');
    try {
      await api.downloadPost(`/reports/${exportReportId}/export`, { format: exportFormat });
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <h1>Documents et exportation</h1>
      <Alert>{error || reports.error || documents.error}</Alert>
      <Alert type="success">{success}</Alert>
      <div className="grid">
        <form className="card" onSubmit={upload}>
          <h3>Importer un document</h3>
          <p className="muted">PDF, Word (.docx) ou image (JPG, PNG, WebP), 15 Mo maximum.</p>
          <label>Fichier<input type="file" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" onChange={(e) => setFile(e.target.files?.[0] || null)} required /></label>
          <label>Associer à un rapport
            <select value={reportId} onChange={(e) => setReportId(e.target.value)}>
              <option value="">Aucun rapport</option>
              {(reports.data || []).map((report) => <option key={report.id} value={report.id}>{report.titre}</option>)}
            </select>
          </label>
          <button className="btn" disabled={busy || !file}><Upload size={15} /> {busy ? 'Traitement…' : 'Importer et extraire le texte'}</button>
        </form>

        <form className="card" onSubmit={exportReport}>
          <h3>Exporter un rapport final</h3>
          <label>Rapport
            <select value={exportReportId} onChange={(e) => setExportReportId(e.target.value)} required>
              <option value="">Sélectionner un rapport</option>
              {(reports.data || []).map((report) => <option key={report.id} value={report.id}>{report.titre}</option>)}
            </select>
          </label>
          <label>Format
            <select value={exportFormat} onChange={(e) => setExportFormat(e.target.value)}>
              <option value="pdf">PDF</option><option value="docx">Word (.docx)</option>
            </select>
          </label>
          <button className="btn" disabled={busy || !exportReportId}><FileDown size={15} /> Télécharger l’export</button>
        </form>
      </div>

      <section className="card">
        <h3>Documents importés</h3>
        <Loading loading={documents.loading} />
        {(documents.data || []).map((item) => (
          <details className="document-item" key={item.id}>
            <summary>{item.nom_original} <span className="muted">· {fmt(item.taille_bytes / 1024)} Ko · {date(item.created_at)}</span></summary>
            <p className="muted">Rapport : {item.report?.titre || 'non associé'}</p>
            <div className="row-left">
              <button className="btn btn-light" onClick={async () => {
                try { await api.downloadGet(`/documents/${item.id}/download`); }
                catch (e) { setError(e.message); }
              }}><Download size={14} /> Télécharger l’original</button>
              <button className="btn btn-danger" onClick={() => remove(item)}><Trash2 size={14} /> Supprimer</button>
            </div>
            <pre className="extracted-text">{item.texte_extrait || 'Aucun texte extrait.'}</pre>
          </details>
        ))}
        {!documents.loading && documents.data?.length === 0 && <p className="muted">Aucun document importé.</p>}
      </section>
    </>
  );
}
