import { router } from '@inertiajs/react';

export const LOGGED_OUT_STORAGE_KEY = 'pitchflow:logged-out';

let logoutInProgress = false;

type LogoutOptions = {
    onStart?: () => void;
};

export const logoutAndReplace = (options: LogoutOptions = {}) => {
    if (logoutInProgress) {
        return;
    }

    logoutInProgress = true;
    sessionStorage.setItem(LOGGED_OUT_STORAGE_KEY, '1');

    router.post('/logout', {}, {
        preserveScroll: false,
        replace: true,
        onStart: () => {
            options.onStart?.();
        },
        onCancel: () => {
            logoutInProgress = false;
        },
        onError: () => {
            window.location.replace('/login');
        },
    });
};
