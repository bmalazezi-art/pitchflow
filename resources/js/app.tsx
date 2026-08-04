import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const syncAuthenticatedState = (pageProps: unknown) => {
    const props = pageProps as { auth?: { user?: unknown } };
    document.body.dataset.authenticated = props.auth?.user ? 'true' : 'false';
};

const protectedPathPrefixes = [
    '/admin',
    '/calendar',
    '/customers',
    '/dashboard',
    '/employees',
    '/fields',
    '/organizations',
    '/profile',
    '/reports',
    '/reservations',
    '/settings',
];

const isProtectedPath = (path: string) =>
    protectedPathPrefixes.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));

const verifyProtectedSession = async () => {
    if (! isProtectedPath(window.location.pathname)) {
        return;
    }

    try {
        const response = await fetch('/auth/status', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 401) {
            window.location.replace('/login');
        }
    } catch {
        window.location.reload();
    }
};

const queueProtectedSessionCheck = () => {
    window.setTimeout(() => {
        void verifyProtectedSession();
    }, 0);
};

window.addEventListener('pageshow', (event) => {
    if (event.persisted && isProtectedPath(window.location.pathname)) {
        window.location.reload();
        return;
    }

    void verifyProtectedSession();
});

window.addEventListener('popstate', queueProtectedSessionCheck);

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        void verifyProtectedSession();
    }
});

router.on('navigate', (event) => {
    syncAuthenticatedState(event.detail.page.props);
    void verifyProtectedSession();
});

createInertiaApp({
    title: (title) => (title ? `${title} · PitchFlow` : 'PitchFlow'),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        syncAuthenticatedState(props.initialPage.props);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#2563eb', showSpinner: false },
});
