import { api } from '../../api.js';
import { useLoad } from '../../hooks.js';
import { Alert, Loading, fmt, money } from '../../ui.jsx';

function Bars({ items, unit = '' }) {
  const max = Math.max(1, ...items.map((i) => i.value));
  return (
    <div className="bars">
      {items.map((i) => (
        <div key={i.label} className="bar-row">
          <span className="bar-label">{i.label}</span>
          <div className="bar-track"><div className="bar-fill" style={{ width: `${(i.value / max) * 100}%` }} /></div>
          <span className="bar-value">{fmt(i.value)}{unit}</span>
        </div>
      ))}
    </div>
  );
}

export default function Dashboard() {
  const { data, error, loading, reload } = useLoad(() => api.get('/admin/billing/stats'));

  if (loading) return <Loading loading />;
  if (error) return <Alert>{error}</Alert>;

  const parPlan = data.abonnements_par_plan.map((p) => ({ label: p.nom, value: Number(p.abonnes) }));
  const totalActifs = parPlan.reduce((s, p) => s + p.value, 0);
  const parOperation = Object.entries(data.tokens.par_operation || {}).map(([label, v]) => ({ label, value: Number(v) }));
  const parJour = data.tokens.par_jour_30j.map((d) => ({ jour: d.jour, tokens: Number(d.tokens) }));
  const maxJour = Math.max(1, ...parJour.map((d) => d.tokens));
  const statuts = Object.entries(data.abonnements_par_statut || {});
  const revenueText = (values, fallback) => {
    const entries = Object.entries(values || {});
    return entries.length ? entries.map(([currency, amount]) => money(amount, currency)).join(' + ') : money(fallback, 'XAF');
  };

  return (
    <>
      <div className="row">
        <h1>Tableau de bord</h1>
        <button className="btn btn-light" onClick={reload}>Actualiser</button>
      </div>

      <div className="kpis">
        <div className="card kpi"><span className="muted">Abonnés actifs</span><strong>{fmt(totalActifs)}</strong></div>
        <div className="card kpi"><span className="muted">Revenus totaux</span><strong>{revenueText(data.revenus.total_par_devise, data.revenus.total)}</strong></div>
        <div className="card kpi"><span className="muted">Revenus (30 jours)</span><strong>{revenueText(data.revenus['30_jours_par_devise'], data.revenus['30_jours'])}</strong></div>
        <div className="card kpi"><span className="muted">Tokens consommés</span><strong>{fmt(data.tokens.consommes)}</strong></div>
        <div className="card kpi"><span className="muted">Tokens crédités</span><strong>{fmt(data.tokens.credites)}</strong></div>
      </div>

      <div className="grid">
        <div className="card">
          <h3>Abonnés actifs par offre</h3>
          {parPlan.length ? <Bars items={parPlan} /> : <p className="muted">Aucun abonné actif.</p>}
          <p className="muted">
            Par statut : {statuts.length ? statuts.map(([s, n]) => `${s.replace('_', ' ')} ${n}`).join(' · ') : '—'}
          </p>
        </div>

        <div className="card">
          <h3>Consommation par opération</h3>
          {parOperation.length ? <Bars items={parOperation} /> : <p className="muted">Aucune consommation pour l'instant.</p>}
        </div>
      </div>

      <div className="card">
        <h3>Tokens consommés — 30 derniers jours</h3>
        {parJour.length ? (
          <div className="chart">
            {parJour.map((d) => (
              <div key={d.jour} className="chart-col" title={`${d.jour} : ${fmt(d.tokens)} tokens`}>
                <div className="chart-bar" style={{ height: `${(d.tokens / maxJour) * 100}%` }} />
                <span className="chart-label">{String(d.jour).slice(5)}</span>
              </div>
            ))}
          </div>
        ) : <p className="muted">Pas de données sur la période.</p>}
      </div>

      <div className="card">
        <h3>Top consommateurs</h3>
        {data.top_consommateurs.length === 0 && <p className="muted">Aucune consommation pour l'instant.</p>}
        {data.top_consommateurs.length > 0 && (
          <table>
            <thead><tr><th>Utilisateur</th><th>Email</th><th className="num">Tokens</th></tr></thead>
            <tbody>
              {data.top_consommateurs.map((u) => (
                <tr key={u.id}><td>{u.name}</td><td>{u.email}</td><td className="num">{fmt(u.tokens)}</td></tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
