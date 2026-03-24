import axios from 'axios';
import { startTransition, useEffect, useState } from 'react';
import Dashboard from '../dashboard';

const demoCredentials = [
    {
        title: 'Platform Admin',
        login: 'platform.admin',
        helper: 'Can see every organization, hospital, and pharmacy.',
    },
    {
        title: 'Organization Admin',
        login: 'org001.admin',
        helper: 'Can see one organization and all hospitals inside it.',
    },
    {
        title: 'Hospital Admin',
        login: 'hospital001.admin',
        helper: 'Can see one hospital and its pharmacies only.',
    },
];

function LoadingScreen() {
    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#eef4ff] px-6 py-10">
            <div className="absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-sky-300/40 blur-3xl" />
            <div className="absolute right-0 top-0 h-64 w-64 rounded-full bg-rose-300/40 blur-3xl" />
            <div className="absolute left-1/2 top-20 h-80 w-80 -translate-x-1/2 rounded-full bg-indigo-200/40 blur-3xl" />
            <div className="relative w-full max-w-xl rounded-[2rem] border border-slate-200/80 bg-white/90 p-8 text-center shadow-[0_32px_90px_rgba(15,23,42,0.12)] backdrop-blur">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-[1.5rem] bg-gradient-to-br from-[#21345b] to-[#4b74c6] text-white shadow-lg shadow-slate-300">
                    <svg className="h-8 w-8 animate-pulse" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                        <path d="M12 4v16M4 12h16" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </div>
                <h1 className="mt-6 text-2xl font-semibold text-slate-950">Loading pharamaPOC</h1>
                <p className="mt-3 text-sm leading-6 text-slate-500">
                    Checking your session and opening the dashboard.
                </p>
            </div>
        </div>
    );
}

