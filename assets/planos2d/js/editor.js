import { aplicarSnap, moverAReferencia } from './ajuste-elementos.js';
import { descartarPlanoTrabajo, guardarPlanoTrabajo, obtenerPlanoTrabajo } from './autoguardado-plano.js';
import { configurarAtajosEditor } from './atajos-editor.js';
import { calcularCotas } from './cotas.js';
import { configurarDragInteriores } from './drag-elementos.js';
import { calcularDistanciasEntreInteriores, calcularDistanciasEntreTodosInteriores } from './distancias-interiores.js';
import { calcularDistanciasElemento } from './distancias-elementos.js';
import { calcularDistanciasParedesPdf, combinarDistanciasPdf } from './distancias-pdf.js';
import { calcularDimensionesElementosPdf } from './dimensiones-elementos-pdf.js';
import { configurarEdicionCotas } from './edicion-cotas.js';
import { calcularSistemaElementos } from './elementos.js';
import { ajustarMuralAMuro, ajustarMuralAReferencia, esElementoMural } from './elementos-murales.js';
import { calcularGeometria, describirCierre, DIRECCIONES } from './geometria.js';
import {
    clonarEstado,
    confirmarCambio,
    crearHistorialPlano,
    deshacer,
    marcarGuardado,
    planoModificado,
    puedeDeshacer,
    puedeRehacer,
    rehacer,
} from './historial-plano.js';
import { calcularSistemaInteriores, posicionInicialInterior } from './interiores.js';
import {
    actualizarElemento,
    actualizarElementoInterior,
    actualizarMuro,
    agregarElementoInterior,
    agregarMuro,
    agregarPuerta,
    agregarVentana,
    cargarPlanoDesdeJSON,
    crearPlano,
    duplicarElementoInterior,
    eliminarElemento,
    eliminarElementoInterior,
    eliminarUltimoMuro,
    limpiarPlano,
    moverElementoInterior,
    obtenerElementos,
    obtenerElementosInteriores,
    obtenerMuros,
    serializarPlano,
} from './plano.js';
import { renderizarPlano } from './render-svg.js';

const plano = crearPlano();
const historial = crearHistorialPlano(serializarPlano(plano));
let direccionSeleccionada = 'arriba';
let elementoInteriorSeleccionadoId = null;
let muroSeleccionadoId = null;
let huecoSeleccionadoId = null;
let mostrarDistanciasTodosElementos = false;
let estadoAntesDrag = null;
let editorCotas = null;
let grupoToolbarAbierto = null;
let herramientaActiva = null;
const SVG_NS = 'http://www.w3.org/2000/svg';
const ESTILO_PLANO = {
    fuente: 'Arial, Helvetica, sans-serif',
    muro: { color: '#111111' },
    cotaGeneral: { color: '#252b33', linea: '#6f7884', grosor: 0.42, texto: 5.4, halo: 1.1 },
    cotaParcial: { color: '#4b5561', linea: '#9aa3ad', grosor: 0.34, texto: 4.4, halo: 0.9 },
    cotaElemento: { color: '#3f4750', linea: '#6f7884', grosor: 0.32, texto: 4.8, halo: 0.85 },
    cotaParedElemento: { color: '#5d6670', linea: '#8a94a0', grosor: 0.28, texto: 4.3, halo: 0.8 },
    puerta: { color: '#252b33', arco: '#8f98a3', grosor: 0.76, arcoGrosor: 0.4 },
    ventana: { color: '#252b33', grosor: 0.72 },
    simbolo: { color: '#252b33', auxiliar: '#6f7884', grosor: 0.88, detalle: 0.5 },
    distancia: { color: '#707984', linea: '#a0a8b2', grosor: 0.24, texto: 4.1, halo: 0.75 },
};
const ESTILOS_SVG_PDF = construirEstilosSvgPdf(ESTILO_PLANO);

const elementos = {
    form: document.querySelector('[data-wall-form]'),
    longitud: document.querySelector('[data-wall-length]'),
    muroAuxiliar: document.querySelector('[data-wall-auxiliary]'),
    direcciones: [...document.querySelectorAll('[data-direction]')],
    svg: document.querySelector('[data-plan-svg]'),
    gruposToolbar: [...document.querySelectorAll('.planos2d-tool-group')],
    botonesToolbar: [...document.querySelectorAll('[data-toolbar-group]')],
    lista: document.querySelector('[data-wall-list]'),
    estado: document.querySelector('[data-status]'),
    eliminarUltimo: document.querySelector('[data-remove-last]'),
    vaciar: document.querySelector('[data-clear-plan]'),
    deshacer: document.querySelector('[data-undo-plan]'),
    rehacer: document.querySelector('[data-redo-plan]'),
    modificado: document.querySelector('[data-dirty-indicator]'),
    recuperacionPanel: document.querySelector('[data-recovery-panel]'),
    recuperarPlano: document.querySelector('[data-recover-plan]'),
    descartarRecuperacion: document.querySelector('[data-discard-recovery]'),
    comprobarCierre: document.querySelector('[data-check-close]'),
    imprimir: document.querySelector('[data-print-plan]'),
    exportarJSON: document.querySelector('[data-export-json]'),
    importarJSON: document.querySelector('[data-import-json]'),
    archivoJSON: document.querySelector('[data-json-file]'),
    generarPDF: document.querySelector('[data-generate-pdf]'),
    mostrarDistanciasElementos: document.querySelector('[data-toggle-element-distances]'),
    fechaImpresion: document.querySelector('[data-print-date]'),
    puertaForm: document.querySelector('[data-door-form]'),
    puertaMuro: document.querySelector('[data-door-wall]'),
    puertaDistancia: document.querySelector('[data-door-distance]'),
    puertaAncho: document.querySelector('[data-door-width]'),
    puertaSentido: document.querySelector('[data-door-swing]'),
    puertaLado: document.querySelector('[data-door-side]'),
    ventanaForm: document.querySelector('[data-window-form]'),
    ventanaMuro: document.querySelector('[data-window-wall]'),
    ventanaDistancia: document.querySelector('[data-window-distance]'),
    ventanaAncho: document.querySelector('[data-window-width]'),
    elementosLista: document.querySelector('[data-element-list]'),
    platoBoton: document.querySelector('[data-add-shower]'),
    inodoroBoton: document.querySelector('[data-add-toilet]'),
    bideBoton: document.querySelector('[data-add-bidet]'),
    barraDuchaBoton: document.querySelector('[data-add-shower-bar]'),
    grifoHigienicoBoton: document.querySelector('[data-add-hygienic-faucet]'),
    radiadorToalleroBoton: document.querySelector('[data-add-towel-radiator]'),
    baneraBoton: document.querySelector('[data-add-bathtub]'),
    muebleBoton: document.querySelector('[data-add-vanity]'),
    seleccionPanel: document.querySelector('[data-selection-panel]'),
    muroSeleccionPanel: document.querySelector('[data-wall-selection-panel]'),
    huecoSeleccionPanel: document.querySelector('[data-opening-selection-panel]'),
    panelVacio: document.querySelector('[data-empty-panel]'),
    resumenMuros: document.querySelector('[data-summary-walls]'),
    resumenHuecos: document.querySelector('[data-summary-openings]'),
    resumenInteriores: document.querySelector('[data-summary-interiors]'),
    seleccionadoTipo: document.querySelector('[data-selected-type]'),
    seleccionadoX: document.querySelector('[data-selected-x]'),
    seleccionadoY: document.querySelector('[data-selected-y]'),
    seleccionadoRotacion: document.querySelector('[data-selected-rotation]'),
    seleccionadoAncho: document.querySelector('[data-selected-width]'),
    seleccionadoFondo: document.querySelector('[data-selected-depth]'),
    muroSeleccionNombre: document.querySelector('[data-selected-wall-name]'),
    muroSeleccionDireccionLabel: document.querySelector('[data-selected-wall-direction-label]'),
    muroSeleccionTipo: document.querySelector('[data-selected-wall-kind]'),
    muroSeleccionLongitud: document.querySelector('[data-selected-wall-length]'),
    muroSeleccionDireccion: document.querySelector('[data-selected-wall-direction]'),
    muroSeleccionAuxiliar: document.querySelector('[data-selected-wall-auxiliary]'),
    huecoSeleccionTipo: document.querySelector('[data-selected-opening-type]'),
    huecoSeleccionMuroLabel: document.querySelector('[data-selected-opening-wall-label]'),
    huecoSeleccionMuro: document.querySelector('[data-selected-opening-wall]'),
    huecoSeleccionDistancia: document.querySelector('[data-selected-opening-distance]'),
    huecoSeleccionAncho: document.querySelector('[data-selected-opening-width]'),
    huecoSeleccionCamposPuerta: document.querySelector('[data-selected-opening-door-fields]'),
    huecoSeleccionSentido: document.querySelector('[data-selected-opening-swing]'),
    huecoSeleccionLado: document.querySelector('[data-selected-opening-side]'),
    eliminarHuecoSeleccionado: document.querySelector('[data-delete-opening-selected]'),
    eliminarSeleccionado: document.querySelector('[data-delete-selected]'),
    duplicarSeleccionado: document.querySelector('[data-duplicate-selected]'),
    girar90Seleccionado: document.querySelector('[data-rotate-selected-90]'),
    pegarSeleccionado: document.querySelector('[data-stick-selected]'),
    rotacionBotones: [...document.querySelectorAll('[data-rotate]')],
    snapActivado: document.querySelector('[data-snap-enabled]'),
    referenciaVertical: document.querySelector('[data-vertical-reference]'),
    distanciaVertical: document.querySelector('[data-vertical-distance]'),
    referenciaHorizontal: document.querySelector('[data-horizontal-reference]'),
    distanciaHorizontal: document.querySelector('[data-horizontal-distance]'),
    aplicarPosicion: document.querySelector('[data-apply-position]'),
    pegarVertical: document.querySelector('[data-stick-vertical]'),
    pegarHorizontal: document.querySelector('[data-stick-horizontal]'),
};

