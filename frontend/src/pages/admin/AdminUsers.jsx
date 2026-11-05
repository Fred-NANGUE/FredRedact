import { useState } from 'react';
import { api } from '../../api.js';
import { useLoad } from '../../hooks.js';
import { Alert, Loading, Pager } from '../../ui.jsx';

export default function AdminUsers() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [message, setMessage] = useState({ type: '', text: '' });
  const users = useLoad(() => api.get(`/admin/users?page=${page}&search=${encodeURIComponent(query)}`), [page, query]);

  const updateRole = async (user, role) => {
    try {
      await api.put(`/admin/users/${user.id}`, { role });
      setMessage({ type: 'success', text: 'Rôle mis à jour.' });
      users.reload();
    } catch (e) {
      setMessage({ type: 'error', text: e.message });
    }
  };

  const remove = async (user) => {
    if (!window.confirm(`Supprimer le compte de ${user.name} ?`)) return;
    try {
      await api.del(`/admin/users/${user.id}`);
      setMessage({ type: 'success', text: 'Utilisateur supprimé.' });
      users.reload();
    } catch (e) {
      setMessage({ type: 'error', text: e.message });
    }
  };

  return (
    <>
      <h1>Gestion des utilisateurs</h1>
      <Alert type={message.type || 'error'}>{message.text}</Alert>
      <Alert>{users.error}</Alert>
      <form className="inline filters" onSubmit={(e) => { e.preventDefault(); setPage(1); setQuery(search); }}>
        <label>Recherche<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nom ou email" /></label>
        <button className="btn">Rechercher</button>
      </form>
      <Loading loading={users.loading} />
      {users.data && (
        <div className="card">
          <table>
            <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Stages</th><th>Rapports</th><th>Actions</th></tr></thead>
            <tbody>{users.data.data.map((user) => (
              <tr key={user.id}>
                <td>{user.name}</td><td>{user.email}</td>
                <td><select value={user.role} onChange={(e) => updateRole(user, e.target.value)}>
                  <option value="etudiant">Étudiant</option><option value="admin">Administrateur</option>
                </select></td>
                <td>{user.internships_count}</td><td>{user.reports_count}</td>
                <td><button className="btn btn-danger" onClick={() => remove(user)}>Supprimer</button></td>
              </tr>
            ))}</tbody>
          </table>
          <Pager page={users.data.current_page} last={users.data.last_page} onChange={setPage} />
        </div>
      )}
    </>
  );
}
