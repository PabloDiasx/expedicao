(function () {
    'use strict';

    var rows = document.querySelectorAll('.table-row-link[data-href]');
    if (!rows.length) {
        return;
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }
            window.location.href = row.dataset.href;
        });

        row.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            window.location.href = row.dataset.href;
        });
    });
})();