iniciarEditor();

function iniciarEditor() {
    configurarToolbar();
    registrarEvento(elementos.form, 'submit', manejarAltaMuro);
    elementos.direcciones.forEach((boton) => {
        boton.addEventListener('click', () => seleccionarDireccion(boton.dataset.direction));
    });
    registrarEvento(elementos.eliminarUltimo, 'click', manejarEliminarUltimo);
    registrarEvento(elementos.vaciar, 'click', manejarVaciar);
    registrarEvento(elementos.deshacer, 'click', manejarDeshacer);
    registrarEvento(elementos.rehacer, 'click', manejarRehacer);
    registrarEvento(elementos.recuperarPlano, 'click', manejarRecuperarPlanoTrabajo);
    registrarEvento(elementos.descartarRecuperacion, 'click', manejarDescartarPlanoTrabajo);
    registrarEvento(elementos.comprobarCierre, 'click', manejarComprobarCierre);
    registrarEvento(elementos.imprimir, 'click', manejarImpresion);
    registrarEvento(elementos.exportarJSON, 'click', manejarExportarJSON);
    registrarEvento(elementos.importarJSON, 'click', () => elementos.archivoJSON.click());
    registrarEvento(elementos.archivoJSON, 'change', manejarImportarJSON);
    registrarEvento(elementos.generarPDF, 'click', manejarGenerarPDF);
    registrarEvento(elementos.mostrarDistanciasElementos, 'click', manejarToggleDistanciasElementos);
    registrarEvento(elementos.puertaForm, 'submit', manejarAltaPuerta);
    registrarEvento(elementos.ventanaForm, 'submit', manejarAltaVentana);
    registrarEvento(elementos.lista, 'click', manejarSeleccionMuroDesdeLista);
    registrarEvento(elementos.elementosLista, 'click', manejarEliminarElemento);
    registrarEvento(elementos.elementosLista, 'click', manejarSeleccionHuecoDesdeLista);
    registrarEvento(elementos.platoBoton, 'click', manejarAltaPlato);
    registrarEvento(elementos.inodoroBoton, 'click', manejarAltaInodoro);
    registrarEvento(elementos.bideBoton, 'click', manejarAltaBide);
    registrarEvento(elementos.barraDuchaBoton, 'click', () => crearInterior('barra_ducha', 13, 7));
    registrarEvento(elementos.grifoHigienicoBoton, 'click', () => crearInterior('grifo_higienico', 30, 7));
    registrarEvento(elementos.radiadorToalleroBoton, 'click', () => crearInterior('radiador_toallero', 50, 10));
    registrarEvento(elementos.baneraBoton, 'click', manejarAltaBanera);
    registrarEvento(elementos.muebleBoton, 'click', manejarAltaMueble);
    registrarEvento(elementos.seleccionadoAncho, 'change', manejarCambioDimensionesSeleccion);
    registrarEvento(elementos.seleccionadoFondo, 'change', manejarCambioDimensionesSeleccion);
    registrarEvento(elementos.muroSeleccionLongitud, 'change', manejarCambioMuroSeleccionado);
    registrarEvento(elementos.muroSeleccionDireccion, 'change', manejarCambioMuroSeleccionado);
    registrarEvento(elementos.muroSeleccionAuxiliar, 'change', manejarCambioMuroSeleccionado);
    registrarEvento(elementos.huecoSeleccionMuro, 'change', manejarCambioHuecoSeleccionado);
    registrarEvento(elementos.huecoSeleccionDistancia, 'change', manejarCambioHuecoSeleccionado);
    registrarEvento(elementos.huecoSeleccionAncho, 'change', manejarCambioHuecoSeleccionado);
    registrarEvento(elementos.huecoSeleccionSentido, 'change', manejarCambioHuecoSeleccionado);
    registrarEvento(elementos.huecoSeleccionLado, 'change', manejarCambioHuecoSeleccionado);
    registrarEvento(elementos.eliminarSeleccionado, 'click', manejarEliminarInteriorSeleccionado);
    registrarEvento(elementos.eliminarHuecoSeleccionado, 'click', manejarEliminarHuecoSeleccionado);
    registrarEvento(elementos.duplicarSeleccionado, 'click', manejarDuplicarInteriorSeleccionado);
    registrarEvento(elementos.girar90Seleccionado, 'click', manejarGirar90Seleccionado);
    registrarEvento(elementos.pegarSeleccionado, 'click', manejarPegarAParedMasCercana);
    elementos.rotacionBotones.forEach((boton) => {
        boton.addEventListener('click', () => manejarRotacionSeleccion(Number(boton.dataset.rotate)));
    });
    registrarEvento(elementos.aplicarPosicion, 'click', manejarAplicarPosicion);
    registrarEvento(elementos.pegarVertical, 'click', () => manejarPegarAPared('vertical'));
    registrarEvento(elementos.pegarHorizontal, 'click', () => manejarPegarAPared('horizontal'));
    registrarEvento(elementos.referenciaVertical, 'change', renderizarPanelSeleccion);
    registrarEvento(elementos.referenciaHorizontal, 'change', renderizarPanelSeleccion);
    configurarDragInteriores(elementos.svg, {
        getElemento: obtenerInteriorPorId,
        onSelect: seleccionarInterior,
        onMove: moverInterior,
        onDragStart: iniciarOperacionDrag,
        onDragEnd: finalizarOperacionDrag,
    });
    editorCotas = configurarEdicionCotas(elementos.svg, {
        confirmar: manejarEdicionDirectaCota,
    });
    elementos.svg.addEventListener('pointerdown', () => cerrarGrupoToolbar());
    configurarAtajosEditor({
        deshacer: manejarDeshacer,
        rehacer: manejarRehacer,
        eliminarSeleccion: manejarEliminarSeleccionAtajo,
        girarSeleccion: manejarGirar90Seleccionado,
        cancelar: manejarCancelarOperacion,
    });
    prepararRecuperacionPlanoTrabajo();

    renderizar();
}

