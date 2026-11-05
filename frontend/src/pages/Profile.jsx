import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Alert, Loading } from '../ui.jsx';

export default function Profile({ onProfileUpdate }) {
  const [form, setForm] = useState({ name: '', email: '', current_password: '', password: '', password_confirmation: '' });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    let active = true;
    api.get('/profile')
      .then((user) => active && setForm((value) => ({ ...value, name: user.name, email: user.email })))
      .catch((e) => active && setError(e.message))
      .finally(() => active && setLoading(false));
    return () => { active = false; };
  }, []);

  const update = async (event) => {
    event.preventDefault();
    setError('');
    setSuccess('');
    try {
      const body = { name: form.name, email: form.email };
      if (form.password) Object.assign(body, {
        current_password: form.current_password,
        password: form.password,
        password_confirmation: form.password_confirmation,
      });
      const user = await api.put('/profile', body);
      setForm((value) => ({ ...value, name: user.name, email: user.email, current_password: '', password: '', password_confirmation: '' }));
      onProfileUpdate?.(user);
      setSuccess('Profil mis à jour.');
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <>
      <h1>Mon profil</h1>
      <Alert>{error}</Alert>
      <Alert type="success">{success}</Alert>
      <Loading loading={loading} />
      {!loading && (
        <form className="card profile-form" onSubmit={update}>
          <label>Nom complet<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required maxLength="120" /></label>
          <label>Email<input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required /></label>
          <hr />
          <h3>Modifier le mot de passe (facultatif)</h3>
          <label>Mot de passe actuel<input type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} /></label>
          <label>Nouveau mot de passe<input type="password" autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} minLength="8" /></label>
          <label>Confirmer le nouveau mot de passe<input type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} minLength="8" /></label>
          <button className="btn">Enregistrer</button>
        </form>
      )}
    </>
  );
}
