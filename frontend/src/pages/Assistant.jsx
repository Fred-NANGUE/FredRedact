import { useEffect, useMemo, useRef, useState } from 'react';
import { Bot, LoaderCircle, Send, Sparkles } from 'lucide-react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading } from '../ui.jsx';

const STARTER_QUESTIONS = [
  'Aide-moi à préciser ma problématique',
  'Quelles informations dois-je encore recueillir ?',
  'Comment organiser mon introduction ?',
];

export default function Assistant({ initialReportId = '' }) {
  const reports = useLoad(() => api.get('/reports'));
  const [reportId, setReportId] = useState(initialReportId ? String(initialReportId) : '');
  const selectedReport = (reports.data || []).find((item) => String(item.id) === reportId);
  const report = useLoad(
    () => reportId ? api.get(`/reports/${reportId}`) : Promise.resolve(null),
    [reportId]
  );
  const history = useLoad(
    () => api.get(`/ai/chat${reportId ? `?rapport_id=${reportId}` : ''}`),
    [reportId]
  );
  const savedSources = useLoad(
    () => reportId ? api.get(`/reports/${reportId}/webographies`) : Promise.resolve([]),
    [reportId]
  );
  const [question, setQuestion] = useState('');
  const [messages, setMessages] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [sources, setSources] = useState([]);
  const [sectionId, setSectionId] = useState('');
  const [researchQuestion, setResearchQuestion] = useState('');
  const messagesEndRef = useRef(null);
  const questionInputRef = useRef(null);

  useEffect(() => {
    setMessages(history.data || []);
  }, [history.data]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: busy ? 'smooth' : 'auto', block: 'end' });
  }, [messages, busy]);

  const sections = useMemo(() => (report.data?.sections || []), [report.data]);
  const allSections = useMemo(
    () => sections.flatMap((section) => [section, ...(section.children || [])]),
    [sections]
  );

  const send = async (event) => {
    event.preventDefault();
    const text = question.trim();
    if (!text || busy || history.loading) return;
    setQuestion('');
    setError('');
    setBusy(true);
    setMessages((items) => [...items, { question: text, pending: true }]);
    try {
      const data = await api.post('/ai/chat', { question: text, rapport_id: reportId || null });
      setMessages((items) => [...items.filter((item) => !item.pending), data.dialogue]);
    } catch (e) {
      setMessages((items) => items.filter((item) => !item.pending));
      setQuestion(text);
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  const chooseStarterQuestion = (text) => {
    setQuestion(text);
    questionInputRef.current?.focus();
  };

  const handleQuestionKeyDown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault();
      event.currentTarget.form?.requestSubmit();
    }
  };

  const generatePlan = async () => {
    if (!reportId) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const data = await api.post('/ai/plan', { rapport_id: Number(reportId) });
      setNotice(`Plan créé (${data.tokens_consommes} tokens). Retrouvez les chapitres dans « Mes rapports ».`);
      report.reload();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  const generateSection = async () => {
    if (!reportId || !sectionId) return;
    setBusy(true);
    setError('');
    try {
      const data = await api.post('/ai/generate', { rapport_id: Number(reportId), section_id: Number(sectionId) });
      setNotice(`Section rédigée (${data.tokens_consommes} tokens).`);
      report.reload();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  const humanizeSection = async () => {
    if (!reportId || !sectionId) return;
    setBusy(true);
    setError('');
    try {
      const data = await api.post('/ai/humanize', { rapport_id: Number(reportId), section_id: Number(sectionId) });
      setNotice(`Texte clarifié sans ajout d’informations (${data.tokens_consommes} tokens).`);
      report.reload();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  const searchSources = async (event) => {
    event.preventDefault();
    if (!reportId) return;
    setBusy(true);
    setError('');
    try {
      const data = await api.post('/ai/research', { rapport_id: Number(reportId), question: researchQuestion });
      setSources(data.sources);
      setNotice(`${data.sources.length} source(s) proposée(s). Vérifiez leur pertinence avant de les citer.`);
      savedSources.reload();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <h1>Assistant de rédaction</h1>
      <Alert>{error || reports.error || report.error || history.error}</Alert>
      <Alert type="success">{notice}</Alert>
      <div className="card">
        <label>Rapport concerné
          <select value={reportId} onChange={(e) => { setReportId(e.target.value); setSources([]); }}>
            <option value="">Discussion générale</option>
            {(reports.data || []).map((item) => <option key={item.id} value={item.id}>{item.titre}</option>)}
          </select>
        </label>
        <p className="chat-context-note">
          {selectedReport
            ? <>Les réponses tiendront compte du rapport <strong>{selectedReport.titre}</strong>. Vérifiez toujours les informations proposées.</>
            : 'Discussion générale : choisissez un rapport pour donner son contexte à l’assistant.'}
        </p>
        {reportId && (
          <div className="ai-tools">
            <button className="btn btn-light" disabled={busy} onClick={generatePlan}>Générer le plan</button>
            <select value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
              <option value="">Choisir une section</option>
              {allSections.map((section) => <option key={section.id} value={section.id}>{section.titre}</option>)}
            </select>
            <button className="btn btn-light" disabled={busy || !sectionId} onClick={generateSection}>Rédiger la section</button>
            <button className="btn btn-light" disabled={busy || !sectionId} onClick={humanizeSection}>Améliorer le texte</button>
          </div>
        )}
      </div>

      <div className="card chat-card">
        <div className="chat-heading">
          <div className="chat-heading-icon"><Bot size={19} /></div>
          <div><h3>Votre assistant</h3><p>{selectedReport ? selectedReport.titre : 'Discussion générale'}</p></div>
        </div>
        <Loading loading={history.loading} />
        <div className="chat-messages" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Conversation avec l’assistant">
          {messages.map((item, index) => (
            <article className="chat-exchange" key={item.id || `pending-${index}`}>
              <div className="chat-question"><span className="chat-speaker">Vous</span><p>{item.question}</p></div>
              <div className="chat-answer">
                <span className="chat-speaker"><Bot size={14} /> Assistant</span>
                {item.pending
                  ? <p className="chat-thinking"><LoaderCircle className="spinner" size={15} /> Préparation de la réponse…</p>
                  : <p>{item.reponse}</p>}
              </div>
            </article>
          ))}
          {!history.loading && messages.length === 0 && (
            <div className="chat-welcome">
              <span className="chat-welcome-icon"><Sparkles size={20} /></span>
              <strong>Par quoi souhaitez-vous commencer ?</strong>
              <p>Je peux vous aider à organiser vos idées à partir des informations que vous fournissez, sans inventer votre expérience.</p>
              <div className="chat-starters">
                {STARTER_QUESTIONS.map((starter) => (
                  <button type="button" className="chat-starter" key={starter} onClick={() => chooseStarterQuestion(starter)}>{starter}</button>
                ))}
              </div>
            </div>
          )}
          <div ref={messagesEndRef} />
        </div>
        <form className="chat-form" onSubmit={send}>
          <div className="chat-input-wrap">
            <textarea
              ref={questionInputRef}
              rows="2"
              value={question}
              onChange={(e) => setQuestion(e.target.value)}
              onKeyDown={handleQuestionKeyDown}
              placeholder="Écrivez votre question…"
              aria-label="Votre question à l’assistant"
              maxLength="12000"
              required
            />
            <span className="chat-input-hint">Entrée pour envoyer · Maj+Entrée pour un saut de ligne</span>
          </div>
          <button className="btn chat-send" disabled={busy || history.loading || !question.trim()} aria-label={busy ? 'Réponse en cours' : 'Envoyer le message'}>
            {busy ? <LoaderCircle className="spinner" size={17} /> : <Send size={17} />}
            <span>{busy ? 'En cours…' : 'Envoyer'}</span>
          </button>
        </form>
      </div>

      {reportId && (
        <form className="card" onSubmit={searchSources}>
          <h3>Recherche documentaire et webographie</h3>
          <p className="muted">Les sources sont des propositions : contrôlez-les avant de les citer dans votre rapport.</p>
          <div className="inline">
            <label>Sujet de recherche<input value={researchQuestion} onChange={(e) => setResearchQuestion(e.target.value)} required maxLength="4000" /></label>
            <button className="btn" disabled={busy}>Rechercher des sources</button>
          </div>
          {(savedSources.data || sources).map((source) => (
            <div className="source-item" key={source.id || source.url}>
              <a href={source.url} target="_blank" rel="noreferrer">{source.titre}</a>
              <p>{source.resume}</p>
              <small className="muted">{source.lien_verifie ? 'Lien accessible · ' : 'Lien non vérifié · '}{source.url}</small>
              {source.id && <button type="button" className="btn btn-light" onClick={async (event) => {
                event.preventDefault();
                try {
                  await api.del(`/reports/${reportId}/webographies/${source.id}`);
                  savedSources.reload();
                } catch (e) {
                  setError(e.message);
                }
              }}>Retirer</button>}
            </div>
          ))}
        </form>
      )}
    </>
  );
}