function configurarToolbar() {
    elementos.botonesToolbar.forEach((boton) => {
        boton.addEventListener('click', () => alternarGrupoToolbar(boton.closest('.planos2d-tool-group')));
    });
}

function alternarGrupoToolbar(grupo) {
    if (grupoToolbarAbierto === grupo) {
        cerrarGrupoToolbar();
        return;
    }

    activarGrupoToolbar(grupo);
}

function activarGrupoToolbar(grupoActivo) {
    grupoToolbarAbierto = grupoActivo;
    elementos.gruposToolbar.forEach((grupo) => {
        const activo = grupo === grupoActivo;
        grupo.classList.toggle('is-active', activo);
        grupo.querySelector('[data-toolbar-group]')?.setAttribute('aria-expanded', activo ? 'true' : 'false');
    });
}

function cerrarGrupoToolbar() {
    grupoToolbarAbierto = null;
    elementos.gruposToolbar.forEach((grupo) => {
        grupo.classList.remove('is-active');
        grupo.querySelector('[data-toolbar-group]')?.setAttribute('aria-expanded', 'false');
    });
}

function registrarEvento(elemento, tipo, callback) {
    if (!elemento) {
        return;
    }

    elemento.addEventListener(tipo, callback);
}

function manejarImpresion() {
    elementos.fechaImpresion.textContent = new Date().toLocaleDateString('es-ES');
    window.print();
}

function manejarExportarJSON() {
    const contenido = JSON.stringify(serializarPlano(plano), null, 2);
    const blob = new Blob([contenido], { type: 'application/json' });
    const enlace = document.createElement('a');
    const fecha = new Date().toISOString().slice(0, 10);

    enlace.href = URL.createObjectURL(blob);
    enlace.download = `plano-2d-${fecha}.json`;
    enlace.click();
    setTimeout(() => URL.revokeObjectURL(enlace.href), 0);
    marcarGuardado(historial, serializarPlano(plano));
    descartarPlanoTrabajo();
    elementos.recuperacionPanel.hidden = true;
    limpiarEstado('Plano exportado como JSON.');
    renderizarEstadoHistorial();
}

function manejarImportarJSON(evento) {
    const archivo = evento.target.files?.[0];

    if (!archivo) {
        return;
    }

    archivo.text()
        .then((contenido) => {
            cargarPlanoDesdeJSON(plano, contenido);
            confirmarCambio(historial, serializarPlano(plano));
            guardarPlanoTrabajo(serializarPlano(plano));
            limpiarSeleccion();
            limpiarEstado('Plano importado correctamente.');
            renderizar();
        })
        .catch((error) => {
            mostrarEstado(error.message, 'warning');
        })
        .finally(() => {
            evento.target.value = '';
        });
}

function manejarGenerarPDF() {
    const svg = generarSvgLimpioPDF({ preset: 'plantaGeneral' });
    const svgDimensiones = generarSvgLimpioPDF({ preset: 'dimensionesElementos' });
    const svgDistancias = generarSvgLimpioPDF({ preset: 'replanteo' });

    if (!svg) {
        mostrarEstado('No hay un plano valido para generar PDF.', 'warning');
        return;
    }

    elementos.generarPDF.disabled = true;
    fetch('/planos2d/pdf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plano: serializarPlano(plano),
            svg,
            svgDimensiones,
            svgDistancias,
            orientacion: decidirOrientacionSvg(svg),
            datos: {
                titulo: 'PLANO DE DISTRIBUCIÓN',
                subtitulo: 'Reforma de baño',
                planoParcial: plano.muros.some((muro) => muro.auxiliar === true),
            },
        }),
    })
        .then(async (respuesta) => {
            if (!respuesta.ok) {
                const error = await respuesta.json().catch(() => ({ error: 'No se pudo generar el PDF.' }));
                throw new Error(error.error ?? 'No se pudo generar el PDF.');
            }

            return respuesta.blob();
        })
        .then((blob) => descargarBlob(blob, 'plano-bano.pdf'))
        .then(() => limpiarEstado('PDF generado correctamente.'))
        .catch((error) => mostrarEstado(error.message, 'warning'))
        .finally(() => {
            elementos.generarPDF.disabled = false;
        });
}

function manejarToggleDistanciasElementos() {
    mostrarDistanciasTodosElementos = !mostrarDistanciasTodosElementos;
    elementos.mostrarDistanciasElementos.classList.toggle('is-active', mostrarDistanciasTodosElementos);
    elementos.mostrarDistanciasElementos.setAttribute('aria-pressed', mostrarDistanciasTodosElementos ? 'true' : 'false');
    renderizar();
}

function generarSvgLimpioPDF(opciones = {}) {
    const geometria = calcularGeometria(plano);

    if (geometria.segmentos.length === 0) {
        return null;
    }

    const sistemaElementos = calcularSistemaElementos(plano, geometria);
    const sistemaCotas = calcularCotas(geometria, sistemaElementos);
    const sistemaInteriores = calcularSistemaInteriores(plano);
    const preset = opciones.preset ?? (opciones.incluirDistanciasInteriores ? 'replanteo' : 'plantaGeneral');
    const sistemaDistanciasInteriores = calcularSistemaCapasPdf(preset, sistemaInteriores, geometria);
    const svg = document.createElementNS(SVG_NS, 'svg');

    renderizarPlano(
        svg,
        geometria,
        sistemaCotas,
        sistemaElementos,
        sistemaInteriores,
        null,
        { distancias: [], referencias: { verticales: [], horizontales: [] }, boundingBox: null },
        sistemaDistanciasInteriores,
        {
            modo: 'pdf',
            mostrarDistanciasInteriores: sistemaDistanciasInteriores.distancias.length > 0,
        }
    );
    aplicarAtributosSvgPdf(svg);
    limpiarSvg(svg);
    inyectarEstilosSvg(svg);

    return svg.outerHTML;
}

function calcularSistemaCapasPdf(preset, sistemaInteriores, geometria) {
    if (preset === 'dimensionesElementos') {
        return calcularDimensionesElementosPdf(sistemaInteriores);
    }

    if (preset === 'replanteo') {
        return calcularSistemaDistanciasPdf(sistemaInteriores, geometria);
    }

    return { distancias: [], boundingBox: null };
}

function calcularSistemaDistanciasPdf(sistemaInteriores, geometria) {
    const distanciasParedes = calcularDistanciasParedesPdf(sistemaInteriores, geometria);
    const distanciasEntrePiezas = calcularDistanciasEntreTodosInteriores(sistemaInteriores);

    return combinarDistanciasPdf(
        distanciasParedes,
        {
            ...distanciasEntrePiezas,
            distancias: distanciasEntrePiezas.distancias.map((distancia) => ({
                ...distancia,
                clase: 'is-between-elements',
            })).filter((distancia) => distancia.distancia > 0.001),
        }
    );
}

