import React, { useState, useEffect } from 'react';
import { Tag, Loader2, AlertCircle, Plus, X, Edit, Trash2 } from 'lucide-react';
import api from '../api/axios';
import { useAppStore } from '../store/useAppStore';

export default function CategoriesView() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  
  // Create / Edit State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [nombre, setNombre] = useState('');

  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    try {
      setLoading(true);
      const res = await api.get('/categories');
      setCategories(res.data.data || res.data);
    } catch (err) {
      setError('Error al cargar categorías.');
    } finally {
      setLoading(false);
    }
  };

  const openCreateModal = () => {
    setEditingId(null);
    setNombre('');
    setIsModalOpen(true);
  };

  const openEditModal = (cat) => {
    setEditingId(cat.id);
    setNombre(cat.nombre);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    if (!nombre) {
      setError('El nombre de la categoría es obligatorio.');
      return;
    }
    
    try {
      setSaving(true);
      setError('');
      
      if (editingId) {
        await api.put(`/categories/${editingId}`, { nombre });
      } else {
        await api.post('/categories', { nombre });
      }
      
      setIsModalOpen(false);
      fetchCategories();
    } catch (err) {
      setError(err.response?.data?.message || 'Error al guardar categoría.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('¿Está seguro de eliminar esta categoría?')) return;
    try {
      await api.delete(`/categories/${id}`);
      fetchCategories();
    } catch (err) {
      setError('Error al eliminar categoría. Puede estar en uso.');
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Tag className="w-6 h-6 text-primary-500" />
            Mantenedor de Categorías
          </h1>
          <p className="text-slate-500 mt-1">Administra las categorías de tus movimientos.</p>
        </div>
        <button onClick={openCreateModal} className="btn btn-primary gap-2 shadow-lg shadow-primary-500/30">
          <Plus className="w-5 h-5" /> Nueva Categoría
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
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-3xl">
          <table className="w-full text-left">
            <thead className="bg-slate-50 border-b border-slate-100 text-sm font-medium text-slate-500">
              <tr>
                <th className="px-6 py-4">ID</th>
                <th className="px-6 py-4">Nombre de Categoría</th>
                <th className="px-6 py-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {categories.map(c => (
                <tr key={c.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-6 py-4 text-sm font-medium text-slate-500">{c.id}</td>
                  <td className="px-6 py-4">
                    <div className="font-medium text-slate-800">{c.nombre}</div>
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
              {categories.length === 0 && (
                <tr>
                  <td colSpan="3" className="px-6 py-12 text-center text-slate-500">
                    No hay categorías registradas.
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
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-slide-up">
            <div className="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
              <h2 className="text-xl font-bold text-slate-800">
                {editingId ? 'Editar Categoría' : 'Nueva Categoría'}
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
              
              <div>
                <label className="label-base">Nombre de Categoría <span className="text-red-500">*</span></label>
                <input type="text" className="input-base" value={nombre} onChange={e => setNombre(e.target.value)} placeholder="Ej. Alimentación" autoFocus />
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
