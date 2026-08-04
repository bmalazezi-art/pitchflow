import { router } from '@inertiajs/react';

export const LOGGED_OUT_STORAGE_KEY = 'pitchflow:logged-out';

export const logoutAndReplace = () => {
    sessionStorage.setItem(LOGGED_OUT_STORAGE_KEY, '1');

    router.post('/logout', {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
        onCancel: () => {
            sessionStorage.removeItem(LOGGED_OUT_STORAGE_KEY);
        },
        onError: () => {
            sessionStorage.removeItem(LOGGED_OUT_STORAGE_KEY);
        },
        onSuccess: () => {
            window.history.replaceState(null, '', '/login');
            window.location.replace('/login');
        },
    });
};