function aplicarAtributosSvgPdf(svg) {
    svg.querySelectorAll('.plano-wall').forEach((nodo) => aplicarAtributos(nodo, {
        fill: ESTILO_PLANO.muro.color,
        stroke: 'none',
    }));
    svg.querySelectorAll('.plano-wall.is-auxiliary').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: '#9aa3ad',
        'stroke-width': '0.65',
        'stroke-dasharray': '4 3.5',
        'stroke-linecap': 'square',
    }));
    svg.querySelectorAll('.plano-wall-continuation').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: '#9aa3ad',
        'stroke-width': '0.5',
        'stroke-linecap': 'square',
    }));
    svg.querySelectorAll('.plano-dimension-line,.plano-dimension-helper').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: nodo.classList.contains('is-partial') ? ESTILO_PLANO.cotaParcial.linea : ESTILO_PLANO.cotaGeneral.linea,
        'stroke-width': String(nodo.classList.contains('is-partial') ? ESTILO_PLANO.cotaParcial.grosor : ESTILO_PLANO.cotaGeneral.grosor),
        'stroke-linecap': 'square',
    }));
    svg.querySelectorAll('.plano-dimension-label').forEach((nodo) => aplicarAtributos(nodo, {
        fill: nodo.classList.contains('is-partial') ? ESTILO_PLANO.cotaParcial.color : ESTILO_PLANO.cotaGeneral.color,
        stroke: '#ffffff',
        'stroke-width': String(nodo.classList.contains('is-partial') ? ESTILO_PLANO.cotaParcial.halo : ESTILO_PLANO.cotaGeneral.halo),
        'stroke-linejoin': 'round',
        'paint-order': 'stroke',
        'font-family': ESTILO_PLANO.fuente,
        'font-size': String(nodo.classList.contains('is-partial') ? ESTILO_PLANO.cotaParcial.texto : ESTILO_PLANO.cotaGeneral.texto),
        'font-weight': '500',
    }));
    svg.querySelectorAll('.plano-dimension-label-mask').forEach((nodo) => aplicarAtributos(nodo, {
        fill: '#ffffff',
        stroke: 'none',
    }));
    svg.querySelectorAll('.plano-door-leaf').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.puerta.color,
        'stroke-width': String(ESTILO_PLANO.puerta.grosor),
        'stroke-linecap': 'round',
    }));
    svg.querySelectorAll('.plano-door-arc').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.puerta.arco,
        'stroke-width': String(ESTILO_PLANO.puerta.arcoGrosor),
    }));
    svg.querySelectorAll('.plano-window-line').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.ventana.color,
        'stroke-width': String(ESTILO_PLANO.ventana.grosor),
        'stroke-linecap': 'round',
    }));
    svg.querySelectorAll('.plano-interior-shape').forEach((nodo) => aplicarAtributos(nodo, {
        fill: '#ffffff',
        stroke: ESTILO_PLANO.simbolo.color,
        'stroke-width': String(ESTILO_PLANO.simbolo.grosor),
    }));
    svg.querySelectorAll('.plano-interior-outline').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.simbolo.auxiliar,
        'stroke-width': String(ESTILO_PLANO.simbolo.detalle),
    }));
    svg.querySelectorAll('.plano-interior-symbol').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.simbolo.color,
        'stroke-width': String(ESTILO_PLANO.simbolo.grosor),
    }));
    svg.querySelectorAll('.plano-interior-detail').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.simbolo.auxiliar,
        'stroke-width': String(ESTILO_PLANO.simbolo.detalle),
    }));
    svg.querySelectorAll('.plano-dynamic-distance-line.is-element-size').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.cotaElemento.linea,
        'stroke-width': String(ESTILO_PLANO.cotaElemento.grosor),
        'stroke-dasharray': 'none',
    }));
    svg.querySelectorAll('.plano-dynamic-distance-label.is-element-size').forEach((nodo) => aplicarAtributos(nodo, {
        fill: ESTILO_PLANO.cotaElemento.color,
        stroke: '#ffffff',
        'stroke-width': String(ESTILO_PLANO.cotaElemento.halo),
        'paint-order': 'stroke',
        'font-family': ESTILO_PLANO.fuente,
        'font-size': String(ESTILO_PLANO.cotaElemento.texto),
        'font-weight': '500',
    }));
    svg.querySelectorAll('.plano-dynamic-distance-line.is-to-wall').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.cotaParedElemento.linea,
        'stroke-width': String(ESTILO_PLANO.cotaParedElemento.grosor),
        'stroke-dasharray': 'none',
    }));
    svg.querySelectorAll('.plano-dynamic-distance-label.is-to-wall').forEach((nodo) => aplicarAtributos(nodo, {
        fill: ESTILO_PLANO.cotaParedElemento.color,
        stroke: '#ffffff',
        'stroke-width': String(ESTILO_PLANO.cotaParedElemento.halo),
        'paint-order': 'stroke',
        'font-family': ESTILO_PLANO.fuente,
        'font-size': String(ESTILO_PLANO.cotaParedElemento.texto),
        'font-weight': '500',
    }));
    svg.querySelectorAll('.plano-dynamic-distance-line.is-between-elements').forEach((nodo) => aplicarAtributos(nodo, {
        fill: 'none',
        stroke: ESTILO_PLANO.distancia.linea,
        'stroke-width': String(ESTILO_PLANO.distancia.grosor),
        'stroke-dasharray': '1.6 2.6',
    }));
    svg.querySelectorAll('.plano-dynamic-distance-label.is-between-elements').forEach((nodo) => aplicarAtributos(nodo, {
        fill: ESTILO_PLANO.distancia.color,
        stroke: '#ffffff',
        'stroke-width': String(ESTILO_PLANO.distancia.halo),
        'paint-order': 'stroke',
        'font-family': ESTILO_PLANO.fuente,
        'font-size': String(ESTILO_PLANO.distancia.texto),
        'font-weight': '500',
    }));
}

function construirEstilosSvgPdf(estilo) {
    return `
.plano-wall{fill:${estilo.muro.color};stroke:none}
.plano-wall.is-auxiliary{fill:none;stroke:#9aa3ad;stroke-width:.65;stroke-dasharray:4 3.5;stroke-linecap:square}
.plano-wall-continuation{fill:none;stroke:#9aa3ad;stroke-width:.5;stroke-linecap:square}
.plano-dimension-line,.plano-dimension-helper{fill:none;stroke:${estilo.cotaGeneral.linea};stroke-width:${estilo.cotaGeneral.grosor};stroke-linecap:square}
.plano-dimension-line.is-partial,.plano-dimension-helper.is-partial{stroke:${estilo.cotaParcial.linea};stroke-width:${estilo.cotaParcial.grosor}}
.plano-dimension-label{fill:${estilo.cotaGeneral.color};font-family:${estilo.fuente};font-size:${estilo.cotaGeneral.texto}px;font-weight:500;paint-order:stroke;stroke:#fff;stroke-width:${estilo.cotaGeneral.halo}px;stroke-linejoin:round}
.plano-dimension-label.is-partial{fill:${estilo.cotaParcial.color};font-size:${estilo.cotaParcial.texto}px;stroke-width:${estilo.cotaParcial.halo}px}
.plano-dimension-label-mask{fill:#fff;stroke:none}
.plano-door-leaf{fill:none;stroke:${estilo.puerta.color};stroke-width:${estilo.puerta.grosor};stroke-linecap:round}
.plano-door-arc{fill:none;stroke:${estilo.puerta.arco};stroke-width:${estilo.puerta.arcoGrosor}}
.plano-window-line{fill:none;stroke:${estilo.ventana.color};stroke-width:${estilo.ventana.grosor};stroke-linecap:round}
.plano-interior-shape{fill:#fff;stroke:${estilo.simbolo.color};stroke-width:${estilo.simbolo.grosor}}
.plano-interior-outline{fill:none;stroke:${estilo.simbolo.auxiliar};stroke-width:${estilo.simbolo.detalle}}
.plano-interior-symbol{fill:none;stroke:${estilo.simbolo.color};stroke-width:${estilo.simbolo.grosor}}
.plano-interior-detail{fill:none;stroke:${estilo.simbolo.auxiliar};stroke-width:${estilo.simbolo.detalle}}
.plano-dynamic-distance-line.is-element-size{fill:none;stroke:${estilo.cotaElemento.linea};stroke-width:${estilo.cotaElemento.grosor}}
.plano-dynamic-distance-label.is-element-size{fill:${estilo.cotaElemento.color};font-family:${estilo.fuente};font-size:${estilo.cotaElemento.texto}px;font-weight:500;paint-order:stroke;stroke:#fff;stroke-width:${estilo.cotaElemento.halo}px}
.plano-dynamic-distance-line.is-to-wall{fill:none;stroke:${estilo.cotaParedElemento.linea};stroke-width:${estilo.cotaParedElemento.grosor}}
.plano-dynamic-distance-label.is-to-wall{fill:${estilo.cotaParedElemento.color};font-family:${estilo.fuente};font-size:${estilo.cotaParedElemento.texto}px;font-weight:500;paint-order:stroke;stroke:#fff;stroke-width:${estilo.cotaParedElemento.halo}px}
.plano-dynamic-distance-line.is-between-elements{fill:none;stroke:${estilo.distancia.linea};stroke-width:${estilo.distancia.grosor};stroke-dasharray:1.6 2.6}
.plano-dynamic-distance-label.is-between-elements{fill:${estilo.distancia.color};font-family:${estilo.fuente};font-size:${estilo.distancia.texto}px;font-weight:500;paint-order:stroke;stroke:#fff;stroke-width:${estilo.distancia.halo}px}
`;
}

