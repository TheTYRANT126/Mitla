

// ====== PREVENIR SCROLL AUTOMÁTICO AL CARGAR ======
// Bloquear scroll hasta que la página esté completamente cargada
let scrollBlocked = true;

// Prevenir scroll inmediatamente
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

// Función para mantener la página arriba
function mantenerpaginaArriba() {
    if (scrollBlocked) {
        window.scrollTo(0, 0);
    }
}

// Monitorear cualquier intento de scroll
window.addEventListener('scroll', mantenerpaginaArriba, { passive: false });

// Forzar posición arriba inmediatamente
window.scrollTo(0, 0);

// Desbloquear scroll después de que todo haya cargado
window.addEventListener('load', function () {
    setTimeout(function () {
        scrollBlocked = false;
        window.removeEventListener('scroll', mantenerpaginaArriba);
    }, 500);
});

document.addEventListener('DOMContentLoaded', function () {

    // ====== VARIABLES GLOBALES ======
    let tasaCambioUSD = 0;
    const paqueteData = PAQUETE_DATA; // Datos del paquete pasados desde PHP

    // Elementos del DOM
    const fechaInput = document.getElementById('fecha');
    const horarioSelect = document.getElementById('horario');
    const numPersonasInput = document.getElementById('numero_personas');
    const btnDecrement = document.getElementById('btn-decrement');
    const btnIncrement = document.getElementById('btn-increment');
    const guiasInfo = document.getElementById('guias-info');
    const nombreDiaSemanaSpan = document.getElementById('nombre-dia-semana');
    const dateInputWrapper = document.querySelector('.date-input-wrapper');
    const numeroGuiasInput = document.getElementById('numero_guias');
    const reservationForm = document.getElementById('reservationForm');

    // ====== CONVERSIÓN A USD ======

    /**
     * Obtener el tipo de cambio MXN a USD
     */
    async function obtenerTasaCambio() {
        try {
            const response = await fetch('https://api.exchangerate-api.com/v4/latest/MXN');
            const data = await response.json();
            tasaCambioUSD = data.rates.USD;

            // Actualizar precios iniciales
            actualizarPreciosUSD();
            calcularTotal();

            console.log('Tasa de cambio obtenida: 1 MXN = ' + tasaCambioUSD + ' USD');
        } catch (error) {
            console.error('Error al obtener tasa de cambio:', error);
            // Tasa de respaldo aproximada
            tasaCambioUSD = 0.055;
            actualizarPreciosUSD();
            calcularTotal();
        }
    }

    /**
     * Actualizar todos los precios a USD en la página
     */
    function actualizarPreciosUSD() {
        // Actualizar precios unitarios
        document.querySelectorAll('.price-usd').forEach(elemento => {
            const precioMXN = parseFloat(elemento.getAttribute('data-mxn'));
            const precioUSD = (precioMXN * tasaCambioUSD).toFixed(2);
            elemento.textContent = '$ ' + precioUSD + ' USD';
        });

        // Actualizar precio unitario de entrada
        const precioEntradaUSD = (paqueteData.precio_entrada * tasaCambioUSD).toFixed(2);
        document.querySelector('.price-usd-unitario').textContent = '$' + precioEntradaUSD + ' USD';

        // Actualizar precio unitario de guía
        const precioGuiaUSD = (paqueteData.precio_guia * tasaCambioUSD).toFixed(2);
        document.querySelector('.price-guia-usd-unitario').textContent = '$' + precioGuiaUSD + ' USD';
    }

    // ====== VALIDACIÓN DE FECHA ======

    /**
     * Configurar el calendario para mostrar solo días disponibles
     */
    function configurarCalendario() {
        // Obtener días disponibles del paquete
        const diasDisponibles = paqueteData.horarios.map(h => h.dia_semana);

        // Mapeo de días en español a números
        const diasMap = {
            'lunes': 1,
            'martes': 2,
            'miercoles': 3,
            'jueves': 4,
            'viernes': 5,
            'sabado': 6,
            'domingo': 0
        };

        const numeroDiasDisponibles = diasDisponibles.map(d => diasMap[d]);

        // Establecer fecha mínima (mañana)
        const manana = new Date();
        manana.setDate(manana.getDate() + 1);
        fechaInput.min = manana.toISOString().split('T')[0];

        // Establecer fecha máxima (3 meses adelante)
        const tresMeses = new Date();
        tresMeses.setMonth(tresMeses.getMonth() + 3);
        fechaInput.max = tresMeses.toISOString().split('T')[0];
    }

    /**
     * Validar que la fecha seleccionada sea un día disponible
     */
    function validarFecha() {
        const fechaSeleccionada = new Date(fechaInput.value + 'T00:00:00');
        const diaSemana = fechaSeleccionada.getDay();

        // Mapeo inverso
        const diasMapInverso = {
            0: 'domingo',
            1: 'lunes',
            2: 'martes',
            3: 'miercoles',
            4: 'jueves',
            5: 'viernes',
            6: 'sabado'
        };

        const nombreDia = diasMapInverso[diaSemana];
        const diasDisponibles = paqueteData.horarios.map(h => h.dia_semana);

        if (!diasDisponibles.includes(nombreDia)) {
            alert('Este día no está disponible para tours. Por favor seleccione otro día.');
            fechaInput.value = '';
            horarioSelect.disabled = true;
            nombreDiaSemanaSpan.textContent = '';
            horarioSelect.innerHTML = '<option value="">Primero seleccione una fecha válida</option>';
            return false;
        }

        return true;
    }

    /**
     * Actualizar horarios disponibles según la fecha seleccionada
     */
    async function actualizarHorarios() {
        if (!fechaInput.value) return;

        // Actualizar el nombre del día de la semana en la UI
        actualizarNombreDia();

        if (!validarFecha()) return;

        const fechaSeleccionada = new Date(fechaInput.value + 'T00:00:00');
        const diaSemana = fechaSeleccionada.getDay();

        const diasMapInverso = {
            0: 'domingo',
            1: 'lunes',
            2: 'martes',
            3: 'miercoles',
            4: 'jueves',
            5: 'viernes',
            6: 'sabado'
        };

        const nombreDia = diasMapInverso[diaSemana];

        // Filtrar horarios del día seleccionado
        const horariosDelDia = paqueteData.horarios.filter(h => h.dia_semana === nombreDia);

        // Obtener disponibilidad para cada horario
        const disponibilidad = await verificarDisponibilidad(fechaInput.value, horariosDelDia);

        // Limpiar y habilitar select
        horarioSelect.innerHTML = '<option value="">Seleccione un horario</option>';
        horarioSelect.disabled = false;

        // Agregar opciones de horario
        horariosDelDia.forEach((horario, index) => {
            const disponible = disponibilidad[index];
            const option = document.createElement('option');

            const horaInicio = formatearHora(horario.hora_inicio);
            const horaFin = formatearHora(horario.hora_fin);

            option.value = `${horario.hora_inicio}|${horario.hora_fin}`;
            option.textContent = `${horaInicio} - ${horaFin}`;

            if (disponible.cupos_disponibles === 0) {
                option.disabled = true;
                option.textContent += ' - COMPLETO';
            } else if (disponible.cupos_disponibles < 10) {
                option.textContent += ` - ${disponible.cupos_disponibles} lugares disponibles`;
            }

            horarioSelect.appendChild(option);
        });
    }

    /**
     * Verificar disponibilidad de horarios para una fecha
     */
    async function verificarDisponibilidad(fecha, horarios) {
        try {
            const response = await fetch(`${window.location.origin}/api/verificar_disponibilidad.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_paquete: paqueteData.id,
                    fecha: fecha,
                    horarios: horarios
                })
            });

            if (!response.ok) {
                throw new Error('Error al verificar disponibilidad');
            }

            const data = await response.json();
            return data.disponibilidad;
        } catch (error) {
            console.error('Error:', error);
            // En caso de error, asumir que hay disponibilidad
            return horarios.map(() => ({
                cupos_disponibles: paqueteData.capacidad_maxima
            }));
        }
    }

    /**
     * Actualiza el texto que muestra el día de la semana seleccionado.
     */
    function actualizarNombreDia() {
        if (!fechaInput.value) {
            nombreDiaSemanaSpan.textContent = '';
            return;
        }

        const fechaSeleccionada = new Date(fechaInput.value + 'T00:00:00');
        const diaSemana = fechaSeleccionada.getDay();
        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const nombreDia = dias[diaSemana];

        if (nombreDia) {
            nombreDiaSemanaSpan.textContent = `(${nombreDia})`;
        } else {
            nombreDiaSemanaSpan.textContent = '';
        }
    }

    /**
     * Formatear hora en formato legible
     */
    function formatearHora(hora) {
        const [horas, minutos] = hora.split(':');
        const h = parseInt(horas);
        const ampm = h >= 12 ? 'pm' : 'am';
        const hora12 = h > 12 ? h - 12 : (h === 0 ? 12 : h);
        return `${hora12}:${minutos} ${ampm}`;
    }

    // ====== SELECTOR DE CANTIDAD DE PERSONAS ======

    /**
     * Incrementar número de personas
     */
    function incrementarPersonas() {
        let valor = parseInt(numPersonasInput.value);
        if (valor < paqueteData.capacidad_maxima) {
            valor++;
            numPersonasInput.value = valor;
            actualizarGuias();
            calcularTotal();
        }
    }

    /**
     * Decrementar número de personas
     */
    function decrementarPersonas() {
        let valor = parseInt(numPersonasInput.value);
        if (valor > 1) {
            valor--;
            numPersonasInput.value = valor;
            actualizarGuias();
            calcularTotal();
        }
    }

    /**
     * Actualizar información de guías necesarios
     * 1 guía cada 5 personas
     */
    function actualizarGuias() {
        const numPersonas = parseInt(numPersonasInput.value);

        // Calcular número de guías: 1 guía cada 5 personas
        // 1-5 personas = 1 guía
        // 6-10 personas = 2 guías
        // 11-15 personas = 3 guías, etc.
        const numGuias = Math.ceil(numPersonas / 5);

        numeroGuiasInput.value = numGuias;

        // Actualizar texto
        const texto = numGuias === 1
            ? 'Se necesita de 1 guía'
            : `Se necesitan de ${numGuias} guías`;

        guiasInfo.innerHTML = `<i class="fas fa-user-friends"></i> ${texto}`;
    }

    // ====== CÁLCULO DE TOTAL ======

    /**
  * Calcular y actualizar el total
  */
    function calcularTotal() {
        const numPersonas = parseInt(numPersonasInput.value);
        const numGuias = parseInt(numeroGuiasInput.value);

        // Calcular subtotales
        const subtotalEntradas = numPersonas * paqueteData.precio_entrada;
        const subtotalGuias = numGuias * paqueteData.precio_guia;
        const totalMXN = subtotalEntradas + subtotalGuias;
        const totalUSD = totalMXN * tasaCambioUSD;

        // Actualizar contador de personas
        document.getElementById('contador-personas').textContent = `x${numPersonas}`;

        // Actualizar subtotal de entradas
        document.querySelector('.subtotal-entradas-mxn').textContent =
            '$' + subtotalEntradas.toFixed(2) + ' mxn';
        document.querySelector('.subtotal-entradas-usd').textContent =
            '$' + (subtotalEntradas * tasaCambioUSD).toFixed(2) + ' USD';

        // Actualizar contador de guías
        document.getElementById('contador-guias').textContent = `x${numGuias}`;

        // Actualizar subtotal de guías
        document.querySelector('.subtotal-guias-mxn').textContent =
            '$' + subtotalGuias.toFixed(2) + ' mxn';
        document.querySelector('.subtotal-guias-usd').textContent =
            '$' + (subtotalGuias * tasaCambioUSD).toFixed(2) + ' USD';

        // Actualizar total
        document.querySelector('.total-mxn').textContent =
            '$' + totalMXN.toFixed(2) + ' mxn';
        document.querySelector('.total-usd').textContent =
            '$' + totalUSD.toFixed(2) + ' USD';
    }
    // ====== VALIDACIÓN Y ENVÍO DEL FORMULARIO ======

    /**
     * Validar formulario antes de enviar
     */
    function validarFormulario(e) {
        // Validación básica de HTML5
        if (!reservationForm.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            reservationForm.classList.add('was-validated');
            return false;
        }

        // Validaciones adicionales
        const fecha = new Date(fechaInput.value);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (fecha < hoy) {
            e.preventDefault();
            alert('La fecha debe ser futura.');
            return false;
        }

        if (!horarioSelect.value) {
            e.preventDefault();
            alert('Por favor seleccione un horario.');
            return false;
        }

        // Agregar estado de loading al botón
        const btnPagar = document.querySelector('.btn-pagar');
        btnPagar.classList.add('loading');
        btnPagar.disabled = true;

        return true;
    }

    // ====== EVENT LISTENERS ======

    // Configurar calendario al cargar
    configurarCalendario();

    // Obtener tasa de cambio
    obtenerTasaCambio();

    // Evento de cambio de fecha
    fechaInput.addEventListener('change', actualizarHorarios);

    // Hacer que todo el contenedor del input de fecha sea clickeable
    if (dateInputWrapper) {
        dateInputWrapper.addEventListener('click', () => {
            // Intenta abrir el selector de fecha nativo del navegador
            try {
                fechaInput.showPicker();
            } catch (e) { /* Fallback para navegadores que no soportan showPicker() */ }
        });
    }

    // Eventos de selector de personas
    btnIncrement.addEventListener('click', incrementarPersonas);
    btnDecrement.addEventListener('click', decrementarPersonas);

    // También permitir cambio manual
    numPersonasInput.addEventListener('change', function () {
        let valor = parseInt(this.value);

        if (isNaN(valor) || valor < 1) {
            valor = 1;
        } else if (valor > paqueteData.capacidad_maxima) {
            valor = paqueteData.capacidad_maxima;
        }

        this.value = valor;
        actualizarGuias();
        calcularTotal();
    });

    // Evento de horario seleccionado
    horarioSelect.addEventListener('change', function () {
        const horarioInfo = document.getElementById('horario-info');

        if (this.value) {
            const texto = this.options[this.selectedIndex].text;
            horarioInfo.innerHTML = `
                <div class="disponibilidad-info disponible">
                    <i class="fas fa-check-circle"></i> ${texto}
                </div>
            `;
        } else {
            horarioInfo.innerHTML = '';
        }
    });

    // Validación del formulario
    reservationForm.addEventListener('submit', validarFormulario);

    // Inicializar cálculos
    actualizarGuias();

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

    // ====== LOG DE DEBUG ======
    console.log('Página de reservación cargada correctamente');
    console.log('Datos del paquete:', paqueteData);
});