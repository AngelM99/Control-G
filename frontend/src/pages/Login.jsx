import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { LogIn, KeyRound, Mail, Loader2, AlertCircle } from 'lucide-react';
import { useAuthStore } from '../stores/useAuthStore';
import api from '../api/axios';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const login = useAuthStore((state) => state.login);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');

    try {
      const response = await api.post('/login', {
        email,
        password,
      });

      // Save to Zustand
      login(response.data.user, response.data.token);
      
      // Redirect to dashboard
      navigate('/dashboard', { replace: true });
    } catch (err) {
      if (err.response?.status === 422) {
        setError(err.response.data.errors?.email?.[0] || 'Credenciales incorrectas');
      } else {
        setError('Ocurrió un error al intentar iniciar sesión');
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md animate-fade-in">
        <div className="flex justify-center">
          <div className="h-16 w-16 bg-primary-600 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/30">
            <LogIn className="h-8 w-8 text-white" />
          </div>
        </div>
        <h2 className="mt-6 text-center text-3xl font-extrabold text-slate-900 tracking-tight">
          Control-G
        </h2>
        <p className="mt-2 text-center text-sm text-slate-500">
          Bienvenido. Por favor, inicia sesión para continuar.
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md animate-slide-up">
        <div className="glass-panel py-8 px-4 sm:px-10">
          <form className="space-y-6" onSubmit={handleSubmit}>
            {error && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start">
                <AlertCircle className="h-5 w-5 text-red-500 mt-0.5 mr-2" />
                <span className="text-sm text-red-600">{error}</span>
              </div>
            )}

            <div>
              <label htmlFor="email" className="label-base">
                Correo Electrónico
              </label>
              <div className="mt-1 relative rounded-md shadow-sm">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-slate-400" />
                </div>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="input-base pl-10"
                  placeholder="admin@controlg.com"
                />
              </div>
            </div>

            <div>
              <label htmlFor="password" className="label-base">
                Contraseña
              </label>
              <div className="mt-1 relative rounded-md shadow-sm">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <KeyRound className="h-5 w-5 text-slate-400" />
                </div>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="input-base pl-10"
                  placeholder="••••••••"
                />
              </div>
            </div>

            <div>
              <button
                type="submit"
                disabled={isLoading}
                className="btn btn-primary w-full flex justify-center py-2.5"
              >
                {isLoading ? (
                  <Loader2 className="animate-spin h-5 w-5" />
                ) : (
                  'Iniciar Sesión'
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default Login;
