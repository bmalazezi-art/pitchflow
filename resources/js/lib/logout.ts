import { router } from '@inertiajs/react';

export const LOGGED_OUT_STORAGE_KEY = 'pitchflow:logged-out';

let logoutInProgress = false;

type LogoutOptions = {
    onStart?: () => void;
    onCancel?: () => void;
    onError?: () => void;
};

export const logoutAndReplace = (options: LogoutOptions = {}) => {
    if (logoutInProgress) {
        return;
    }

    logoutInProgress = true;
    options.onStart?.();
    sessionStorage.setItem(LOGGED_OUT_STORAGE_KEY, '1');

    router.post('/logout', {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
        onCancel: () => {
            logoutInProgress = false;
            sessionStorage.removeItem(LOGGED_OUT_STORAGE_KEY);
            options.onCancel?.();
        },
        onError: () => {
            logoutInProgress = false;
            sessionStorage.removeItem(LOGGED_OUT_STORAGE_KEY);
            options.onError?.();
        },
        onSuccess: () => {
            window.history.replaceState(null, '', '/login');
            window.location.replace('/login');
        },
    });
};
