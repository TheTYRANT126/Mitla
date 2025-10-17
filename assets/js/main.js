
document.addEventListener('DOMContentLoaded', function () {

    //  CARRUSEL HERO 
    const heroCarousel = {
        currentSlide: 0,
        slides: document.querySelectorAll('.hero-slide'),
        indicators: document.querySelectorAll('.hero-indicators .indicator'),
        interval: null,

        init: function () {
            if (this.slides.length === 0) return;

            // Configurar eventos de indicadores
            this.indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    this.goToSlide(index);
                    this.resetInterval();
                });
            });

            // Iniciar carrusel automático
            this.startAutoPlay();

            // Pausar en hover
            const carouselElement = document.querySelector('.hero-carousel');
            if (carouselElement) {
                carouselElement.addEventListener('mouseenter', () => this.pauseAutoPlay());
                carouselElement.addEventListener('mouseleave', () => this.startAutoPlay());
            }
        },

        goToSlide: function (index) {
            // Remover clase active de slide actual
            this.slides[this.currentSlide].classList.remove('active');
            this.indicators[this.currentSlide].classList.remove('active');

            // Actualizar índice
            this.currentSlide = index;

            // Agregar clase active al nuevo slide
            this.slides[this.currentSlide].classList.add('active');
            this.indicators[this.currentSlide].classList.add('active');
        },

        nextSlide: function () {
            const next = (this.currentSlide + 1) % this.slides.length;
            this.goToSlide(next);
        },

        startAutoPlay: function () {
            this.interval = setInterval(() => {
                this.nextSlide();
            }, 2000); // Cambia cada 2 segundos
        },

        pauseAutoPlay: function () {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        resetInterval: function () {
            this.pauseAutoPlay();
            this.startAutoPlay();
        }
    };

    // Inicializar carrusel
    heroCarousel.init();


    // ANIMACIÓN AL HACER SCROLL 
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Aplicar a secciones
    document.querySelectorAll('.features, .packages, .info-section').forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(30px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });


    // VALIDACIÓN DE FORMULARIOS para futuras páginas
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });


    // SCROLL PARA ENLACES INTERNOS 
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


    // TOOLTIP BOOTSTRAP 
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });


    //  AJUSTE DE ALTURA DEL HERO EN MÓVILES 
    function adjustHeroHeight() {
        const heroSection = document.querySelector('.hero-carousel-section');
        if (heroSection && window.innerWidth < 768) {
            const vh = window.innerHeight * 0.01;
            heroSection.style.height = `${vh * 70}px`;
        }
    }

    adjustHeroHeight();
    window.addEventListener('resize', adjustHeroHeight);


    //  LOG DE DEBUG solo en desarrollo
    console.log('Mitla Tours - Sistema cargado correctamente');
    console.log('Slides encontrados:', heroCarousel.slides.length);
});


// FUNCIÓN PARA FORMATEAR PRECIOS 
function formatPrice(price) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(price);
}


//  FUNCIÓN PARA FORMATEAR FECHAS 
function formatDate(date) {
    return new Intl.DateTimeFormat('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}