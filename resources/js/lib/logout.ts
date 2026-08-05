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
    options.onStart?.();
    sessionStorage.setItem(LOGGED_OUT_STORAGE_KEY, '1');

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const form = document.createElement('form');
    const csrf = document.createElement('input');

    form.method = 'POST';
    form.action = '/logout';
    form.style.display = 'none';
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = token;

    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
};
