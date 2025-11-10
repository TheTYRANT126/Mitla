/**
 * Easter Egg del Footer
 * 6 clicks consecutivos en el footer revelan un mensaje secreto
 */

(function() {
    let clickCount = 0;
    let clickTimer = null;
    let originalContent = '';
    let isEasterEggActive = false;

    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        const footer = document.querySelector('footer');

        if (!footer) return;

        // Guardar el contenido original
        originalContent = footer.innerHTML;

        // Escuchar clicks en el footer
        footer.addEventListener('click', function() {
            // Si el easter egg ya está activo, no hacer nada
            if (isEasterEggActive) return;

            // Incrementar contador de clicks
            clickCount++;

            // Limpiar el timer anterior
            if (clickTimer) {
                clearTimeout(clickTimer);
            }

            // Si llegamos a 6 clicks, activar el easter egg
            if (clickCount >= 6) {
                activarEasterEgg();
                clickCount = 0;
            } else {
                // Resetear el contador después de 2 segundos de inactividad
                clickTimer = setTimeout(function() {
                    clickCount = 0;
                }, 2000);
            }
        });
    });

    function activarEasterEgg() {
        const footer = document.querySelector('footer');
        if (!footer) return;

        isEasterEggActive = true;

        // Cambiar el contenido del footer
        footer.innerHTML = `
            <div class="container text-center" style="padding: 2rem 0;">
                <h3 style="font-size: 2rem; margin: 0; animation: fadeIn 0.5s;">
                    Elaborado por Emmanuel Velasquez Ortiz 😉
                </h3>
            </div>
        `;

        // Agregar animación de fade in
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: scale(0.8); }
                to { opacity: 1; transform: scale(1); }
            }
        `;
        document.head.appendChild(style);

        // Restaurar el contenido original después de 5 segundos
        setTimeout(function() {
            footer.innerHTML = originalContent;
            isEasterEggActive = false;

            // Remover el estilo de animación
            style.remove();
        }, 5000);
    }
})();