function aplicarAtributos(nodo, atributos) {
    Object.entries(atributos).forEach(([nombre, valor]) => {
        nodo.setAttribute(nombre, valor);
    });
}

function limpiarSvg(svg) {
    svg.querySelectorAll('.plano-dimension-hitbox').forEach((nodo) => nodo.remove());
    svg.querySelectorAll('*').forEach((nodo) => {
        [...nodo.attributes].forEach((atributo) => {
            if (atributo.name.startsWith('data-') || atributo.name.startsWith('on') || atributo.name === 'title') {
                nodo.removeAttribute(atributo.name);
            }
        });
        nodo.classList.remove('is-selected');
    });
}

function inyectarEstilosSvg(svg) {
    const defs = document.createElementNS(SVG_NS, 'defs');
    const style = document.createElementNS(SVG_NS, 'style');
    style.setAttribute('type', 'text/css');
    style.textContent = ESTILOS_SVG_PDF;
    defs.appendChild(style);
    svg.insertBefore(defs, svg.firstChild);
}

function decidirOrientacionSvg(svgMarkup) {
    const viewBox = svgMarkup.match(/viewBox="([^"]+)"/)?.[1]?.split(/\s+/).map(Number);

    if (!viewBox || viewBox.length !== 4 || viewBox.some((valor) => !Number.isFinite(valor))) {
        return 'landscape';
    }

    const [, , ancho, alto] = viewBox;
    return alto > ancho * 1.12 ? 'portrait' : 'landscape';
}

function descargarBlob(blob, nombreArchivo) {
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(blob);
    enlace.download = nombreArchivo;
    enlace.click();
    setTimeout(() => URL.revokeObjectURL(enlace.href), 0);
}

function aplicarCambio(mutacion, mensajeOk = '') {
    const estadoAnterior = serializarPlano(plano);

    try {
        const resultado = mutacion();
        const estadoNuevo = serializarPlano(plano);
        const cambiado = confirmarCambio(historial, estadoNuevo);

        if (cambiado) {
            guardarPlanoTrabajo(estadoNuevo);
        }

        if (mensajeOk) {
            limpiarEstado(mensajeOk);
        }

        renderizar();
        return resultado;
    } catch (error) {
        cargarPlanoDesdeJSON(plano, estadoAnterior);
        mostrarEstado(error.message, 'warning');
        renderizar();
        return null;
    }
}

function restaurarEstadoPlano(estado, mensaje) {
    if (!estado) {
        return;
    }

    cargarPlanoDesdeJSON(plano, estado);
    limpiarSeleccion();
    guardarPlanoTrabajo(serializarPlano(plano));
    limpiarEstado(mensaje);
    renderizar();
}

function manejarAltaMuro(evento) {
    evento.preventDefault();

    aplicarCambio(() => {
        agregarMuro(plano, elementos.longitud.value, direccionSeleccionada, elementos.muroAuxiliar.checked);
        elementos.longitud.select();
    }, `Muro añadido: ${elementos.longitud.value} cm ${DIRECCIONES[direccionSeleccionada].simbolo}${elementos.muroAuxiliar.checked ? ' (auxiliar)' : ''}`);
}

function manejarAltaPuerta(evento) {
    evento.preventDefault();

    aplicarCambio(() => {
        agregarPuerta(plano, {
            muroId: elementos.puertaMuro.value,
            distanciaDesdeInicio: elementos.puertaDistancia.value,
            ancho: elementos.puertaAncho.value,
            sentidoApertura: elementos.puertaSentido.value,
            ladoApertura: elementos.puertaLado.value,
        });
    }, 'Puerta añadida.');
}

function manejarAltaVentana(evento) {
    evento.preventDefault();

    aplicarCambio(() => {
        agregarVentana(plano, {
            muroId: elementos.ventanaMuro.value,
            distanciaDesdeInicio: elementos.ventanaDistancia.value,
            ancho: elementos.ventanaAncho.value,
        });
    }, 'Ventana añadida.');
}

function manejarEliminarElemento(evento) {
    const boton = evento.target.closest('[data-remove-element]');

    if (!boton) {
        return;
    }

    if (huecoSeleccionadoId === boton.dataset.removeElement) {
        limpiarSeleccion();
    }

    aplicarCambio(() => eliminarElemento(plano, boton.dataset.removeElement), 'Elemento eliminado.');
}

function manejarAltaPlato() {
    crearInterior('plato_ducha', 120, 80);
}

function manejarAltaInodoro() {
    crearInterior('inodoro', 38, 65);
}

function manejarAltaBide() {
    crearInterior('bide', 38, 58);
}

function manejarAltaBanera() {
    crearInterior('banera', 170, 70);
}

function manejarAltaMueble() {
    crearInterior('mueble_lavabo', 80, 45);
}

function crearInterior(tipo, ancho, fondo) {
    aplicarCambio(() => {
        const geometria = calcularGeometria(plano);
        const posicion = posicionInicialInterior(geometria);
        const elemento = agregarElementoInterior(plano, {
            tipo,
            x: posicion.x,
            y: posicion.y,
            ancho,
            fondo,
            rotacion: 0,
        });
        seleccionarInterior(elemento.id, false);
    }, 'Elemento interior añadido.');
}

function seleccionarInterior(elementoId, renderizarDespues = true) {
    elementoInteriorSeleccionadoId = elementoId;
    muroSeleccionadoId = null;
    huecoSeleccionadoId = null;

    if (renderizarDespues) {
        renderizar();
    }
}

function moverInterior(elementoId, posicion) {
    const geometria = calcularGeometria(plano);
    const elemento = obtenerInteriorPorId(elementoId);
    const posicionBase = elemento?.muroId && esElementoMural(elemento)
        ? ajustarMuralAMuro(elemento, geometria, elemento.muroId, posicion) ?? posicion
        : posicion;
    const posicionFinal = elementos.snapActivado.checked && elemento && !elemento.muroId
        ? aplicarSnap(elemento, posicion, geometria)
        : posicionBase;

    moverElementoInterior(plano, elementoId, posicionFinal);
    if (posicionFinal.rotacion !== undefined || posicionFinal.muroId !== undefined) {
        actualizarElementoInterior(plano, elementoId, {
            rotacion: posicionFinal.rotacion,
            muroId: posicionFinal.muroId,
        });
    }
    seleccionarInterior(elementoId, false);
    renderizar();
}

function manejarSeleccionMuroDesdeLista(evento) {
    const boton = evento.target.closest('[data-select-wall]');

    if (!boton) {
        return;
    }

    elementoInteriorSeleccionadoId = null;
    huecoSeleccionadoId = null;
    muroSeleccionadoId = boton.dataset.selectWall;
    renderizar();
}

function manejarAplicarPosicion() {
    aplicarPosicionDesdePanel(false);
}

function manejarPegarAPared(tipo) {
    aplicarPosicionDesdePanel(true, tipo);
}