function LoginScreen({ busy, error, onSubmit }) {
    const [form, setForm] = useState({
        login: '',
        password: '',
        remember: true,
    });

    return (
        <div className="relative min-h-screen overflow-hidden bg-[#eef4ff]">
            <div className="absolute -left-28 bottom-0 h-80 w-80 rounded-full bg-sky-300/30 blur-3xl" />
            <div className="absolute right-0 top-0 h-72 w-72 rounded-full bg-rose-300/30 blur-3xl" />
            <div className="absolute left-1/3 top-24 h-96 w-96 rounded-full bg-indigo-200/30 blur-3xl" />

            <div className="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid w-full overflow-hidden rounded-[2.25rem] border border-white/70 bg-white/90 shadow-[0_40px_110px_rgba(15,23,42,0.12)] backdrop-blur lg:grid-cols-[1.05fr_0.95fr]">
                    <section className="relative overflow-hidden border-b border-slate-200/70 bg-[linear-gradient(180deg,#f7fbff_0%,#eef4ff_100%)] p-8 sm:p-10 lg:border-b-0 lg:border-r">
                        <div className="inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[#21345b] text-white">
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M12 5v14M5 12h14" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </span>
                            pharamaPOC
                        </div>

                        <div className="mt-8 max-w-xl">
                            <p className="text-sm font-semibold uppercase tracking-[0.24em] text-[#4b74c6]">Pharmacy Dashboard</p>
                            <h1 className="mt-4 text-4xl font-extrabold tracking-tight text-slate-950 sm:text-5xl">
                                Welcome back
                            </h1>
                        </div>

                        <div className="relative mt-10 overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 p-8 shadow-[0_18px_45px_rgba(59,130,246,0.08)]">
                            <div className="absolute -right-5 -top-5 h-24 w-24 rounded-full bg-rose-200/60 blur-2xl" />
                            <div className="absolute -left-5 bottom-0 h-28 w-28 rounded-full bg-sky-200/60 blur-2xl" />

                            <div className="relative flex min-h-[280px] items-center justify-center">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-[1.5rem] bg-[#21345b] px-5 py-5 text-white shadow-lg">
                                        <p className="text-sm font-semibold">pharamaPOC</p>
                                        <p className="mt-3 text-2xl font-bold">Login</p>
                                        <p className="mt-2 text-sm text-slate-200">Simple access for pharmacy reports and records.</p>
                                    </div>
                                    <div className="grid gap-4">
                                        <div className="rounded-[1.5rem] bg-white px-5 py-4 shadow-sm">
                                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Access</p>
                                            <p className="mt-2 text-base font-semibold text-slate-900">Organization based</p>
                                        </div>
                                        <div className="rounded-[1.5rem] bg-white px-5 py-4 shadow-sm">
                                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Exports</p>
                                            <p className="mt-2 text-base font-semibold text-slate-900">Fast Excel export</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8">
                            <p className="text-sm font-semibold text-slate-700">Quick demo logins</p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                {demoCredentials.map((item) => (
                                    <button
                                        className="rounded-[1.35rem] border border-slate-200 bg-white px-4 py-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#4b74c6] hover:shadow-md"
                                        key={item.title}
                                        onClick={() => {
                                            setForm((current) => ({
                                                ...current,
                                                login: item.login,
                                                password: 'password',
                                            }));
                                        }}
                                        type="button"
                                    >
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[#4b74c6]">{item.title}</p>
                                        <p className="mt-3 text-base font-semibold text-slate-950">{item.login}</p>
                                        <p className="mt-2 text-sm leading-6 text-slate-500">{item.helper}</p>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="flex items-center justify-center bg-white p-8 sm:p-10">
                        <div className="w-full max-w-md">
                            <p className="text-sm font-semibold uppercase tracking-[0.24em] text-[#4b74c6]">Sign In</p>
                            <h2 className="mt-3 text-4xl font-bold text-slate-950">pharamaPOC</h2>
                            <p className="mt-3 text-base leading-7 text-slate-500">
                                Enter your username or email and password to open the app.
                            </p>

                            <form
                                className="mt-8 space-y-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    void onSubmit(form);
                                }}
                            >
                                <label className="block">
                                    <span className="mb-2 block text-sm font-semibold text-slate-700">Username or Email</span>
                                    <input
                                        autoComplete="username"
                                        className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#4b74c6] focus:ring-4 focus:ring-[#4b74c6]/10"
                                        onChange={(event) => {
                                            setForm((current) => ({
                                                ...current,
                                                login: event.target.value,
                                            }));
                                        }}
                                        placeholder="platform.admin"
                                        value={form.login}
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-sm font-semibold text-slate-700">Password</span>
                                    <input
                                        autoComplete="current-password"
                                        className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#4b74c6] focus:ring-4 focus:ring-[#4b74c6]/10"
                                        onChange={(event) => {
                                            setForm((current) => ({
                                                ...current,
                                                password: event.target.value,
                                            }));
                                        }}
                                        placeholder="password"
                                        type="password"
                                        value={form.password}
                                    />
                                </label>

                                <label className="flex items-center gap-3 text-sm text-slate-600">
                                    <input
                                        checked={form.remember}
                                        className="h-4 w-4 rounded border-slate-300 text-[#4b74c6] focus:ring-[#4b74c6]"
                                        onChange={(event) => {
                                            setForm((current) => ({
                                                ...current,
                                                remember: event.target.checked,
                                            }));
                                        }}
                                        type="checkbox"
                                    />
                                    Keep me signed in on this browser
                                </label>

                                {error ? (
                                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                        {error}
                                    </div>
                                ) : null}

                                <button
                                    className="inline-flex w-full items-center justify-center rounded-2xl bg-[#21345b] px-5 py-3.5 text-base font-semibold text-white transition hover:bg-[#172741] disabled:cursor-not-allowed disabled:opacity-60"
                                    disabled={busy}
                                    type="submit"
                                >
                                    {busy ? 'Signing in...' : 'Login'}
                                </button>
                            </form>

                            <div className="mt-8 rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-4">
                                <p className="text-sm font-semibold text-slate-900">Demo password</p>
                                <p className="mt-2 text-sm text-slate-500">All sample accounts use: <span className="font-semibold text-slate-700">password</span></p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    );
}

export default function App() {
    const [user, setUser] = useState(null);
    const [booting, setBooting] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    async function loadCurrentUser() {
        try {
            const response = await axios.get('/api/v1/auth/me');
            startTransition(() => {
                setUser(response.data.data);
            });
        } catch (requestError) {
            if (requestError?.response?.status !== 401) {
                setError(requestError?.response?.data?.message ?? 'Could not load the current session.');
            }
            setUser(null);
        } finally {
            setBooting(false);
        }
    }

    useEffect(() => {
        void loadCurrentUser();
    }, []);

    async function handleLogin(form) {
        setBusy(true);
        setError('');

        try {
            await axios.get('/sanctum/csrf-cookie');
            const response = await axios.post('/api/v1/auth/login', form);
            setUser(response.data.data);
        } catch (requestError) {
            const errors = requestError?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;

            setError(firstError ?? requestError?.response?.data?.message ?? 'Sign-in failed.');
        } finally {
            setBusy(false);
        }
    }

    async function handleLogout() {
        setBusy(true);

        try {
            await axios.post('/api/v1/auth/logout');
            setUser(null);
        } finally {
            setBusy(false);
        }
    }

    if (booting) {
        return <LoadingScreen />;
    }

    if (!user) {
        return <LoginScreen busy={busy} error={error} onSubmit={handleLogin} />;
    }

    return <Dashboard currentUser={user} onLogout={handleLogout} />;
}
