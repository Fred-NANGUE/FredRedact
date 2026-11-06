import { Fragment, useCallback, useEffect, useRef, useState } from 'react';
import {
  ArrowUpRight, BookOpen, Bot, CreditCard, FolderOpen, GraduationCap,
  LayoutDashboard, LogOut, Menu, Settings2, ShieldCheck, Sparkles, Users, WalletCards,
} from 'lucide-react';
import { api, getToken, setToken } from './api.js';
import Login from './pages/Login.jsx';
import Plans from './pages/Plans.jsx';
import Account from './pages/Account.jsx';
import Internships from './pages/Internships.jsx';
import Reports from './pages/Reports.jsx';
import Assistant from './pages/Assistant.jsx';
import Documents from './pages/Documents.jsx';
import Profile from './pages/Profile.jsx';
import Dashboard from './pages/admin/Dashboard.jsx';
import AdminPlans from './pages/admin/AdminPlans.jsx';
import AdminSubscriptions from './pages/admin/AdminSubscriptions.jsx';
import AdminTokens from './pages/admin/AdminTokens.jsx';
import AdminUsers from './pages/admin/AdminUsers.jsx';
import { Alert, UserAvatar } from './ui.jsx';

const STUDENT_TABS = [
  ['internships', 'Mes stages', GraduationCap],
  ['reports', 'Mes rapports', BookOpen],
  ['assistant', 'Assistant IA', Bot],
  ['documents', 'Documents & export', FolderOpen],
  ['plans', 'Offres', CreditCard],
  ['account', 'Abonnement & tokens', WalletCards],
];
const ADMIN_TABS = [
  ['dashboard', 'Vue d’ensemble', LayoutDashboard],
  ['admin-users', 'Utilisateurs', Users],
  ['admin-plans', 'Gestion des offres', CreditCard],
  ['admin-subs', 'Abonnements', WalletCards],
  ['admin-tokens', 'Tokens', Sparkles],
];
const PAGE_TITLES = {
  dashboard: ['Vue d’ensemble', 'Votre activité en un coup d’œil'],
  'admin-users': ['Utilisateurs', 'Gérez les accès et les rôles'],
  'admin-plans': ['Gestion des offres', 'Configurez les formules et leurs quotas'],
  'admin-subs': ['Abonnements', 'Suivez les activations et les échéances'],
  'admin-tokens': ['Gestion des tokens', 'Créditez et suivez la consommation'],
  internships: ['Mes stages', 'Organisez les informations de votre expérience'],
  reports: ['Mes rapports', 'Rédigez, structurez et suivez votre progression'],
  assistant: ['Assistant IA', 'Un copilote pour faire avancer votre rapport'],
  documents: ['Documents & export', 'Importez vos ressources et préparez votre rendu'],
  plans: ['Offres & abonnement', 'Choisissez les crédits adaptés à votre projet'],
  account: ['Mon espace', 'Votre offre et votre consommation'],
  profile: ['Mon profil', 'Vos informations personnelles et votre sécurité'],
};