function aplicarPosicionDesdePanel(pegar, tipoUnico = null) {
    const elemento = obtenerInteriorPorId(elementoInteriorSeleccionadoId);

    if (!elemento) {
        return;
    }

    const geometria = calcularGeometria(plano);
    const sistema = calcularDistanciasElemento(elemento, geometria);
    let posicion = { x: elemento.x, y: elemento.y };

    const ajustes = [];

    if (!tipoUnico || tipoUnico === 'vertical') {
        ajustes.push({
            referencia: buscarReferencia(sistema.referencias.verticales, elementos.referenciaVertical.value),
            distancia: pegar ? 0 : parsearDecimal(elementos.distanciaVertical.value),
        });
    }

    if (!tipoUnico || tipoUnico === 'horizontal') {
        ajustes.push({
            referencia: buscarReferencia(sistema.referencias.horizontales, elementos.referenciaHorizontal.value),
            distancia: pegar ? 0 : parsearDecimal(elementos.distanciaHorizontal.value),
        });
    }

    for (const ajuste of ajustes) {
        if (!ajuste.referencia || !Number.isFinite(ajuste.distancia)) {
            continue;
        }

        const candidato = calcularPosicionAReferencia({ ...elemento, ...posicion }, ajuste.referencia, ajuste.distancia, geometria);

        if (!candidato) {
            mostrarEstado('No es posible colocar el elemento a esa distancia.', 'warning');
            return;
        }

        posicion = ajustarMuralAReferencia({ ...elemento, ...candidato }, ajuste.referencia, geometria) ?? candidato;
    }

    aplicarCambio(() => {
        moverElementoInterior(plano, elemento.id, posicion);
        actualizarElementoInterior(plano, elemento.id, {
            rotacion: posicion.rotacion,
            muroId: posicion.muroId,
        });
    });
}

function manejarCambioDimensionesSeleccion() {
    if (!elementoInteriorSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        actualizarElementoInterior(plano, elementoInteriorSeleccionadoId, {
            ancho: elementos.seleccionadoAncho.value,
            fondo: elementos.seleccionadoFondo.value,
        });
    });
}

function manejarCambioMuroSeleccionado() {
    if (!muroSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        actualizarMuro(plano, muroSeleccionadoId, {
            longitud: elementos.muroSeleccionLongitud.value,
            direccion: elementos.muroSeleccionDireccion.value,
            auxiliar: elementos.muroSeleccionAuxiliar.checked,
        });
    });
}

function manejarSeleccionHuecoDesdeLista(evento) {
    const boton = evento.target.closest('[data-select-opening]');

    if (!boton) {
        return;
    }

    elementoInteriorSeleccionadoId = null;
    muroSeleccionadoId = null;
    huecoSeleccionadoId = boton.dataset.selectOpening;
    renderizar();
}

function manejarCambioHuecoSeleccionado() {
    if (!huecoSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        actualizarElemento(plano, huecoSeleccionadoId, {
            muroId: elementos.huecoSeleccionMuro.value,
            distanciaDesdeInicio: elementos.huecoSeleccionDistancia.value,
            ancho: elementos.huecoSeleccionAncho.value,
            sentidoApertura: elementos.huecoSeleccionSentido.value,
            ladoApertura: elementos.huecoSeleccionLado.value,
        });
    });
}

function manejarRotacionSeleccion(rotacion) {
    if (!elementoInteriorSeleccionadoId) {
        return;
    }

    const elemento = obtenerInteriorPorId(elementoInteriorSeleccionadoId);

    if (elemento?.muroId && esElementoMural(elemento)) {
        mostrarEstado('La orientación de este elemento mural depende de la pared.', 'warning');
        return;
    }

    aplicarCambio(() => actualizarElementoInterior(plano, elementoInteriorSeleccionadoId, { rotacion }));
}

function manejarGirar90Seleccionado() {
    const elemento = obtenerInteriorPorId(elementoInteriorSeleccionadoId);

    if (!elemento) {
        return;
    }

    if (elemento.muroId && esElementoMural(elemento)) {
        mostrarEstado('La orientación de este elemento mural depende de la pared.', 'warning');
        return;
    }

    aplicarCambio(() => actualizarElementoInterior(plano, elemento.id, { rotacion: elemento.rotacion + 90 }));
}

function manejarDuplicarInteriorSeleccionado() {
    if (!elementoInteriorSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        const duplicado = duplicarElementoInterior(plano, elementoInteriorSeleccionadoId);
        seleccionarInterior(duplicado.id, false);
    }, 'Elemento interior duplicado.');
}

function manejarPegarAParedMasCercana() {
    const elemento = obtenerInteriorPorId(elementoInteriorSeleccionadoId);

    if (!elemento) {
        return;
    }

    const geometria = calcularGeometria(plano);
    const sistema = calcularDistanciasElemento(elemento, geometria);
    const referencia = [
        ...sistema.referencias.verticales,
        ...sistema.referencias.horizontales,
    ].sort((a, b) => a.distancia - b.distancia)[0];

    if (!referencia) {
        return;
    }

    const posicionBase = calcularPosicionAReferencia(elemento, referencia, 0, geometria);

    if (!posicionBase) {
        mostrarEstado('No es posible pegar el elemento a esa pared.', 'warning');
        return;
    }

    const posicion = ajustarMuralAReferencia({ ...elemento, ...posicionBase }, referencia, geometria) ?? posicionBase;

    aplicarCambio(() => {
        moverElementoInterior(plano, elemento.id, posicion);
        actualizarElementoInterior(plano, elemento.id, {
            rotacion: posicion.rotacion,
            muroId: posicion.muroId,
        });
    });
}

function manejarEliminarInteriorSeleccionado() {
    if (!elementoInteriorSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        eliminarElementoInterior(plano, elementoInteriorSeleccionadoId);
        limpiarSeleccion();
    }, 'Elemento interior eliminado.');
}

function manejarEliminarHuecoSeleccionado() {
    if (!huecoSeleccionadoId) {
        return;
    }

    aplicarCambio(() => {
        eliminarElemento(plano, huecoSeleccionadoId);
        limpiarSeleccion();
    }, 'Hueco eliminado.');
}

function seleccionarDireccion(direccion) {
    direccionSeleccionada = direccion;

    elementos.direcciones.forEach((boton) => {
        const activo = boton.dataset.direction === direccion;
        boton.classList.toggle('is-active', activo);
        boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
    });
}

function manejarEliminarUltimo() {
    if (plano.muros.length === 0) {
        mostrarEstado('No hay muros para eliminar.', 'warning');
        return;
    }

    aplicarCambio(() => eliminarUltimoMuro(plano), 'Ultimo muro eliminado.');
}

function manejarVaciar() {
    aplicarCambio(() => {
        limpiarPlano(plano);
        limpiarSeleccion();
    }, 'Plano vaciado.');
}

function limpiarSeleccion() {
    elementoInteriorSeleccionadoId = null;
    muroSeleccionadoId = null;
    huecoSeleccionadoId = null;
}

function manejarComprobarCierre() {
    const geometria = calcularGeometria(plano);
    mostrarEstado(describirCierre(geometria.cierre), geometria.cierre.cerrado ? 'ok' : 'warning');
}

function renderizar() {
    const geometria = calcularGeometria(plano);
    const sistemaElementos = calcularSistemaElementos(plano, geometria);
    const sistemaCotas = calcularCotas(geometria, sistemaElementos);
    const sistemaInteriores = calcularSistemaInteriores(plano);
    const seleccionado = obtenerInteriorPorId(elementoInteriorSeleccionadoId);
    const sistemaDistancias = calcularDistanciasElemento(seleccionado, geometria);
    const sistemaDistanciasInteriores = mostrarDistanciasTodosElementos
        ? calcularDistanciasEntreTodosInteriores(sistemaInteriores)
        : calcularDistanciasEntreInteriores(seleccionado, sistemaInteriores);
    renderizarPlano(
        elementos.svg,
        geometria,
        sistemaCotas,
        sistemaElementos,
        sistemaInteriores,
        elementoInteriorSeleccionadoId,
        sistemaDistancias,
        sistemaDistanciasInteriores,
        {
            mostrarDistanciasInteriores: mostrarDistanciasTodosElementos || Boolean(seleccionado),
            interioresInvalidos: calcularInterioresInvalidos(geometria),
        }
    );
    renderizarListaMuros();
    renderizarSelectoresMuros();
    renderizarListaElementos();
    renderizarResumenPlano();
    renderizarPanelSeleccion();
    renderizarEstadoHistorial();
}

function renderizarResumenPlano() {
    elementos.resumenMuros.textContent = String(plano.muros.length);
    elementos.resumenHuecos.textContent = String(plano.elementos.length);
    elementos.resumenInteriores.textContent = String(plano.interiores.length);
}

