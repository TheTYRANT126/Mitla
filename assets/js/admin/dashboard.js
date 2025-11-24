
document.addEventListener('DOMContentLoaded', function () {

    // Auto-refresh cada 5 minutos
    setInterval(function () {
        const badge = document.createElement('span');
        badge.className = 'badge bg-info position-fixed top-0 end-0 m-3';
        badge.textContent = 'Actualizando datos...';
        document.body.appendChild(badge);

        setTimeout(function () {
            location.reload();
        }, 1000);
    }, 300000); // 5 minutos

    // Tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Confirmación para acciones destructivas
    document.querySelectorAll('.btn-danger').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('¿Está seguro de realizar esta acción?')) {
                e.preventDefault();
            }
        });
    });

    // Hacer las filas de tabla clickeables
    document.querySelectorAll('table tbody tr').forEach(function (row) {
        const link = row.querySelector('a.btn-outline-primary');
        if (link) {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function (e) {
                if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
                    link.click();
                }
            });
        }
    });

    console.log('Dashboard cargado correctamente');
});

/**
 * Función para formatear números como moneda
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

/**
 * Función para formatear fechas
 */
function formatDate(date) {
    return new Intl.DateTimeFormat('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

/**
 * Mostrar loading overlay
 */
function showLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Cargando...</span></div>';
    overlay.id = 'loadingOverlay';
    document.body.appendChild(overlay);
}

/**
 * Ocultar loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}