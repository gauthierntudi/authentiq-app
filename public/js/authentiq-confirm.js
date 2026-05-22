/**
 * Confirmations Authentiq (SweetAlert2) — remplace window.confirm et iziToast.question.
 *
 * Usage :
 *   const ok = await AuthentiqConfirm.confirm({ title: '...', text: '...' });
 *   const ok = await AuthentiqConfirm.danger({ text: 'Supprimer ?' });
 *   await AuthentiqConfirm.whenConfirmed({ text: '...' }, async () => { ... });
 */
(function (global) {
    'use strict';

    const THEME = {
        background: '#161a26',
        color: '#e8eaef',
        confirmButtonColor: '#4766e7',
        cancelButtonColor: '#40465e',
        customClass: {
            container: 'authentiq-swal-container',
            popup: 'authentiq-swal-popup',
            title: 'authentiq-swal-title',
            htmlContainer: 'authentiq-swal-body',
            icon: 'authentiq-swal-icon',
            actions: 'authentiq-swal-actions',
            confirmButton: 'authentiq-swal-btn authentiq-swal-btn--confirm',
            cancelButton: 'authentiq-swal-btn authentiq-swal-btn--cancel',
        },
    };

    function normalizeOptions(message, options) {
        if (message && typeof message === 'object') {
            return { ...message };
        }

        return {
            text: message != null ? String(message) : '',
            ...(options || {}),
        };
    }

    function buildConfig(opts) {
        const danger = !!opts.danger;
        const confirmClass = THEME.customClass.confirmButton
            + (danger ? ' authentiq-swal-btn--danger' : '');

        return {
            icon: opts.icon || (danger ? 'warning' : 'question'),
            title: opts.title || (danger ? 'Confirmation' : 'Confirmer'),
            text: opts.text || undefined,
            html: opts.html || undefined,
            showCancelButton: opts.showCancelButton !== false,
            confirmButtonText: opts.confirmButtonText || (danger ? 'Oui, continuer' : 'Confirmer'),
            cancelButtonText: opts.cancelButtonText || 'Annuler',
            reverseButtons: true,
            focusCancel: true,
            buttonsStyling: false,
            background: THEME.background,
            color: THEME.color,
            customClass: {
                ...THEME.customClass,
                confirmButton: confirmClass,
            },
            ...opts,
            danger: undefined,
        };
    }

    async function confirm(message, options) {
        const opts = normalizeOptions(message, options);

        if (typeof global.Swal === 'undefined') {
            const fallback = opts.text || opts.title || 'Confirmer ?';
            return global.confirm(fallback);
        }

        const result = await global.Swal.fire(buildConfig(opts));

        return result.isConfirmed === true;
    }

    async function danger(message, options) {
        const opts = normalizeOptions(message, options);

        return confirm({ ...opts, danger: true });
    }

    async function whenConfirmed(message, options, callback) {
        let opts = options;
        let fn = callback;

        if (typeof options === 'function') {
            fn = options;
            opts = {};
        }

        const ok = await confirm(message, opts);
        if (ok && typeof fn === 'function') {
            await fn();
        }

        return ok;
    }

    global.AuthentiqConfirm = {
        confirm,
        danger,
        whenConfirmed,
    };
})(window);