function renderizarListaMuros() {
    elementos.lista.replaceChildren();

    obtenerMuros(plano).forEach((muro, index) => {
        const item = document.createElement('li');
        item.innerHTML = `
            <span>${etiquetaMuro(muro, index)}</span>
            <button type="button" data-select-wall="${muro.id}">Editar</button>
        `;
        elementos.lista.appendChild(item);
    });
}

function renderizarSelectoresMuros() {
    const opciones = obtenerMuros(plano).map((muro, index) => ({
        value: muro.id,
        label: etiquetaMuro(muro, index),
    }));

    actualizarSelectorMuros(elementos.puertaMuro, opciones);
    actualizarSelectorMuros(elementos.ventanaMuro, opciones);
    actualizarSelectorMuros(elementos.huecoSeleccionMuro, opciones);
}

function actualizarSelectorMuros(selector, opciones) {
    const valorActual = selector.value;
    selector.replaceChildren();

    opciones.forEach((opcion) => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.textContent = opcion.label;
        selector.appendChild(option);
    });

    if (opciones.some((opcion) => opcion.value === valorActual)) {
        selector.value = valorActual;
    }

    selector.disabled = opciones.length === 0;
}

function renderizarListaElementos() {
    const muros = obtenerMuros(plano);
    elementos.elementosLista.replaceChildren();

    obtenerElementos(plano).forEach((elementoPlano) => {
        const item = document.createElement('li');
        const muroIndex = muros.findIndex((muro) => muro.id === elementoPlano.muroId);
        const nombre = elementoPlano.tipo === 'puerta' ? 'Puerta' : 'Ventana';
        item.innerHTML = `
            <span>${nombre} - muro ${muroIndex + 1} - ${elementoPlano.ancho} cm</span>
            <span>
                <button type="button" data-select-opening="${elementoPlano.id}">Editar</button>
                <button type="button" data-remove-element="${elementoPlano.id}">Eliminar</button>
            </span>
        `;
        elementos.elementosLista.appendChild(item);
    });
}

function renderizarPanelSeleccion() {
    const seleccionado = obtenerInteriorPorId(elementoInteriorSeleccionadoId);
    const muro = obtenerMuros(plano).find((candidato) => candidato.id === muroSeleccionadoId) ?? null;
    const hueco = obtenerElementos(plano).find((candidato) => candidato.id === huecoSeleccionadoId) ?? null;

    elementos.seleccionPanel.hidden = !seleccionado;
    elementos.muroSeleccionPanel.hidden = !muro;
    elementos.huecoSeleccionPanel.hidden = !hueco;
    elementos.panelVacio.hidden = Boolean(seleccionado || muro || hueco);

    if (muro) {
        renderizarPanelMuro(muro);
        return;
    }

    if (hueco) {
        renderizarPanelHueco(hueco);
        return;
    }

    if (!seleccionado) {
        return;
    }

    elementos.seleccionadoTipo.textContent = nombreInterior(seleccionado.tipo);
    elementos.seleccionadoX.textContent = `${redondear(seleccionado.x)} cm`;
    elementos.seleccionadoY.textContent = `${redondear(seleccionado.y)} cm`;
    elementos.seleccionadoRotacion.textContent = `${seleccionado.rotacion}°`;
    elementos.seleccionadoAncho.value = seleccionado.ancho;
    elementos.seleccionadoFondo.value = seleccionado.fondo;
    renderizarPanelPosicionPrecisa(seleccionado);
}

function renderizarPanelMuro(muro) {
    const muros = obtenerMuros(plano);
    const index = muros.findIndex((candidato) => candidato.id === muro.id);

    elementos.muroSeleccionNombre.textContent = `Muro ${index + 1}`;
    elementos.muroSeleccionDireccionLabel.textContent = DIRECCIONES[muro.direccion].simbolo;
    elementos.muroSeleccionTipo.textContent = muro.auxiliar ? 'Auxiliar / no medido' : 'Medido';
    elementos.muroSeleccionLongitud.value = muro.longitud;
    elementos.muroSeleccionDireccion.value = muro.direccion;
    elementos.muroSeleccionAuxiliar.checked = muro.auxiliar === true;
}

function etiquetaMuro(muro, index) {
    return muro.auxiliar
        ? `Muro ${index + 1} - auxiliar ${DIRECCIONES[muro.direccion].simbolo}`
        : `Muro ${index + 1} - ${muro.longitud} cm ${DIRECCIONES[muro.direccion].simbolo}`;
}

function renderizarPanelHueco(hueco) {
    const muros = obtenerMuros(plano);
    const muroIndex = muros.findIndex((muro) => muro.id === hueco.muroId);

    elementos.huecoSeleccionTipo.textContent = hueco.tipo === 'puerta' ? 'Puerta' : 'Ventana';
    elementos.huecoSeleccionMuroLabel.textContent = `Muro ${muroIndex + 1}`;
    elementos.huecoSeleccionMuro.value = hueco.muroId;
    elementos.huecoSeleccionDistancia.value = hueco.distanciaDesdeInicio;
    elementos.huecoSeleccionAncho.value = hueco.ancho;
    elementos.huecoSeleccionCamposPuerta.hidden = hueco.tipo !== 'puerta';

    if (hueco.tipo === 'puerta') {
        elementos.huecoSeleccionSentido.value = hueco.sentidoApertura;
        elementos.huecoSeleccionLado.value = hueco.ladoApertura;
    }
}

function renderizarPanelPosicionPrecisa(seleccionado) {
    const geometria = calcularGeometria(plano);
    const sistema = calcularDistanciasElemento(seleccionado, geometria);

    actualizarSelectorReferencias(elementos.referenciaVertical, sistema.referencias.verticales);
    actualizarSelectorReferencias(elementos.referenciaHorizontal, sistema.referencias.horizontales);
    actualizarInputDistancia(elementos.distanciaVertical, buscarReferencia(sistema.referencias.verticales, elementos.referenciaVertical.value));
    actualizarInputDistancia(elementos.distanciaHorizontal, buscarReferencia(sistema.referencias.horizontales, elementos.referenciaHorizontal.value));
}

function actualizarSelectorReferencias(selector, referencias) {
    const valorActual = selector.value;
    selector.replaceChildren();

    referencias.forEach((referencia) => {
        const option = document.createElement('option');
        option.value = referencia.id;
        option.textContent = `Muro ${referencia.muroIndex + 1} - ${referencia.lado}`;
        selector.appendChild(option);
    });

    if (referencias.some((referencia) => referencia.id === valorActual)) {
        selector.value = valorActual;
    }

    selector.disabled = referencias.length === 0;
}

function actualizarInputDistancia(input, referencia) {
    input.value = referencia ? formatearDecimal(referencia.distancia) : '';
    input.disabled = !referencia;
}

function buscarReferencia(referencias, id) {
    return referencias.find((referencia) => referencia.id === id) ?? referencias[0] ?? null;
}

function parsearDecimal(valor) {
    return Number(String(valor).replace(',', '.'));
}

function formatearDecimal(valor) {
    const redondeado = Math.round(valor * 10) / 10;
    return Number.isInteger(redondeado) ? String(redondeado) : String(redondeado).replace('.', ',');
}

function obtenerInteriorPorId(elementoId) {
    return obtenerElementosInteriores(plano).find((elemento) => elemento.id === elementoId) ?? null;
}

function nombreInterior(tipo) {
    const nombres = {
        plato_ducha: 'Plato de ducha',
        inodoro: 'Inodoro',
        bide: 'Bide',
        banera: 'Bañera',
        mueble_lavabo: 'Mueble/lavabo',
        barra_ducha: 'Barra de ducha',
        grifo_higienico: 'Grifo higiénico',
        radiador_toallero: 'Radiador toallero',
    };

    return nombres[tipo] ?? tipo;
}

function redondear(valor) {
    return Math.round(valor * 10) / 10;
}

function limpiarEstado(mensaje) {
    mostrarEstado(mensaje);
}

