<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.appAlert = function (options) {
        return Swal.fire(Object.assign({
            confirmButtonColor: '#4682B4',
            confirmButtonText: 'OK',
        }, options || {}));
    };

    (function () {
        const errors = @json($errors->all());
        const swalConfig = @json(session('swal'));
        const status = @json(session('status'));

        if (errors.length > 0) {
            window.appAlert({
                icon: 'error',
                title: 'Atenção',
                text: errors.join('\n'),
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
