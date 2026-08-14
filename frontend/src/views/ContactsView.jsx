import React, { useState, useEffect } from 'react';
import { Users, Loader2, AlertCircle, Plus, X, Search, Save, Edit, Trash2 } from 'lucide-react';
import api from '../api/axios';
import { useAppStore } from '../store/useAppStore';

export default function ContactsView() {
  const [contacts, setContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  
  // Create / Edit State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState(null);
  
  const [formData, setFormData] = useState({
    dni: '',
    nombre: '',
    alias: '',
    telefono: '',
    tipo_contacto: 'AMBOS',
    correo: ''
  });

  useEffect(() => {
    fetchContacts();
  }, []);

  const fetchContacts = async () => {
    try {
      setLoading(true);
      const res = await api.get('/contacts');
      setContacts(res.data.data || res.data);
    } catch (err) {
      setError('Error al cargar contactos.');
    } finally {
      setLoading(false);
    }
  };

  const openCreateModal = () => {
    setEditingId(null);
    setFormData({ dni: '', nombre: '', alias: '', telefono: '', tipo_contacto: 'AMBOS', correo: '' });
    setIsModalOpen(true);
  };

  const openEditModal = (contact) => {
    setEditingId(contact.id);
    setFormData({
      dni: contact.dni || '',
      nombre: contact.nombre || '',
      alias: contact.alias || '',
      telefono: contact.telefono || '',
      tipo_contacto: contact.tipo_contacto?.id || 'AMBOS',
      correo: contact.correo || ''
    });
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    if (!formData.nombre) {
      setError('El nombre es obligatorio.');
      return;
    }
    
    try {
      setSaving(true);
      setError('');
      
      if (editingId) {
        await api.put(`/contacts/${editingId}`, formData);
      } else {
        await api.post('/contacts', formData);
      }
      
      setIsModalOpen(false);
      fetchContacts();
    } catch (err) {
      setError(err.response?.data?.message || 'Error al guardar contacto.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('¿Está seguro de desactivar este contacto?')) return;
    try {
      await api.delete(`/contacts/${id}`);
      fetchContacts();
    } catch (err) {
      setError('Error al eliminar contacto.');
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Users className="w-6 h-6 text-primary-500" />
            Mantenedor de Contactos
          </h1>
          <p className="text-slate-500 mt-1">Gestiona tus deudores y acreedores.</p>
        </div>
        <button onClick={openCreateModal} className="btn btn-primary gap-2 shadow-lg shadow-primary-500/30">
          <Plus className="w-5 h-5" /> Nuevo Contacto
        </button>
      </div>

      {error && !isModalOpen && (
        <div className="bg-red-50 p-4 rounded-xl flex items-center text-red-600 gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{error}</span>
        </div>
      )}

      {loading ? (
        <div className="flex justify-center p-12">
          <Loader2 className="w-8 h-8 animate-spin text-primary-500" />
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <table className="w-full text-left">
            <thead className="bg-slate-50 border-b border-slate-100 text-sm font-medium text-slate-500">
              <tr>
                <th className="px-6 py-4">Documento</th>
                <th className="px-6 py-4">Nombre Completo</th>
                <th className="px-6 py-4">Teléfono</th>
                <th className="px-6 py-4">Clasificación</th>
                <th className="px-6 py-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {contacts.map(c => (
                <tr key={c.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-6 py-4 text-sm font-medium text-slate-700">{c.dni || '-'}</td>
                  <td className="px-6 py-4">
                    <div className="font-medium text-slate-800">{c.nombre}</div>
                    <div className="text-xs text-slate-500">{c.alias}</div>
                  </td>
                  <td className="px-6 py-4 text-sm text-slate-600">{c.telefono || '-'}</td>
                  <td className="px-6 py-4">
                    <span className="px-2 py-1 bg-primary-50 text-primary-700 rounded text-xs font-medium border border-primary-100">
                      {c.tipo_contacto?.label || c.tipo_contacto}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-right space-x-2">
                    <button onClick={() => openEditModal(c)} className="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button onClick={() => handleDelete(c.id)} className="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
              {contacts.length === 0 && (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-slate-500">
                    No hay contactos registrados.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-slide-up">
            <div className="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
              <h2 className="text-xl font-bold text-slate-800">
                {editingId ? 'Editar Contacto' : 'Nuevo Contacto'}
              </h2>
              <button onClick={() => setIsModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>
            
            <div className="p-6 space-y-4">
              {error && (
                <div className="p-3 bg-red-50 text-red-600 rounded-xl text-sm font-medium">
                  {error}
                </div>
              )}
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label-base">DNI / Documento</label>
                  <input type="text" className="input-base" value={formData.dni} onChange={e => setFormData({...formData, dni: e.target.value})} placeholder="Ej. 70012345" />
                </div>
                <div>
                  <label className="label-base">Teléfono / Celular</label>
                  <input type="text" className="input-base" value={formData.telefono} onChange={e => setFormData({...formData, telefono: e.target.value})} placeholder="Ej. 987654321" />
                </div>
              </div>
              
              <div>
                <label className="label-base">Nombre Completo <span className="text-red-500">*</span></label>
                <input type="text" className="input-base" value={formData.nombre} onChange={e => setFormData({...formData, nombre: e.target.value})} placeholder="Ej. Juan Pérez" />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label-base">Alias (Opcional)</label>
                  <input type="text" className="input-base" value={formData.alias} onChange={e => setFormData({...formData, alias: e.target.value})} placeholder="Ej. Juan P." />
                </div>
                <div>
                  <label className="label-base">Clasificación</label>
                  <select className="input-base" value={formData.tipo_contacto} onChange={e => setFormData({...formData, tipo_contacto: e.target.value})}>
                    <option value="DEUDOR">Solo Deudor</option>
                    <option value="ACREEDOR">Solo Acreedor</option>
                    <option value="AMBOS">Ambos</option>
                  </select>
                </div>
              </div>
            </div>
            
            <div className="p-6 bg-slate-50 flex justify-end gap-3 border-t border-slate-100">
              <button onClick={() => setIsModalOpen(false)} className="btn btn-secondary">Cancelar</button>
              <button onClick={handleSave} disabled={saving} className="btn btn-primary px-8">
                {saving ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
