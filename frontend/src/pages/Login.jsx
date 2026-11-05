import { useEffect, useRef, useState } from 'react';
import { ArrowRight, BookOpenCheck, BrainCircuit, FileText, KeyRound, ShieldCheck, Sparkles } from 'lucide-react';
import { api, ApiError, LOGIN_PATH } from '../api.js';
import { Alert } from '../ui.jsx';

export default function Login({ onLogin }) {
  const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
  const query = new URLSearchParams(window.location.search);
  const resetToken = query.get('token') || '';
  const [mode, setMode] = useState(resetToken ? 'reset' : 'login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState(query.get('email') || '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [rawToken, setRawToken] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [googleError, setGoogleError] = useState('');
  const googleInitialized = useRef(false);
  const onLoginRef = useRef(onLogin);

  useEffect(() => {
    onLoginRef.current = onLogin;
  }, [onLogin]);

  useEffect(() => {
    if (mode !== 'login' || !googleClientId) return undefined;
    let active = true;

    const renderGoogleButton = () => {
      if (!active || !window.google?.accounts?.id) return;

      if (!googleInitialized.current) {
        window.google.accounts.id.initialize({
          client_id: googleClientId,
          callback: async ({ credential }) => {
            if (!credential) return;
            setBusy(true);
            setError('');
            try {
              const data = await api.post('/auth/google', { credential });
              if (!data.token) throw new ApiError('La réponse Google ne contient pas de jeton.', 0, data);
              onLoginRef.current(data.token);
            } catch (err) {
              setError(err.message);
            } finally {
              setBusy(false);
            }
          },
        });
        googleInitialized.current = true;
      }

      const container = document.getElementById('google-signin-button');
      if (container) {
        try {
          container.replaceChildren();
          window.google.accounts.id.renderButton(container, {
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            width: Math.min(container.clientWidth || 400, 400),
          });
          setGoogleError('');
        } catch {
          setGoogleError('Le bouton Google n’a pas pu être initialisé. Vérifiez la configuration OAuth.');
        }
      }
    };

    const handleScriptError = () => {
      if (active) setGoogleError('Impossible de charger le service Google. Vérifiez votre connexion Internet.');
    };
    let script = document.getElementById('google-identity-services');
    if (window.google?.accounts?.id) {
      renderGoogleButton();
    } else {
      if (!script) {
        script = document.createElement('script');
        script.id = 'google-identity-services';
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
      }

      script.addEventListener('load', renderGoogleButton);
      script.addEventListener('error', handleScriptError);
      if (!script.isConnected) document.head.appendChild(script);
    }

    return () => {
      active = false;
      script?.removeEventListener('load', renderGoogleButton);
      script?.removeEventListener('error', handleScriptError);
    };
  }, [googleClientId, mode, onLogin]);

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setBusy(true);
    try {
      if (mode === 'login') {
        const data = await api.post(LOGIN_PATH, { email, password });
        const token = data?.token ?? data?.access_token ?? data?.plainTextToken ?? data?.data?.token;
        if (!token) throw new ApiError('La réponse de connexion ne contient pas de jeton.', 0, data);
        onLogin(token);
      } else if (mode === 'register') {
        const data = await api.post('/auth/register', {
          name, email, password, password_confirmation: passwordConfirmation,
        });
        onLogin(data.token);
      } else if (mode === 'forgot') {
        const data = await api.post('/auth/password/forgot', { email });
        setSuccess(data.message);
      } else {
        const data = await api.post('/auth/password/reset', {
          token: resetToken, email, password, password_confirmation: passwordConfirmation,
        });
        setSuccess(data.message);
        setMode('login');
        window.history.replaceState({}, '', window.location.pathname);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const heading = {
    login: 'Connexion',
    register: 'Créer un compte étudiant',
    forgot: 'Mot de passe oublié',
    reset: 'Nouveau mot de passe',
  }[mode];

  return (
    <div className="auth-screen">
      <section className="auth-showcase">
        <a className="auth-brand" href="/" aria-label="FredRedact, accueil">
          <span className="brand-mark"><Sparkles size={21} /></span>
          <span><strong>Fred<span>Redact</span></strong><small>VOTRE STAGE, VOTRE HISTOIRE</small></span>
        </a>
        <div className="auth-copy">
          <span className="eyebrow"><Sparkles size={14} /> Votre copilote de rédaction</span>
          <h1>Un rapport dont vous serez <span>fier.</span></h1>
          <p>Structurez vos idées, valorisez votre expérience et avancez sereinement, une étape à la fois.</p>
          <div className="auth-feature-list">
            <div className="auth-feature"><span className="auth-feature-icon"><BookOpenCheck size={17} /></span>Un espace clair pour organiser votre rapport</div>
            <div className="auth-feature"><span className="auth-feature-icon"><BrainCircuit size={17} /></span>Une IA qui vous accompagne sans inventer</div>
            <div className="auth-feature"><span className="auth-feature-icon"><FileText size={17} /></span>Vos documents et exports au même endroit</div>
          </div>
        </div>
        <div className="auth-trust"><ShieldCheck size={16} /> Vos données et vos travaux restent confidentiels.</div>
      </section>

      <section className="auth-panel">
        <div className="auth-panel-inner">
          <div className="auth-mobile-brand">
            <span className="brand-mark"><Sparkles size={19} /></span>
            <span><strong>FredRedact</strong><small>VOTRE ESPACE DE TRAVAIL</small></span>
          </div>
          <h2>{mode === 'login' ? 'Ravi de vous revoir' : heading}</h2>
          <p className="auth-panel-intro">{mode === 'login' ? 'Connectez-vous pour reprendre votre projet là où vous l’avez laissé.' : 'Quelques instants suffisent pour continuer.'}</p>

          <Alert>{error}</Alert>
          <Alert type="success">{success}</Alert>

          {LOGIN_PATH !== '' && (
            <form className="card" onSubmit={submit}>
              <h3>{mode === 'login' ? 'Connexion à votre compte' : heading}</h3>
              {mode === 'register' && (
                <label>Nom complet<input autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} maxLength="120" required /></label>
              )}
              <label>Adresse e-mail<input type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></label>
              {(mode === 'login' || mode === 'register' || mode === 'reset') && (
                <label>Mot de passe<input type="password" autoComplete={mode === 'login' ? 'current-password' : 'new-password'} value={password} onChange={(e) => setPassword(e.target.value)} minLength="8" required /></label>
              )}
              {(mode === 'register' || mode === 'reset') && (
                <label>Confirmer le mot de passe<input type="password" autoComplete="new-password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} minLength="8" required /></label>
              )}
              <button className="btn" disabled={busy}>
                {busy ? 'En cours…' : mode === 'login' ? <>Se connecter <ArrowRight size={16} /></> : mode === 'register' ? 'Créer mon compte' : mode === 'forgot' ? 'Envoyer le lien' : 'Réinitialiser'}
              </button>
              {mode === 'login' && (
                <div className="auth-switch">
                  <button type="button" className="link" onClick={() => setMode('forgot')}>Mot de passe oublié ?</button>
                  <button type="button" className="link" onClick={() => setMode('register')}>Créer un compte</button>
                </div>
              )}
              {(mode === 'forgot' || mode === 'register' || mode === 'reset') && (
                <div className="auth-switch"><button type="button" className="link" onClick={() => setMode('login')}>Retour à la connexion</button></div>
              )}
            </form>
          )}

          {mode === 'login' && (
            <>
              <div className="auth-divider">ou continuer avec Google</div>
              {googleClientId
                ? (
                  <>
                    <div id="google-signin-button" className="google-signin-button" />
                    <Alert>{googleError}</Alert>
                  </>
                )
                : <p className="muted">Connexion Google disponible après configuration de VITE_GOOGLE_CLIENT_ID.</p>}
            </>
          )}

          <div className="auth-divider">ou connexion développeur</div>
          <form className="card auth-token-form" onSubmit={(e) => { e.preventDefault(); if (rawToken.trim()) onLogin(rawToken.trim()); }}>
            <h3><KeyRound size={16} /> Accéder avec un jeton API</h3>
            <p>Réservé aux environnements de développement.</p>
            <label className="sr-only" htmlFor="api-token">Jeton API</label>
            <textarea id="api-token" rows="2" value={rawToken} onChange={(e) => setRawToken(e.target.value)} placeholder="Coller un jeton de développement" />
            <button className="btn btn-light" disabled={!rawToken.trim()}>Utiliser ce jeton</button>
          </form>
          <p className="auth-footnote">En vous connectant, vous accédez à votre espace personnel sécurisé.</p>
        </div>
      </section>
    </div>
  );
}
