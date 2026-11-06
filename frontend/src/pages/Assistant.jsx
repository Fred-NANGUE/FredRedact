import { useEffect, useMemo, useRef, useState } from 'react';
import {
  BookOpen, Bot, Check, ChevronDown, Copy, FileText, LoaderCircle, Mic, Pencil,
  MessageCircle, Paperclip, Plus, Search, Send, ShieldCheck, Sparkles, Trash2,
  Volume2, VolumeX, WandSparkles, X,
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
  const availableDocuments = useLoad(
    () => api.get(`/ai/documents${reportId ? `?rapport_id=${reportId}` : ''}`),
    [reportId]
  );
  const savedSources = useLoad(
    () => reportId ? api.get(`/reports/${reportId}/webographies`) : Promise.resolve([]),
    [reportId]
  );
  const [question, setQuestion] = useState('');
  const [messages, setMessages] = useState([]);
  const [conversations, setConversations] = useState([]);
  const [conversationId, setConversationId] = useState(null);
  const [conversationLoading, setConversationLoading] = useState(true);
  const [messageLoading, setMessageLoading] = useState(false);
  const [messagesLoadedFor, setMessagesLoadedFor] = useState(null);
  const [editingTitle, setEditingTitle] = useState(false);
  const [conversationTitle, setConversationTitle] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [sources, setSources] = useState([]);
  const [sectionId, setSectionId] = useState('');
  const [researchQuestion, setResearchQuestion] = useState('');
  const [toolsOpen, setToolsOpen] = useState(false);
  const [copiedMessageId, setCopiedMessageId] = useState(null);
  const [attachment, setAttachment] = useState(null);
  const [fileBusy, setFileBusy] = useState(false);
  const [isListening, setIsListening] = useState(false);
  const [voiceMode, setVoiceMode] = useState(false);
  const [voicePreview, setVoicePreview] = useState('');
  const [speakingMessageId, setSpeakingMessageId] = useState(null);
  const messagesEndRef = useRef(null);
  const questionInputRef = useRef(null);
  const fileInputRef = useRef(null);
  const recognitionRef = useRef(null);
  const speechRef = useRef(null);
  const voiceModeRef = useRef(false);
  const voiceTurnPendingRef = useRef(false);
  const voiceRestartTimerRef = useRef(null);
  const submitMessageRef = useRef(null);
  const startVoiceRecognitionRef = useRef(null);
  const restoredAttachmentConversationRef = useRef(null);
  const SpeechRecognition = typeof window !== 'undefined'
    ? window.SpeechRecognition || window.webkitSpeechRecognition
    : null;
  const speechSynthesisAvailable = typeof window !== 'undefined'
    && 'speechSynthesis' in window
    && 'SpeechSynthesisUtterance' in window;

  useEffect(() => {
    let active = true;
    setConversations([]);
    setConversationId(null);
    setMessages([]);
    setConversationLoading(true);
    const query = reportId ? `?rapport_id=${reportId}` : '';
    api.get(`/ai/conversations${query}`)
      .then((items) => {
        if (!active) return;
        setConversations(items);
        setConversationId(items[0]?.id ?? null);
      })
      .catch((e) => {
        if (active) setError(e.message);
      })
      .finally(() => {
        if (active) setConversationLoading(false);
      });
    return () => { active = false; };
  }, [reportId]);

  useEffect(() => {
    if (!conversationId) {
      setMessages([]);
      setMessagesLoadedFor(null);
      setMessageLoading(false);
      return undefined;
    }
    let active = true;
    setMessages([]);
    setMessagesLoadedFor(null);
    setMessageLoading(true);
    api.get(`/ai/chat?conversation_id=${conversationId}`)
      .then((items) => {
        if (!active) return;
        setMessages(items);
        setMessagesLoadedFor(conversationId);
      })
      .catch((e) => { if (active) setError(e.message); })
      .finally(() => { if (active) setMessageLoading(false); });
    return () => { active = false; };
  }, [conversationId]);

  const activeConversation = conversations.find((item) => item.id === conversationId);
  const selectableDocuments = (availableDocuments.data || []).filter(
    (item) => item.rapport_id === null || String(item.rapport_id) === reportId
  );

  useEffect(() => {
    if (!conversationId || messagesLoadedFor !== conversationId || messageLoading || availableDocuments.loading
      || restoredAttachmentConversationRef.current === conversationId) return;
    restoredAttachmentConversationRef.current = conversationId;
    const lastAttachment = [...messages].reverse().find((item) => item.document_id);
    const document = selectableDocuments.find((item) => item.id === lastAttachment?.document_id);
    if (document) setAttachment({ id: document.id, name: lastAttachment.attachment_name || document.nom_original });
  }, [conversationId, messagesLoadedFor, messages, messageLoading, availableDocuments.loading, availableDocuments.data, reportId]);

  useEffect(() => {
    setConversationTitle(activeConversation?.titre || '');
    setEditingTitle(false);
  }, [activeConversation?.id, activeConversation?.titre]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: busy ? 'smooth' : 'auto', block: 'end' });
  }, [messages, busy]);

  useEffect(() => () => {
    window.clearTimeout(voiceRestartTimerRef.current);
    voiceModeRef.current = false;
    voiceTurnPendingRef.current = false;
    const recognition = recognitionRef.current;
    if (recognition) {
      recognition.onend = null;
      recognition.onerror = null;
      recognition.stop();
    }
    speechRef.current = null;
    window.speechSynthesis?.cancel();
  }, []);

  const sections = useMemo(() => (report.data?.sections || []), [report.data]);
  const allSections = useMemo(
    () => sections.flatMap((section) => [section, ...(section.children || [])]),
    [sections]
  );

  const createConversationRecord = async () => {
    const conversation = await api.post('/ai/conversations', {
      rapport_id: reportId || null,
    });
    setConversations((items) => [conversation, ...items]);
    setConversationId(conversation.id);
    setMessages([]);
    return conversation;
  };

  const createConversation = async () => {
    if (busy || fileBusy) return;
    setError('');
    try {
      await createConversationRecord();
    } catch (e) {
      setError(e.message);
    }
  };

  const submitMessage = async (text, fromVoice = false) => {
    const message = text.trim();
    if (!message || busy || fileBusy || conversationLoading || messageLoading) return;
    setQuestion('');
    setError('');
    setNotice('');
    setBusy(true);
    const attachmentForMessage = attachment;
    setMessages((items) => [...items, { question: message, attachment_name: attachmentForMessage?.name, pending: true }]);
    try {
      const currentConversation = conversationId
        ? { id: conversationId }
        : await createConversationRecord();
      const data = await api.post('/ai/chat', {
        question: message,
        conversation_id: currentConversation.id,
        document_id: attachmentForMessage?.id || null,
      });
      setMessages((items) => [
        ...items.filter((item) => !item.pending),
        data.dialogue,
      ]);
      if (data.conversation) {
        setConversations((items) => {
          const rest = items.filter((item) => item.id !== data.conversation.id);
          return [data.conversation, ...rest];
        });
      }
      if (fromVoice && voiceModeRef.current) {
        speakResponse(data.dialogue, true);
      }
    } catch (e) {
      setMessages((items) => items.filter((item) => !item.pending));
      setQuestion(message);
      setError(e.message);
      if (fromVoice) {
        voiceTurnPendingRef.current = false;
        voiceRestartTimerRef.current = window.setTimeout(
          () => startVoiceRecognitionRef.current?.(),
          800
        );
      }
    } finally {
      setBusy(false);
    }
  };

  submitMessageRef.current = submitMessage;

  const send = (event) => {
    event.preventDefault();
    void submitMessage(question);
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

  const handleAttachmentChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    setFileBusy(true);
    setError('');
    setNotice('');
    const form = new FormData();
    form.append('file', file);
    if (reportId) form.append('rapport_id', reportId);
    try {
      const document = await api.upload('/documents', form);
      setAttachment({ id: document.id, name: document.nom_original });
      availableDocuments.reload();
      setNotice(`« ${document.nom_original} » est prêt pour cette discussion.`);
    } catch (e) {
      setError(e.message);
    } finally {
      setFileBusy(false);
    }
  };

  const startVoiceRecognition = () => {
    if (!SpeechRecognition) {
      setError('La saisie vocale n’est pas disponible dans ce navigateur. Essayez Chrome ou Edge.');
      return;
    }
    setError('');
    setVoicePreview('');
    const recognition = new SpeechRecognition();
    recognition.lang = 'fr-FR';
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.onresult = (event) => {
      let finalText = '';
      let interimText = '';
      for (let index = event.resultIndex; index < event.results.length; index += 1) {
        const transcript = event.results[index][0].transcript;
        if (event.results[index].isFinal) finalText += transcript;
        else interimText += transcript;
      }
      if (finalText.trim() && voiceModeRef.current && !voiceTurnPendingRef.current) {
        voiceTurnPendingRef.current = true;
        recognition.stop();
        void submitMessageRef.current?.(finalText, true);
      } else if (finalText.trim()) {
        setQuestion((current) => `${current}${current && !/\s$/.test(current) ? ' ' : ''}${finalText.trim()}`.slice(0, 12000));
      }
      setVoicePreview(interimText);
    };
    recognition.onerror = (event) => {
      if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
        setError('Autorisez l’accès au microphone dans votre navigateur pour dicter un message.');
        voiceModeRef.current = false;
        setVoiceMode(false);
      } else if (event.error !== 'no-speech' && event.error !== 'aborted') {
        setError('La reconnaissance vocale a échoué. Vous pouvez saisir votre message au clavier.');
        voiceModeRef.current = false;
        voiceTurnPendingRef.current = false;
        setVoiceMode(false);
      }
    };
    recognition.onend = () => {
      recognitionRef.current = null;
      setIsListening(false);
      setVoicePreview('');
      if (voiceModeRef.current && !voiceTurnPendingRef.current) {
        voiceRestartTimerRef.current = window.setTimeout(
          () => startVoiceRecognitionRef.current?.(),
          400
        );
      } else if (!voiceModeRef.current) {
        questionInputRef.current?.focus();
      }
    };
    recognitionRef.current = recognition;
    try {
      recognition.start();
      setIsListening(true);
    } catch {
      recognitionRef.current = null;
      if (voiceModeRef.current) {
        voiceModeRef.current = false;
        voiceTurnPendingRef.current = false;
        setVoiceMode(false);
      }
      setError('Impossible de démarrer le microphone. Réessayez après avoir vérifié ses autorisations.');
    }
  };

  startVoiceRecognitionRef.current = startVoiceRecognition;

  const toggleVoiceInput = () => {
    if (isListening) {
      recognitionRef.current?.stop();
      return;
    }
    if (voiceModeRef.current) return;
    startVoiceRecognition();
  };

  const toggleVoiceMode = () => {
    if (voiceModeRef.current) {
      voiceModeRef.current = false;
      voiceTurnPendingRef.current = false;
      setVoiceMode(false);
      window.clearTimeout(voiceRestartTimerRef.current);
      recognitionRef.current?.stop();
      speechRef.current = null;
      window.speechSynthesis?.cancel();
      setSpeakingMessageId(null);
      return;
    }
    if (!SpeechRecognition || !speechSynthesisAvailable) {
      setError('Le mode vocal complet nécessite un navigateur compatible avec la dictée et la lecture vocale.');
      return;
    }
    setError('');
    voiceModeRef.current = true;
    setVoiceMode(true);
    startVoiceRecognition();
  };

  const changeReport = (nextReportId) => {
    setReportId(nextReportId);
    setConversationId(null);
    restoredAttachmentConversationRef.current = null;
    setConversationLoading(true);
    setMessages([]);
    setSectionId('');
    setSources([]);
    setAttachment(null);
    setError('');
  };

  const selectConversation = (conversation) => {
    setConversationId(conversation.id);
    restoredAttachmentConversationRef.current = null;
    setAttachment(null);
    setMessages([]);
    setError('');
  };

  const saveConversationTitle = async (event) => {
    event.preventDefault();
    if (!conversationId || !conversationTitle.trim()) return;
    try {
      const updated = await api.put(`/ai/conversations/${conversationId}`, { titre: conversationTitle.trim() });
      setConversations((items) => items.map((item) => item.id === updated.id ? updated : item));
      setEditingTitle(false);
    } catch (e) {
      setError(e.message);
    }
  };

  const deleteConversation = async (conversation) => {
    if (!window.confirm(`Supprimer la discussion « ${conversation.titre} » et tous ses messages ?`)) return;
    try {
      await api.del(`/ai/conversations/${conversation.id}`);
      const remaining = conversations.filter((item) => item.id !== conversation.id);
      setConversations(remaining);
      setAttachment(null);
      if (conversation.id === conversationId) {
        restoredAttachmentConversationRef.current = null;
        setConversationId(remaining[0]?.id ?? null);
        setMessages([]);
      }
    } catch (e) {
      setError(e.message);
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

  const speakResponse = (message, resumeVoiceMode = false) => {
    if (!speechSynthesisAvailable) {
      setError('La lecture vocale n’est pas disponible dans ce navigateur.');
      return;
    }
    speechRef.current = null;
    window.speechSynthesis.cancel();
    const text = message.reponse
      .replace(/```[\s\S]*?```/g, '')
      .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/^\s*[-+]\s/gm, '')
      .replace(/[*_~`>#]/g, '')
      .trim();
    const utterance = new window.SpeechSynthesisUtterance(text);
    utterance.lang = 'fr-FR';
    utterance.onstart = () => {
      if (speechRef.current === utterance) setSpeakingMessageId(message.id);
    };
    utterance.onend = () => {
      if (speechRef.current === utterance) {
        speechRef.current = null;
        setSpeakingMessageId(null);
        if (resumeVoiceMode && voiceModeRef.current) {
          voiceTurnPendingRef.current = false;
          startVoiceRecognitionRef.current?.();
        }
      }
    };
    utterance.onerror = () => {
      if (speechRef.current === utterance) {
        speechRef.current = null;
        setSpeakingMessageId(null);
        if (resumeVoiceMode && voiceModeRef.current) {
          voiceTurnPendingRef.current = false;
          setError('La lecture vocale a échoué. Le mode vocal reste actif.');
          startVoiceRecognitionRef.current?.();
        } else {
          setError('La lecture vocale a échoué. Vous pouvez lire la réponse à l’écran.');
        }
      }
    };
    speechRef.current = utterance;
    try {
      window.speechSynthesis.speak(utterance);
    } catch {
      speechRef.current = null;
      setSpeakingMessageId(null);
      setError('Impossible de démarrer la lecture vocale dans ce navigateur.');
    }
  };

  const toggleResponseAudio = (message) => {
    if (speakingMessageId === message.id) {
      speechRef.current = null;
      window.speechSynthesis?.cancel();
      setSpeakingMessageId(null);
      return;
    }
    speakResponse(message);
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

      <Alert>{error || reports.error || report.error || availableDocuments.error}</Alert>
      <Alert type="success">{notice}</Alert>

      <div className="assistant-context">
        <div className="assistant-context-label"><BookOpen size={16} /><span>CONTEXTE</span></div>
        <label className="sr-only" htmlFor="assistant-report">Rapport concerné</label>
        <select
          id="assistant-report"
          value={reportId}
          disabled={busy || fileBusy || voiceMode}
          onChange={(event) => changeReport(event.target.value)}
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
          <div className="assistant-list-heading">
            <span>VOS DISCUSSIONS</span>
            <button type="button" className="assistant-new-thread" onClick={createConversation} disabled={busy || fileBusy || voiceMode || conversationLoading} aria-label="Créer une nouvelle discussion" title="Nouvelle discussion"><Plus size={16} /></button>
          </div>
          {conversationLoading && <Loading loading />}
          {!conversationLoading && conversations.map((item) => (
            <div className={`assistant-thread-row ${item.id === conversationId ? 'assistant-thread-row-active' : ''}`} key={item.id}>
              <button type="button" className="assistant-thread" onClick={() => selectConversation(item)} disabled={busy || fileBusy || voiceMode}>
                <span className="assistant-thread-icon"><MessageCircle size={16} /></span>
                <span className="assistant-thread-copy"><strong>{item.titre}</strong><small>{item.dialogues_count || 0} message(s)</small></span>
              </button>
              <button type="button" className="assistant-delete-thread" onClick={() => deleteConversation(item)} disabled={busy || fileBusy || voiceMode} aria-label={`Supprimer ${item.titre}`} title="Supprimer la discussion"><Trash2 size={14} /></button>
            </div>
          ))}
          {!conversationLoading && conversations.length === 0 && <p className="assistant-empty-threads">Aucune discussion. Créez-en une pour commencer.</p>}
          <div className="assistant-sidebar-note"><ShieldCheck size={15} /><span>Vos échanges sont associés à votre compte et à vos rapports.</span></div>
        </aside>

        <section className="assistant-chat-panel" aria-label="Conversation">
          <div className="assistant-chat-header">
            <span className="assistant-avatar"><Bot size={19} /></span>
            <div className="assistant-chat-title">
              {editingTitle ? (
                <form className="assistant-title-form" onSubmit={saveConversationTitle}>
                  <input aria-label="Nom de la discussion" value={conversationTitle} onChange={(event) => setConversationTitle(event.target.value)} maxLength="100" required autoFocus />
                  <button type="submit" className="assistant-title-action" aria-label="Enregistrer le nom"><Check size={14} /></button>
                  <button type="button" className="assistant-title-action" aria-label="Annuler" onClick={() => setEditingTitle(false)}><X size={14} /></button>
                </form>
              ) : (
                <div className="assistant-title-display">
                  <strong>{activeConversation?.titre || 'Nouvelle discussion'}</strong>
                  {conversationId && <button type="button" className="assistant-title-action" aria-label="Renommer la discussion" onClick={() => setEditingTitle(true)}><Pencil size={13} /></button>}
                </div>
              )}
              <span>{selectedReport ? selectedReport.titre : 'Assistant de rédaction'}</span>
            </div>
            <span className="assistant-online"><i /> En ligne</span>
          </div>

          <Loading loading={conversationLoading || messageLoading} />
          <div className="chat-messages" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Conversation avec l’assistant">
            {messages.map((item, index) => (
              <article className="chat-exchange" key={item.id || `pending-${index}`}>
                <div className="chat-question">
                  <span className="chat-speaker">Vous</span>
                  <p>{item.question}</p>
                  {item.attachment_name && <span className="chat-message-attachment"><Paperclip size={12} />{item.attachment_name}</span>}
                </div>
                <div className="chat-answer">
                  <span className="chat-speaker"><Bot size={14} /> FredRedact</span>
                  {item.pending
                    ? <p className="chat-thinking"><LoaderCircle className="spinner" size={15} /> Préparation de la réponse…</p>
                    : (
                      <>
                        <div className="chat-markdown"><ReactMarkdown remarkPlugins={[remarkGfm]}>{item.reponse || ''}</ReactMarkdown></div>
                        <div className="chat-answer-actions">
                          <button
                            type="button"
                            className="chat-copy"
                            onClick={() => toggleResponseAudio(item)}
                            disabled={!speechSynthesisAvailable}
                            aria-label={speakingMessageId === item.id ? 'Arrêter la lecture vocale' : 'Écouter la réponse'}
                            title={speechSynthesisAvailable ? 'Écouter la réponse' : 'Lecture vocale non prise en charge par ce navigateur'}
                          >
                            {speakingMessageId === item.id ? <VolumeX size={14} /> : <Volume2 size={14} />}
                            {speakingMessageId === item.id ? 'Arrêter' : 'Écouter'}
                          </button>
                          <button type="button" className="chat-copy" onClick={() => copyResponse(item)} aria-label="Copier la réponse">
                            {copiedMessageId === item.id ? <Check size={14} /> : <Copy size={14} />}
                            {copiedMessageId === item.id ? 'Copié' : 'Copier'}
                          </button>
                        </div>
                      </>
                    )}
                </div>
              </article>
            ))}
            {!conversationLoading && !messageLoading && messages.length === 0 && (
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
            {attachment && (
              <div className="chat-attachment-chip">
                <Paperclip size={14} />
                <span title={attachment.name}>{attachment.name}</span>
                <button type="button" onClick={() => setAttachment(null)} aria-label="Retirer le fichier joint" title="Retirer le fichier"><X size={14} /></button>
              </div>
            )}
            {isListening && <div className="chat-listening"><span className="chat-recording-dot" />{voiceMode ? 'Mode vocal actif… L’envoi sera automatique.' : 'Dictée en cours… Parlez normalement.'}</div>}
            {voicePreview && <div className="chat-voice-preview">{voicePreview}</div>}
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
                disabled={voiceMode}
              />
              <span className="chat-input-hint"><span>Entrée pour envoyer · Maj+Entrée pour un saut de ligne</span><span>{question.length.toLocaleString('fr-FR')} / 12 000</span></span>
            </div>
            <div className="chat-composer-actions">
              <label className="sr-only" htmlFor="chat-existing-document">Joindre un document déjà importé</label>
              <select
                id="chat-existing-document"
                className="chat-document-select"
                value={attachment?.id || ''}
                onChange={(event) => {
                  const document = selectableDocuments.find((item) => String(item.id) === event.target.value);
                  setAttachment(document ? { id: document.id, name: document.nom_original } : null);
                }}
                disabled={busy || fileBusy || voiceMode || availableDocuments.loading || !selectableDocuments.length}
                title="Choisir un document déjà importé"
              >
                <option value="">{availableDocuments.loading ? 'Chargement…' : 'Documents importés'}</option>
                {selectableDocuments.map((document) => (
                  <option key={document.id} value={document.id}>{document.nom_original}</option>
                ))}
              </select>
              <input
                ref={fileInputRef}
                className="sr-only"
                type="file"
                accept=".pdf,.docx,.jpg,.jpeg,.png,.webp"
                onChange={handleAttachmentChange}
                aria-label="Choisir un fichier à joindre"
              />
              <button
                type="button"
                className="chat-tool-button"
                onClick={() => fileInputRef.current?.click()}
                disabled={busy || fileBusy || isListening || voiceMode}
                aria-label={fileBusy ? 'Import du fichier en cours' : 'Joindre ou remplacer un fichier'}
                title="Joindre ou remplacer un fichier (PDF, Word ou image)"
              >
                {fileBusy ? <LoaderCircle className="spinner" size={17} /> : <Paperclip size={17} />}
                <span>{fileBusy ? 'Analyse…' : 'Fichier'}</span>
              </button>
              <button
                type="button"
                className={`chat-tool-button ${isListening ? 'chat-tool-button-active' : ''}`}
                onClick={toggleVoiceInput}
                disabled={busy || fileBusy || voiceMode}
                aria-label={isListening ? 'Arrêter la dictée vocale' : SpeechRecognition ? 'Dicter un message' : 'Saisie vocale non prise en charge : afficher les détails'}
                title={SpeechRecognition ? 'Dicter un message en français' : 'Saisie vocale non prise en charge par ce navigateur'}
                aria-pressed={isListening}
              >
                <Mic size={17} /><span>{isListening ? 'Stop' : 'Voix'}</span>
              </button>
              <button
                type="button"
                className={`chat-tool-button ${voiceMode ? 'chat-tool-button-active' : ''}`}
                onClick={toggleVoiceMode}
                disabled={busy || fileBusy || conversationLoading || messageLoading || (isListening && !voiceMode)}
                aria-pressed={voiceMode}
                aria-label={voiceMode ? 'Arrêter le mode vocal' : 'Activer la conversation vocale'}
                title="Échange vocal mains libres : dicter, recevoir la réponse à voix haute, puis reprendre l’écoute"
              >
                {voiceMode ? <VolumeX size={17} /> : <Volume2 size={17} />}
                <span>{voiceMode ? 'Quitter' : 'Mode vocal'}</span>
              </button>
              <button className="btn chat-send" disabled={busy || fileBusy || voiceMode || conversationLoading || messageLoading || !question.trim()} aria-label={busy ? 'Réponse en cours' : 'Envoyer le message'}>
              {busy ? <LoaderCircle className="spinner" size={17} /> : <Send size={17} />}
              <span>{busy ? 'En cours…' : 'Envoyer'}</span>
              </button>
            </div>
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