export default function App() {
  const [token, setTokenState] = useState(getToken());
  const [role, setRole] = useState(null);
  const [profile, setProfile] = useState(null);
  const initialPayment = new URLSearchParams(window.location.search).get('payment');
  const [page, setPage] = useState(['return', 'paypal-return'].includes(initialPayment) ? 'account' : 'plans');
  const [tokenUserId, setTokenUserId] = useState('');
  const [assistantReportId, setAssistantReportId] = useState('');
  const [error, setError] = useState('');
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const paypalReturnHandled = useRef(false);

  const clearLogin = () => {
    setToken('');
    setTokenState('');
    setRole(null);
    setProfile(null);
    setPage('plans');
    setMobileNavOpen(false);
  };

  const logout = async () => {
    try {
      await api.post('/auth/logout');
    } catch (e) {
      if (e.status !== 401) setError(e.message);
    } finally {
      clearLogin();
    }
  };

  const handleLogin = useCallback((newToken) => {
    setToken(newToken);
    setTokenState(newToken);
    setError('');
  }, []);

  useEffect(() => {
    if (!token) return undefined;
    let active = true;
    api.get('/profile')
      .then((user) => {
        if (!active) return;
        setRole(user.role === 'admin' ? 'admin' : 'etudiant');
        setProfile(user);
        setPage(user.role === 'admin' ? 'dashboard' : ['return', 'paypal-return'].includes(new URLSearchParams(window.location.search).get('payment')) ? 'account' : 'internships');
      })
      .catch((e) => {
        if (!active) return;
        if (e.status === 401) clearLogin();
        else setError(e.message);
      });
    return () => { active = false; };
  }, [token]);

  useEffect(() => {
    if (!token || role !== 'etudiant' || paypalReturnHandled.current) return;
    const params = new URLSearchParams(window.location.search);
    const payment = params.get('payment');
    if (payment !== 'paypal-return' && payment !== 'paypal-cancel') return;

    paypalReturnHandled.current = true;
    window.history.replaceState({}, '', window.location.pathname);
    if (payment === 'paypal-cancel') {
      setPage('plans');
      setError('Paiement PayPal annulé. Aucun abonnement n’a été activé.');
      return;
    }

    const orderId = params.get('token') || params.get('order_id');
    const subscriptionId = params.get('subscription_id');
    if (!orderId || !subscriptionId) {
      setPage('plans');
      setError('Les informations de retour PayPal sont incomplètes. Contactez l’administrateur avant de recommencer le paiement.');
      return;
    }
    api.post('/billing/paypal/capture', { order_id: orderId, subscription_id: subscriptionId })
      .then(() => {
        setPage('account');
        setError('Paiement PayPal confirmé. Votre abonnement est activé.');
      })
      .catch((e) => {
        setPage('plans');
        setError(e.message);
      });
  }, [token, role]);

  if (!token) return <Login onLogin={handleLogin} />;
  if (!role) {
    return (
      <div className="boot-screen">
        <div className="boot-mark"><Sparkles size={24} /></div>
        <p>Préparation de votre espace…</p>
        <Alert>{error}</Alert>
      </div>
    );
  }

  const tabs = role === 'admin' ? [...ADMIN_TABS, ...STUDENT_TABS] : STUDENT_TABS;
  const title = PAGE_TITLES[page] || PAGE_TITLES.internships;
  const CurrentIcon = tabs.find(([key]) => key === page)?.[2] || BookOpen;
  const navigate = (nextPage) => {
    setError('');
    setPage(nextPage);
    setMobileNavOpen(false);
  };
  const manageTokens = (userId) => {
    setTokenUserId(String(userId));
    navigate('admin-tokens');
  };
  const openAssistant = (reportId = '') => {
    setAssistantReportId(reportId ? String(reportId) : '');
    navigate('assistant');
  };

  return (
    <div className="app-shell">
      {mobileNavOpen && <button className="sidebar-backdrop" aria-label="Fermer le menu" onClick={() => setMobileNavOpen(false)} />}
      <aside className={`sidebar ${mobileNavOpen ? 'sidebar-open' : ''}`}>
        <button className="brand-lockup" onClick={() => navigate(role === 'admin' ? 'dashboard' : 'internships')}>
          <span className="brand-mark"><Sparkles size={21} /></span>
          <span className="brand-copy"><strong>Fred<span>Redact</span></strong><small>ESPACE DE TRAVAIL</small></span>
        </button>
        <div className="sidebar-label">{role === 'admin' ? 'ADMINISTRATION' : 'VOTRE ESPACE'}</div>
        <nav className="sidebar-nav" aria-label="Navigation principale">
          {role === 'admin' && <div className="nav-section-title">Pilotage</div>}
          {tabs.map(([key, label, Icon]) => {
            const selected = page === key;
            const adminPage = key.startsWith('admin-') || key === 'dashboard';
            const studentPage = !adminPage;
            return (
              <Fragment key={key}>
                {role === 'admin' && studentPage && key === 'internships' && <div className="nav-section-title">Espace étudiant</div>}
                <button
                  className={`nav-link ${selected ? 'nav-link-active' : ''}`}
                  aria-current={selected ? 'page' : undefined}
                  onClick={() => navigate(key)}
                >
                  <Icon size={18} strokeWidth={selected ? 2.2 : 1.8} />
                  <span>{label}</span>
                  {selected && <span className="nav-active-dot" />}
                </button>
              </Fragment>
            );
          })}
        </nav>
        <div className="sidebar-bottom">
          <div className="help-card">
            <div className="help-icon"><ShieldCheck size={18} /></div>
            <strong>Votre travail, en sécurité</strong>
            <p>Vos rapports restent privés et accessibles uniquement par vous.</p>
          </div>
          <button className={`nav-link ${page === 'profile' ? 'nav-link-active' : ''}`} onClick={() => navigate('profile')}>
            <Settings2 size={18} /><span>Mon profil</span>
          </button>
          <div className="sidebar-user">
            <UserAvatar name={profile?.name} photoUrl={profile?.google_avatar_url} label={profile?.name} />
            <span className="user-copy"><strong>{profile?.name || (role === 'admin' ? 'Administration' : 'Espace étudiant')}</strong><small>{role === 'admin' ? 'Administrateur' : 'Compte actif'}</small></span>
            <button className="icon-button logout-button" title="Déconnexion" aria-label="Déconnexion" onClick={logout}><LogOut size={17} /></button>
          </div>
        </div>
      </aside>

      <div className="workspace">
        <header className="workspace-header">
          <button className="icon-button mobile-menu-button" aria-label="Ouvrir le menu" onClick={() => setMobileNavOpen(true)}><Menu size={21} /></button>
          <div className="breadcrumb"><span>FredRedact</span><span className="breadcrumb-separator">/</span><strong>{title[0]}</strong></div>
          <div className="header-actions">
            <span className="secure-label"><ShieldCheck size={15} /> Espace sécurisé</span>
            <button className="header-profile" onClick={() => navigate('profile')} aria-label="Ouvrir mon profil"><UserAvatar name={profile?.name} photoUrl={profile?.google_avatar_url} label={profile?.name} className="avatar-small" /><span className="header-role">{role === 'admin' ? 'Admin' : 'Étudiant'}</span><ArrowUpRight size={14} /></button>
          </div>
        </header>
        <main className={page === 'assistant' ? 'page-content page-content-assistant' : 'page-content'}>
          {page !== 'assistant' && (
            <div className="page-heading">
              <div className="page-heading-icon"><CurrentIcon size={22} /></div>
              <div><h1>{title[0]}</h1><p>{title[1]}</p></div>
            </div>
          )}
          <Alert>{error}</Alert>
          <div className={page === 'assistant' ? 'page-body assistant-page-body' : 'page-body'}>
            {page === 'plans' && <Plans onGoAccount={() => navigate('account')} />}
            {page === 'account' && <Account />}
            {page === 'internships' && <Internships onOpenReports={() => navigate('reports')} />}
            {page === 'reports' && <Reports onOpenAssistant={openAssistant} />}
            {page === 'assistant' && <Assistant initialReportId={assistantReportId} />}
            {page === 'documents' && <Documents />}
            {page === 'profile' && <Profile onProfileUpdate={(updatedProfile) => setProfile(updatedProfile)} />}
            {page === 'dashboard' && <Dashboard />}
            {page === 'admin-users' && <AdminUsers />}
            {page === 'admin-plans' && <AdminPlans />}
            {page === 'admin-subs' && <AdminSubscriptions onManageTokens={manageTokens} />}
            {page === 'admin-tokens' && <AdminTokens initialUserId={tokenUserId} />}
          </div>
        </main>
        <footer className="workspace-footer"><span>FredRedact</span><span>Votre rapport, avec méthode et confiance.</span></footer>
      </div>
    </div>
  );
}
