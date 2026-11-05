import { useEffect, useMemo, useRef, useState } from 'react';
import {
  BookOpen, Bot, Check, ChevronDown, Copy, FileText, LoaderCircle,
  MessageCircle, Search, Send, ShieldCheck, Sparkles, WandSparkles,
} from 'lucide-react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading } from '../ui.jsx';

const STARTER_QUESTIONS = [
  { title: 'Clarifier mon sujet', prompt: 'Aide-moi à préciser ma problématique de stage. Pose-moi des questions si tu as besoin de détails.' },
  { title: 'Organiser mes idées', prompt: 'Aide-moi à organiser les idées et informations que je dois recueillir pour mon rapport.' },
  { title: 'Préparer mon introduction', prompt: 'Comment structurer une introduction pertinente pour mon rapport de stage ?' },
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
  const [toolsOpen, setToolsOpen] = useState(false);
  const [copiedMessageId, setCopiedMessageId] = useState(null);
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
    setNotice('');
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

  const copyResponse = async (message) => {
    try {
      await navigator.clipboard.writeText(message.reponse);
      setCopiedMessageId(message.id);
      window.setTimeout(() => setCopiedMessageId(null), 1800);
    } catch {
      setError('Impossible de copier la réponse. Vérifiez les autorisations du navigateur.');
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
    <section className="assistant-workspace" aria-label="Assistant IA">
      <header className="assistant-intro">
        <div>
          <span className="assistant-eyebrow"><Sparkles size={14} /> VOTRE ESPACE DE RÉDACTION</span>
          <h1>Faisons avancer votre rapport.</h1>
          <p>Un échange à la fois, avec le contexte de votre stage si vous le souhaitez.</p>
        </div>
        <div className="assistant-privacy"><ShieldCheck size={16} /><span>Vos échanges restent privés</span></div>
      </header>

      <Alert>{error || reports.error || report.error || history.error}</Alert>
      <Alert type="success">{notice}</Alert>

      <div className="assistant-context">
        <div className="assistant-context-label"><BookOpen size={16} /><span>CONTEXTE</span></div>
        <label className="sr-only" htmlFor="assistant-report">Rapport concerné</label>
        <select
          id="assistant-report"
          value={reportId}
          onChange={(event) => { setReportId(event.target.value); setSectionId(''); setSources([]); setError(''); }}
        >
          <option value="">Discussion générale</option>
          {(reports.data || []).map((item) => <option key={item.id} value={item.id}>{item.titre}</option>)}
        </select>
        <p>{selectedReport
          ? <>L’assistant prendra en compte <strong>{selectedReport.titre}</strong>.</>
          : 'Choisissez un rapport pour personnaliser les réponses.'}</p>
        {reportId && (
          <button type="button" className={`assistant-tools-toggle ${toolsOpen ? 'is-open' : ''}`} onClick={() => setToolsOpen((value) => !value)} aria-expanded={toolsOpen}>
            <WandSparkles size={15} /> Outils de rédaction <ChevronDown size={15} />
          </button>
        )}
      </div>

      {toolsOpen && reportId && (
        <div className="assistant-tools">
          <button className="btn btn-light" disabled={busy} onClick={generatePlan}>Générer le plan</button>
          <label className="sr-only" htmlFor="assistant-section">Section du rapport</label>
          <select id="assistant-section" value={sectionId} onChange={(event) => setSectionId(event.target.value)}>
            <option value="">Choisir une section</option>
            {allSections.map((section) => <option key={section.id} value={section.id}>{section.titre}</option>)}
          </select>
          <button className="btn btn-light" disabled={busy || !sectionId} onClick={generateSection}>Rédiger la section</button>
          <button className="btn btn-light" disabled={busy || !sectionId} onClick={humanizeSection}>Améliorer le texte</button>
        </div>
      )}

      <div className="assistant-layout">
        <aside className="assistant-conversations" aria-label="Discussions">
          <div className="assistant-list-heading"><span>VOS DISCUSSIONS</span><small>{(reports.data || []).length + 1}</small></div>
          <button type="button" className={`assistant-thread ${!reportId ? 'assistant-thread-active' : ''}`} onClick={() => { setReportId(''); setSectionId(''); setSources([]); }}>
            <span className="assistant-thread-icon"><MessageCircle size={16} /></span>
            <span className="assistant-thread-copy"><strong>Discussion générale</strong><small>Sans contexte de rapport</small></span>
          </button>
          {(reports.data || []).map((item) => (
            <button type="button" key={item.id} className={`assistant-thread ${String(item.id) === reportId ? 'assistant-thread-active' : ''}`} onClick={() => { setReportId(String(item.id)); setSectionId(''); setSources([]); }}>
              <span className="assistant-thread-icon"><FileText size={16} /></span>
              <span className="assistant-thread-copy"><strong>{item.titre}</strong><small>Discussion sur ce rapport</small></span>
            </button>
          ))}
          <div className="assistant-sidebar-note"><ShieldCheck size={15} /><span>Vos échanges sont associés à votre compte et à vos rapports.</span></div>
        </aside>

        <section className="assistant-chat-panel" aria-label="Conversation">
          <div className="assistant-chat-header">
            <span className="assistant-avatar"><Bot size={19} /></span>
            <div className="assistant-chat-title">
              <strong>FredRedact</strong>
              <span>{selectedReport ? selectedReport.titre : 'Assistant de rédaction'}</span>
            </div>
            <span className="assistant-online"><i /> En ligne</span>
          </div>

          <Loading loading={history.loading} />
          <div className="chat-messages" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Conversation avec l’assistant">
            {messages.map((item, index) => (
              <article className="chat-exchange" key={item.id || `pending-${index}`}>
                <div className="chat-question">
                  <span className="chat-speaker">Vous</span>
                  <p>{item.question}</p>
                </div>
                <div className="chat-answer">
                  <span className="chat-speaker"><Bot size={14} /> FredRedact</span>
                  {item.pending
                    ? <p className="chat-thinking"><LoaderCircle className="spinner" size={15} /> Préparation de la réponse…</p>
                    : (
                      <>
                        <div className="chat-markdown"><ReactMarkdown remarkPlugins={[remarkGfm]}>{item.reponse || ''}</ReactMarkdown></div>
                        <button type="button" className="chat-copy" onClick={() => copyResponse(item)} aria-label="Copier la réponse">
                          {copiedMessageId === item.id ? <Check size={14} /> : <Copy size={14} />}
                          {copiedMessageId === item.id ? 'Copié' : 'Copier'}
                        </button>
                      </>
                    )}
                </div>
              </article>
            ))}
            {!history.loading && messages.length === 0 && (
              <div className="chat-welcome">
                <span className="chat-welcome-icon"><Sparkles size={21} /></span>
                <strong>Bonjour, que souhaitez-vous préparer ?</strong>
                <p>Je peux vous aider à réfléchir et à structurer vos idées. Vous restez maître du contenu et des faits de votre expérience.</p>
                <div className="chat-starters">
                  {STARTER_QUESTIONS.map((starter) => (
                    <button type="button" className="chat-starter" key={starter.title} onClick={() => chooseStarterQuestion(starter.prompt)}>
                      <strong>{starter.title}</strong><span>{starter.prompt}</span>
                    </button>
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
                onChange={(event) => setQuestion(event.target.value)}
                onKeyDown={handleQuestionKeyDown}
                placeholder="Écrivez votre message…"
                aria-label="Votre message à l’assistant"
                maxLength="12000"
                required
              />
              <span className="chat-input-hint"><span>Entrée pour envoyer · Maj+Entrée pour un saut de ligne</span><span>{question.length.toLocaleString('fr-FR')} / 12 000</span></span>
            </div>
            <button className="btn chat-send" disabled={busy || history.loading || !question.trim()} aria-label={busy ? 'Réponse en cours' : 'Envoyer le message'}>
              {busy ? <LoaderCircle className="spinner" size={17} /> : <Send size={17} />}
              <span>{busy ? 'En cours…' : 'Envoyer'}</span>
            </button>
          </form>
          <p className="assistant-disclaimer">L’IA peut se tromper. Vérifiez les informations avant de les intégrer à votre rapport.</p>
        </section>
      </div>

      {reportId && (
        <details className="assistant-research">
          <summary><Search size={16} /> Recherche documentaire et webographie</summary>
          <form onSubmit={searchSources}>
            <p className="muted">Les sources sont des propositions : contrôlez-les avant de les citer dans votre rapport.</p>
            <div className="inline">
              <label>Sujet de recherche<input value={researchQuestion} onChange={(event) => setResearchQuestion(event.target.value)} required maxLength="4000" /></label>
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
        </details>
      )}
    </section>
  );
}