function mostrarEstado(mensaje, tipo = '') {
    elementos.estado.textContent = mensaje;
    elementos.estado.classList.toggle('is-ok', tipo === 'ok');
    elementos.estado.classList.toggle('is-warning', tipo === 'warning');
}

function manejarDeshacer() {
    const estado = deshacer(historial);
    restaurarEstadoPlano(estado, 'Cambio deshecho.');
}

function manejarRehacer() {
    const estado = rehacer(historial);
    restaurarEstadoPlano(estado, 'Cambio rehecho.');
}

function iniciarOperacionDrag() {
    estadoAntesDrag = serializarPlano(plano);
}

function finalizarOperacionDrag() {
    if (!estadoAntesDrag) {
        return;
    }

    const estadoFinal = serializarPlano(plano);
    const cambiado = JSON.stringify(estadoAntesDrag) !== JSON.stringify(estadoFinal);

    if (cambiado) {
        historial.presente = clonarEstado(estadoAntesDrag);
        confirmarCambio(historial, estadoFinal);
        guardarPlanoTrabajo(estadoFinal);
        renderizarEstadoHistorial();
    }

    estadoAntesDrag = null;
}

function prepararRecuperacionPlanoTrabajo() {
    const trabajo = obtenerPlanoTrabajo();

    if (!trabajo || JSON.stringify(trabajo.plano) === JSON.stringify(serializarPlano(plano))) {
        return;
    }

    elementos.recuperacionPanel.hidden = false;
}

function manejarRecuperarPlanoTrabajo() {
    const trabajo = obtenerPlanoTrabajo();

    if (!trabajo) {
        elementos.recuperacionPanel.hidden = true;
        return;
    }

    try {
        cargarPlanoDesdeJSON(plano, trabajo.plano);
        confirmarCambio(historial, serializarPlano(plano));
        limpiarSeleccion();
        elementos.recuperacionPanel.hidden = true;
        limpiarEstado('Plano de trabajo recuperado.');
        renderizar();
    } catch (error) {
        descartarPlanoTrabajo();
        elementos.recuperacionPanel.hidden = true;
        mostrarEstado(error.message, 'warning');
    }
}

function manejarDescartarPlanoTrabajo() {
    descartarPlanoTrabajo();
    elementos.recuperacionPanel.hidden = true;
    limpiarEstado('Plano de trabajo descartado.');
}

function manejarEliminarSeleccionAtajo() {
    if (elementoInteriorSeleccionadoId) {
        manejarEliminarInteriorSeleccionado();
        return;
    }

    if (huecoSeleccionadoId) {
        manejarEliminarHuecoSeleccionado();
    }
}

function manejarCancelarOperacion() {
    if (editorCotas?.cancelar()) {
        return;
    }

    if (herramientaActiva) {
        herramientaActiva = null;
        return;
    }

    cerrarGrupoToolbar();
}

function manejarEdicionDirectaCota(payload, valor) {
    if (!payload) {
        return;
    }

    const numero = parsearDecimal(valor);

    if (!Number.isFinite(numero)) {
        mostrarEstado('Introduce una medida valida.', 'warning');
        return;
    }

    aplicarCambio(() => aplicarEdicionCota(payload, numero), 'Cota actualizada.');
}

function aplicarEdicionCota(payload, valor) {
    if (payload.tipo === 'muro-longitud') {
        actualizarMuro(plano, payload.muroId, { longitud: valor });
        return;
    }

    if (payload.tipo === 'hueco-distancia-inicio') {
        actualizarElemento(plano, payload.elementoId, { distanciaDesdeInicio: valor });
        return;
    }

    if (payload.tipo === 'hueco-ancho') {
        actualizarElemento(plano, payload.elementoId, { ancho: valor });
        return;
    }

    if (payload.tipo === 'hueco-distancia-final') {
        const hueco = obtenerElementos(plano).find((elemento) => elemento.id === payload.elementoId);
        const muro = obtenerMuros(plano).find((candidato) => candidato.id === payload.muroId);

        if (!hueco || !muro) {
            throw new Error('No se ha encontrado el hueco seleccionado.');
        }

        actualizarElemento(plano, payload.elementoId, {
            distanciaDesdeInicio: muro.longitud - hueco.ancho - valor,
        });
        return;
    }

    if (payload.tipo === 'distancia-pared') {
        aplicarEdicionDistanciaPared(payload, valor);
    }
}

function aplicarEdicionDistanciaPared(payload, valor) {
    if (valor < 0) {
        throw new Error('La distancia debe ser mayor o igual que 0 cm.');
    }

    const elemento = obtenerInteriorPorId(elementoInteriorSeleccionadoId);

    if (!elemento) {
        throw new Error('Selecciona un elemento interior para editar su distancia.');
    }

    const geometria = calcularGeometria(plano);
    const sistema = calcularDistanciasElemento(elemento, geometria);
    const referencia = buscarReferencia([
        ...sistema.referencias.verticales,
        ...sistema.referencias.horizontales,
    ], payload.referenciaId);
    const posicion = calcularPosicionAReferencia(elemento, referencia, valor, geometria);

    if (!posicion) {
        throw new Error('No es posible colocar el elemento a esa distancia.');
    }

    moverElementoInterior(plano, elemento.id, posicion);
}

function calcularPosicionAReferencia(elemento, referencia, distancia, geometria) {
    return moverAReferencia(elemento, referencia, distancia, geometria);
}

function renderizarEstadoHistorial() {
    elementos.deshacer.disabled = !puedeDeshacer(historial);
    elementos.rehacer.disabled = !puedeRehacer(historial);
    elementos.modificado.hidden = !planoModificado(historial);
}

function calcularInterioresInvalidos(geometria) {
    const invalidos = new Set();

    if (!geometria.cierre.cerrado || geometria.puntos.length < 4) {
        return invalidos;
    }

    obtenerElementosInteriores(plano).forEach((elemento) => {
        const esquinas = esquinasInterior(elemento);

        if (!esquinas.every((punto) => puntoDentroPoligono(punto, geometria.puntos))) {
            invalidos.add(elemento.id);
        }
    });

    return invalidos;
}

function esquinasInterior(elemento) {
    const medioAncho = elemento.ancho / 2;
    const medioFondo = elemento.fondo / 2;
    const angulo = elemento.rotacion * Math.PI / 180;
    const cos = Math.cos(angulo);
    const sin = Math.sin(angulo);

    return [
        { x: -medioAncho, y: -medioFondo },
        { x: medioAncho, y: -medioFondo },
        { x: medioAncho, y: medioFondo },
        { x: -medioAncho, y: medioFondo },
    ].map((punto) => ({
        x: elemento.x + punto.x * cos - punto.y * sin,
        y: elemento.y + punto.x * sin + punto.y * cos,
    }));
}

function puntoDentroPoligono(punto, puntosPoligono) {
    let dentro = false;
    const puntos = puntosPoligono.slice(0, -1);

    for (let i = 0, j = puntos.length - 1; i < puntos.length; j = i, i += 1) {
        const a = puntos[i];
        const b = puntos[j];
        const cruza = (a.y > punto.y) !== (b.y > punto.y)
            && punto.x < ((b.x - a.x) * (punto.y - a.y)) / ((b.y - a.y) || 1) + a.x;

        if (cruza) {
            dentro = !dentro;
        }
    }

    return dentro || puntoSobreBorde(punto, puntos);
}

function puntoSobreBorde(punto, puntos) {
    const tolerancia = 0.001;

    return puntos.some((inicio, index) => {
        const fin = puntos[(index + 1) % puntos.length];
        const area = Math.abs((fin.x - inicio.x) * (punto.y - inicio.y) - (fin.y - inicio.y) * (punto.x - inicio.x));
        const dentroX = punto.x >= Math.min(inicio.x, fin.x) - tolerancia && punto.x <= Math.max(inicio.x, fin.x) + tolerancia;
        const dentroY = punto.y >= Math.min(inicio.y, fin.y) - tolerancia && punto.y <= Math.max(inicio.y, fin.y) + tolerancia;

        return area <= tolerancia && dentroX && dentroY;
    });
}
