import { router } from '@inertiajs/react';

export const logoutAndReplace = () => {
    router.post('/logout', {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
        onSuccess: () => {
            window.history.replaceState(null, '', '/login');
            window.location.replace('/login');
        },
    });
};
