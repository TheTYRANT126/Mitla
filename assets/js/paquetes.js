/**
 * ============================================
 * RUTA: assets/js/paquetes.js
 * ============================================
 * JavaScript para página de paquetes
 */

document.addEventListener('DOMContentLoaded', function () {

    // ====== CONVERSIÓN DE MXN A USD ======

    /**
     * Obtener el tipo de cambio actual y convertir precios
     */
    async function convertirPreciosUSD() {
        try {
            // Usar API gratuita para obtener tipo de cambio
            // AJUSTABLE: Puedes cambiar la API si encuentras una mejor
            const response = await fetch('https://api.exchangerate-api.com/v4/latest/MXN');
            const data = await response.json();
            const tasaUSD = data.rates.USD; // Cuántos USD vale 1 MXN

            // Convertir todos los precios en la página
            document.querySelectorAll('.precio-usd').forEach(elemento => {
                const precioMXN = parseFloat(elemento.getAttribute('data-mxn'));
                const precioUSD = (precioMXN * tasaUSD).toFixed(2);
                elemento.textContent = '$ ' + precioUSD + ' USD';
            });

            console.log('Precios convertidos a USD. Tasa: 1 MXN = ' + tasaUSD + ' USD');

        } catch (error) {
            console.error('Error al obtener tipo de cambio:', error);
            // Si falla, usar tasa fija aproximada
            const tasaFija = 0.055; // AJUSTABLE: Tasa de respaldo aproximada
            document.querySelectorAll('.precio-usd').forEach(elemento => {
                const precioMXN = parseFloat(elemento.getAttribute('data-mxn'));
                const precioUSD = (precioMXN * tasaFija).toFixed(2);
                elemento.textContent = '$ ' + precioUSD + ' USD (aprox)';
            });
        }
    }

    // Ejecutar conversión al cargar
    convertirPreciosUSD();


    // ====== GALERÍA DE IMÁGENES ROTATIVA ======

    // 🔧 AJUSTABLE: Velocidad de rotación automática de la galería (en milisegundos).
    const VELOCIDAD_ROTACION = 3000; // 3 segundos por imagen

    let galeriaInterval = null;
    let currentImageIndex = 0;
    const galleryItems = document.querySelectorAll('.gallery-item');
    const galleryNavPrev = document.querySelector('.gallery-nav-prev');
    const galleryNavNext = document.querySelector('.gallery-nav-next');

    /**
     * Muestra una imagen específica de la galería por su índice.
     * @param {number} index - El índice de la imagen a mostrar.
     */
    function showImage(index) {
        if (galleryItems.length === 0) return;

        // Asegurarse de que el índice esté en el rango correcto
        currentImageIndex = (index + galleryItems.length) % galleryItems.length;

        // Remover clase active de todos los items
        galleryItems.forEach(item => item.classList.remove('active'));

        // Agregar clase active al item actual
        galleryItems[currentImageIndex].classList.add('active');

        // Ajustar z-index para simular apilamiento
        galleryItems.forEach((item, idx) => {
            const relativeIndex = (idx - currentImageIndex + galleryItems.length) % galleryItems.length;
            item.style.zIndex = galleryItems.length - relativeIndex;
        });
    }

    /**
     * Rotar imágenes de la galería automáticamente
     */
    function rotarImagenes() {
        showImage(currentImageIndex + 1);
    }

    /**
     * Iniciar rotación automática
     */
    function iniciarRotacion() {
        // Limpiar cualquier intervalo anterior para evitar duplicados
        detenerRotacion();
        galeriaInterval = setInterval(rotarImagenes, VELOCIDAD_ROTACION);
    }

    /**
     * Detener rotación automática
     */
    function detenerRotacion() {
        clearInterval(galeriaInterval);
    }

    // Iniciar rotación automática
    if (galleryItems.length > 1) {
        iniciarRotacion();
    }

    /**
     * Al hacer hover en una imagen, traerla al frente
     */
    galleryItems.forEach((item, index) => {
        item.addEventListener('mouseenter', function () {
            // Detener rotación automática al hacer hover
            detenerRotacion();

            // Mostrar la imagen sobre la que se hizo hover
            showImage(index);
        });

        item.addEventListener('mouseleave', function () {
            // Reiniciar rotación al quitar el mouse
            setTimeout(() => {
                iniciarRotacion();
            }, 500); // AJUSTABLE: Espera 500ms antes de reiniciar
        });
    });

    /**
     * Event Listeners para los botones de navegación manual
     */
    if (galleryNavNext) {
        galleryNavNext.addEventListener('click', function () {
            detenerRotacion();
            showImage(currentImageIndex + 1);
            setTimeout(iniciarRotacion, 5000); // Reinicia rotación después de 5 seg de inactividad
        });
    }

    if (galleryNavPrev) {
        galleryNavPrev.addEventListener('click', function () {
            detenerRotacion();
            showImage(currentImageIndex - 1);
            setTimeout(iniciarRotacion, 5000); // Reinicia rotación después de 5 seg de inactividad
        });
    }

    // Inicializar la primera imagen
    showImage(0);


    // ====== SMOOTH SCROLL ======

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });


    // ====== ANIMACIÓN DE ENTRADA ======

    /**
     * Observador para animar elementos al aparecer
     */
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observar bloques de información
    document.querySelectorAll('.info-block').forEach(block => {
        observer.observe(block);
    });


    // ====== LOG DE DEBUG ======
    console.log('Página de paquetes cargada correctamente');
    console.log(`Imágenes de galería: ${galleryItems.length}, velocidad de rotación: ${VELOCIDAD_ROTACION}ms`);
});


/**
 * ====== FUNCIÓN GLOBAL: SELECCIONAR PAQUETE ======
 * Esta función se llama desde el HTML cuando se hace click en un selector
 */
function selectPackage(packageId) {
    // Cambiar video de fondo con transición suave
    const allVideos = document.querySelectorAll('.hero-video');
    const videoActual = document.querySelector('.video-paquete-' + packageId);

    // Ocultar todos los videos
    allVideos.forEach(video => {
        video.classList.remove('active');
    });

    // Mostrar el video del paquete seleccionado
    if (videoActual) {
        videoActual.classList.add('active');
        // Reiniciar el video desde el inicio
        videoActual.currentTime = 0;
        videoActual.play();
    }

    // Actualizar selectores de paquetes
    document.querySelectorAll('.package-selector-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector('.package-selector-btn[data-package-id="' + packageId + '"]').classList.add('active');

    // Construir URL con el ID del paquete
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('id', packageId);

    // Recargar página con el nuevo paquete seleccionado
    // AJUSTABLE: Puedes hacer que NO recargue y solo cambie el contenido con AJAX
    window.location.href = currentUrl.toString();
}


/**
 * ====== FUNCIONES AUXILIARES ======
 */

/**
 * Formatear precio en formato mexicano
 */
function formatearPrecio(precio) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(precio);
}

/**
 * Formatear precio en USD
 */
function formatearPrecioUSD(precio) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(precio);
}