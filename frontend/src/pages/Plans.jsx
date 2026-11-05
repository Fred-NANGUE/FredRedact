import { useState } from 'react';
import { Check, Crown, Gem, Sparkles } from 'lucide-react';
import { api } from '../api.js';
import { useLoad } from '../hooks.js';
import { Alert, Loading, fmt, money, date } from '../ui.jsx';

export default function Plans({ onGoAccount }) {
  const plans = useLoad(() => api.get('/plans'));
  const current = useLoad(() => api.get('/billing/subscription'));
  const [message, setMessage] = useState({ type: '', text: '' });
  const [busyId, setBusyId] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState('orange_money');
  const [customerPhone, setCustomerPhone] = useState('');

  const sub = current.data?.subscription;

  const subscribe = async (plan) => {
    if (!/^(?:\+?237)?6\d{8}$/.test(customerPhone.trim())) {
      setMessage({ type: 'error', text: 'Saisissez un numéro Mobile Money camerounais valide.' });
      return;
    }

    setBusyId(plan.id);
    setMessage({ type: '', text: '' });
    try {
      const res = await api.post('/billing/checkout', {
        plan_id: plan.id,
        mode_paiement: paymentMethod,
        customer_phone: customerPhone,
      });
      window.location.assign(res.checkout_url);
    } catch (e) {
      setMessage({ type: 'error', text: e.message });
    } finally {
      setBusyId(null);
    }
  };

  return (
    <>
      <h1>Choisissez votre offre</h1>
      {sub && (
        <p className="muted">
          Offre actuelle : <strong>{sub.plan?.nom}</strong>
          {sub.date_fin ? ` (jusqu'au ${date(sub.date_fin)})` : ''} —{' '}
          <button className="link" onClick={onGoAccount}>voir mon compte</button>
        </p>
      )}

      <Alert type={message.type || 'error'}>{message.text}</Alert>
      <Alert>{plans.error}</Alert>
      <Loading loading={plans.loading} />

      <div className="card">
        <h3>Paiement sécurisé avec CinetPay</h3>
        <p className="muted">Les offres payantes sont facturées en francs CFA (XAF). Vous confirmerez votre paiement Orange Money ou MTN Mobile Money sur la page CinetPay.</p>
        <label>
          Opérateur mobile préféré
          <select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)}>
            <option value="orange_money">Orange Money</option>
            <option value="mtn_mobile_money">MTN Mobile Money</option>
          </select>
        </label>
        <label>
          Numéro Mobile Money (Cameroun)
          <input
            type="tel"
            autoComplete="tel"
            placeholder="6XXXXXXXX ou +2376XXXXXXXX"
            value={customerPhone}
            onChange={(event) => setCustomerPhone(event.target.value)}
            pattern="(\+?237)?6[0-9]{8}"
            required
          />
        </label>
      </div>

      <div className="grid">
        {(plans.data || []).map((plan) => {
          const isCurrent = sub?.plan_id === plan.id && sub?.statut === 'actif';
          const isFree = plan.nom.toLowerCase() === 'free';
          const Icon = isFree ? Sparkles : plan.nom.toLowerCase() === 'premium' ? Gem : Crown;
          return (
            <div key={plan.id} className={`card plan ${isCurrent ? 'plan-current' : ''} ${plan.nom.toLowerCase() === 'pro' ? 'plan-featured' : ''}`}>
              <div className="plan-topline">
                <span className="plan-icon"><Icon size={19} /></span>
                {plan.nom.toLowerCase() === 'pro' && <span className="plan-pill">LE PLUS CHOISI</span>}
                {isCurrent && <span className="plan-pill">VOTRE OFFRE</span>}
              </div>
              <h2>{plan.nom}</h2>
              <p className="price">{isFree ? 'Gratuit' : money(plan.prix)}{!isFree && <small> / {plan.duree_jours ? `${plan.duree_jours} jours` : 'offre'}</small>}</p>
              <p className="muted">{plan.description}</p>
              <ul className="features">
                <li><Check size={16} /><span><strong>{fmt(plan.tokens_inclus)}</strong> tokens inclus</span></li>
                <li><Check size={16} /><span>{plan.duree_jours ? `Valable ${plan.duree_jours} jours` : 'Sans limite de durée'}</span></li>
              </ul>
              {isCurrent ? (
                <button className="btn" disabled>Offre actuelle</button>
              ) : isFree ? (
                <button className="btn btn-light" disabled>Attribuée automatiquement</button>
              ) : (
                <button className="btn" disabled={busyId === plan.id} onClick={() => subscribe(plan)}>
                  {busyId === plan.id ? 'Redirection…' : `Payer ${money(plan.prix)}`}
                </button>
              )}
            </div>
          );
        })}
      </div>
    </>
  );
}
