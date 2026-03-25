<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.Swal || document.write('<script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"><\/script>');</script>
<script>
    window.appAlert = function (options) {
        return Swal.fire(Object.assign({
            confirmButtonColor: '#4682B4',
            confirmButtonText: 'OK',
        }, options || {}));
    };

    window.confirmModelDeleteForm = function (form) {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        const confirmationFlag = form.querySelector('.js-delete-confirmation-flag');
        const modelName = form.dataset.modelName || 'este modelo';

        if (confirmationFlag) {
            confirmationFlag.value = '0';
        }

        if (form.dataset.deleteConfirmed === '1') {
            form.dataset.deleteConfirmed = '0';
            return true;
        }

        window.appAlert({
            icon: 'warning',
            title: 'Remover modelo?',
            text: `Deseja remover "${modelName}"?`,
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true,
        }).then(function (result) {
            if (!result || !result.isConfirmed) {
                return;
            }

            if (confirmationFlag) {
                confirmationFlag.value = '1';
            }

            form.dataset.deleteConfirmed = '1';
            HTMLFormElement.prototype.submit.call(form);
        });

        return false;
    };

    (function () {
        const errors = @json($errors->all());
        const swalConfig = @json(session('swal'));
        const status = @json(session('status'));

        if (errors.length > 0) {
            window.appAlert({
                icon: 'error',
                title: 'Atenção',
                html: errors.map(function (e) { return '<p style="margin:0.25em 0">' + e.replace(/[<>&"]/g, function (c) { return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]; }) + '</p>'; }).join(''),
            });
            return;
        }

        if (swalConfig) {
            window.appAlert(swalConfig);
            return;
        }

        if (status) {
            window.appAlert({
                icon: 'success',
                title: 'Sucesso',
                text: status,
            });
        }
    })();
</script>
@stack('sweetalert')
