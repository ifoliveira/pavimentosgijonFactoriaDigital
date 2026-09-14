const VERSION_PLANO = 1;
const DIRECCIONES_VALIDAS = ['arriba', 'derecha', 'abajo', 'izquierda'];
const TIPOS_INTERIORES_VALIDOS = ['plato_ducha', 'inodoro', 'bide', 'banera', 'mueble_lavabo', 'barra_ducha', 'grifo_higienico', 'radiador_toallero'];
const SENTIDOS_APERTURA_VALIDOS = ['izquierda', 'derecha'];
const LADOS_APERTURA_VALIDOS = ['interior', 'exterior'];

export function crearPlano() {
    return {
        version: VERSION_PLANO,
        muros: [],
        elementos: [],
        interiores: [],
    };
}

export function agregarMuro(plano, longitud, direccion, auxiliar = false) {
    const longitudNumerica = Number(longitud);

    if (!Number.isFinite(longitudNumerica) || longitudNumerica <= 0) {
        throw new Error('La longitud debe ser mayor que 0 cm.');
    }

    plano.muros.push({
        id: siguienteId(plano.muros, 'muro'),
        longitud: longitudNumerica,
        direccion,
        auxiliar: auxiliar === true,
    });
}

export function eliminarUltimoMuro(plano) {
    const muro = plano.muros.pop();

    if (muro) {
        plano.elementos = plano.elementos.filter((elemento) => elemento.muroId !== muro.id);
        plano.interiores = plano.interiores.map((elemento) => (
            elemento.muroId === muro.id ? sinMuroAsociado(elemento) : elemento
        ));
    }
}

export function limpiarPlano(plano) {
    plano.muros = [];
    plano.elementos = [];
    plano.interiores = [];
}

export function obtenerMuros(plano) {
    return plano.muros.map((muro) => ({ ...muro }));
}

export function actualizarMuro(plano, muroId, cambios) {
    const muro = buscarMuro(plano, muroId);
    const original = { ...muro };

    try {
        if (cambios.longitud !== undefined) {
            const longitud = Number(cambios.longitud);
            if (!Number.isFinite(longitud) || longitud <= 0) {
                throw new Error('La longitud debe ser mayor que 0 cm.');
            }
            muro.longitud = longitud;
        }

        if (cambios.direccion !== undefined) {
            if (!DIRECCIONES_VALIDAS.includes(cambios.direccion)) {
                throw new Error('Selecciona una direccion valida.');
            }
            muro.direccion = cambios.direccion;
        }

        if (cambios.auxiliar !== undefined) {
            muro.auxiliar = cambios.auxiliar === true;
        }

        plano.elementos
            .filter((elemento) => elemento.muroId === muro.id)
            .forEach((elemento) => {
                const nombre = elemento.tipo === 'puerta' ? 'La puerta' : 'La ventana';
                validarElementoEnMuro(
                    muro,
                    elemento.distanciaDesdeInicio,
                    elemento.ancho,
                    `${nombre} ya no cabe en el muro después de modificar su longitud.`
                );
            });
    } catch (error) {
        muro.longitud = original.longitud;
        muro.direccion = original.direccion;
        muro.auxiliar = original.auxiliar;
        throw error;
    }
}

export function agregarPuerta(plano, datos) {
    const muro = buscarMuro(plano, datos.muroId);
    validarElementoEnMuro(muro, datos.distanciaDesdeInicio, datos.ancho, 'La puerta no cabe dentro del muro seleccionado.');

    plano.elementos.push({
        id: siguienteId(plano.elementos, 'puerta'),
        tipo: 'puerta',
        muroId: datos.muroId,
        distanciaDesdeInicio: Number(datos.distanciaDesdeInicio),
        ancho: Number(datos.ancho),
        sentidoApertura: datos.sentidoApertura,
        ladoApertura: datos.ladoApertura,
    });
}

export function agregarVentana(plano, datos) {
    const muro = buscarMuro(plano, datos.muroId);
    validarElementoEnMuro(muro, datos.distanciaDesdeInicio, datos.ancho, 'La ventana no cabe dentro del muro seleccionado.');

    plano.elementos.push({
        id: siguienteId(plano.elementos, 'ventana'),
        tipo: 'ventana',
        muroId: datos.muroId,
        distanciaDesdeInicio: Number(datos.distanciaDesdeInicio),
        ancho: Number(datos.ancho),
    });
}

export function eliminarElemento(plano, elementoId) {
    plano.elementos = plano.elementos.filter((elemento) => elemento.id !== elementoId);
}

export function actualizarElemento(plano, elementoId, cambios) {
    const elemento = buscarElemento(plano, elementoId);
    const original = { ...elemento };

    try {
        if (cambios.muroId !== undefined) {
            buscarMuro(plano, cambios.muroId);
            elemento.muroId = cambios.muroId;
        }

        if (cambios.distanciaDesdeInicio !== undefined) {
            const distancia = Number(cambios.distanciaDesdeInicio);
            if (!Number.isFinite(distancia) || distancia < 0) {
                throw new Error('La distancia desde el inicio debe ser mayor o igual que 0 cm.');
            }
            elemento.distanciaDesdeInicio = distancia;
        }

        if (cambios.ancho !== undefined) {
            const ancho = Number(cambios.ancho);
            if (!Number.isFinite(ancho) || ancho <= 0) {
                throw new Error('El ancho debe ser mayor que 0 cm.');
            }
            elemento.ancho = ancho;
        }

        const muro = buscarMuro(plano, elemento.muroId);
        validarElementoEnMuro(muro, elemento.distanciaDesdeInicio, elemento.ancho, 'El hueco no cabe dentro del muro seleccionado.');

        if (elemento.tipo === 'puerta') {
            if (cambios.sentidoApertura !== undefined) {
                if (!SENTIDOS_APERTURA_VALIDOS.includes(cambios.sentidoApertura)) {
                    throw new Error('Selecciona un sentido de apertura valido.');
                }
                elemento.sentidoApertura = cambios.sentidoApertura;
            }

            if (cambios.ladoApertura !== undefined) {
                if (!LADOS_APERTURA_VALIDOS.includes(cambios.ladoApertura)) {
                    throw new Error('Selecciona un lado de apertura valido.');
                }
                elemento.ladoApertura = cambios.ladoApertura;
            }
        }
    } catch (error) {
        Object.assign(elemento, original);
        throw error;
    }
}

export function obtenerElementos(plano) {
    return plano.elementos.map((elemento) => ({ ...elemento }));
}

export function agregarElementoInterior(plano, datos) {
    const ancho = Number(datos.ancho);
    const fondo = Number(datos.fondo);

    if (!Number.isFinite(ancho) || ancho <= 0 || !Number.isFinite(fondo) || fondo <= 0) {
        throw new Error('Las dimensiones del elemento deben ser mayores que 0 cm.');
    }

    const elemento = {
        id: siguienteId(plano.interiores, 'elemento'),
        tipo: datos.tipo,
        x: Number(datos.x),
        y: Number(datos.y),
        ancho,
        fondo,
        rotacion: normalizarRotacion(datos.rotacion ?? 0),
        ...(datos.muroId ? { muroId: datos.muroId } : {}),
    };

    plano.interiores.push(elemento);

    return elemento;
}

export function moverElementoInterior(plano, elementoId, posicion) {
    const elemento = buscarElementoInterior(plano, elementoId);

    elemento.x = Number(posicion.x);
    elemento.y = Number(posicion.y);
}

export function actualizarElementoInterior(plano, elementoId, cambios) {
    const elemento = buscarElementoInterior(plano, elementoId);

    if (cambios.ancho !== undefined) {
        const ancho = Number(cambios.ancho);
        if (!Number.isFinite(ancho) || ancho <= 0) {
            throw new Error('El ancho debe ser mayor que 0 cm.');
        }
        elemento.ancho = ancho;
    }

    if (cambios.fondo !== undefined) {
        const fondo = Number(cambios.fondo);
        if (!Number.isFinite(fondo) || fondo <= 0) {
            throw new Error('El fondo debe ser mayor que 0 cm.');
        }
        elemento.fondo = fondo;
    }

    if (cambios.rotacion !== undefined) {
        elemento.rotacion = normalizarRotacion(cambios.rotacion);
    }

    if (cambios.muroId !== undefined) {
        if (cambios.muroId === null || cambios.muroId === '') {
            delete elemento.muroId;
        } else {
            elemento.muroId = String(cambios.muroId);
        }
    }
}

export function duplicarElementoInterior(plano, elementoId, desplazamiento = { x: 12, y: -12 }) {
    const elemento = buscarElementoInterior(plano, elementoId);
    const duplicado = {
        ...elemento,
        id: siguienteId(plano.interiores, 'elemento'),
        x: elemento.x + desplazamiento.x,
        y: elemento.y + desplazamiento.y,
    };

    plano.interiores.push(duplicado);

    return duplicado;
}

export function eliminarElementoInterior(plano, elementoId) {
    plano.interiores = plano.interiores.filter((elemento) => elemento.id !== elementoId);
}

export function obtenerElementosInteriores(plano) {
    return plano.interiores.map((elemento) => ({ ...elemento }));
}

export function serializarPlano(plano) {
    return {
        version: VERSION_PLANO,
        muros: plano.muros.map((muro) => ({
            id: muro.id,
            longitud: muro.longitud,
            direccion: muro.direccion,
            ...(muro.auxiliar ? { auxiliar: true } : {}),
        })),
        puertas: plano.elementos
            .filter((elemento) => elemento.tipo === 'puerta')
            .map((puerta) => ({
                id: puerta.id,
                muroId: puerta.muroId,
                distanciaDesdeInicio: puerta.distanciaDesdeInicio,
                ancho: puerta.ancho,
                sentidoApertura: puerta.sentidoApertura,
                ladoApertura: puerta.ladoApertura,
            })),
        ventanas: plano.elementos
            .filter((elemento) => elemento.tipo === 'ventana')
            .map((ventana) => ({
                id: ventana.id,
                muroId: ventana.muroId,
                distanciaDesdeInicio: ventana.distanciaDesdeInicio,
                ancho: ventana.ancho,
            })),
        elementos: plano.interiores.map((elemento) => ({
            id: elemento.id,
            tipo: elemento.tipo,
            x: elemento.x,
            y: elemento.y,
            ancho: elemento.ancho,
            fondo: elemento.fondo,
            rotacion: elemento.rotacion,
            ...(elemento.muroId ? { muroId: elemento.muroId } : {}),
        })),
    };
}

export function cargarPlanoDesdeJSON(plano, entrada) {
    const datos = typeof entrada === 'string' ? parsearJSONPlano(entrada) : entrada;
    const normalizado = validarPlanoPortable(datos);

    plano.version = VERSION_PLANO;
    plano.muros = normalizado.muros;
    plano.elementos = [
        ...normalizado.puertas.map((puerta) => ({ ...puerta, tipo: 'puerta' })),
        ...normalizado.ventanas.map((ventana) => ({ ...ventana, tipo: 'ventana' })),
    ];
    plano.interiores = normalizado.elementos;

    return plano;
}

function buscarMuro(plano, muroId) {
    const muro = plano.muros.find((candidato) => candidato.id === muroId);

    if (!muro) {
        throw new Error('Selecciona un muro valido.');
    }

    return muro;
}

function sinMuroAsociado(elemento) {
    const { muroId, ...resto } = elemento;

    return resto;
}

function buscarElemento(plano, elementoId) {
    const elemento = plano.elementos.find((candidato) => candidato.id === elementoId);

    if (!elemento) {
        throw new Error('Selecciona una puerta o ventana valida.');
    }

    return elemento;
}

function validarElementoEnMuro(muro, distanciaDesdeInicio, ancho, mensajeFueraDeMuro) {
    const distancia = Number(distanciaDesdeInicio);
    const anchoNumerico = Number(ancho);

    if (!Number.isFinite(distancia) || distancia < 0) {
        throw new Error('La distancia desde el inicio debe ser mayor o igual que 0 cm.');
    }

    if (!Number.isFinite(anchoNumerico) || anchoNumerico <= 0) {
        throw new Error('El ancho debe ser mayor que 0 cm.');
    }

    if (distancia + anchoNumerico > muro.longitud) {
        throw new Error(mensajeFueraDeMuro);
    }
}

function parsearJSONPlano(entrada) {
    try {
        return JSON.parse(entrada);
    } catch (error) {
        throw new Error('El JSON no es valido.');
    }
}

function validarPlanoPortable(datos) {
    if (!esObjetoPlano(datos)) {
        throw new Error('El JSON del plano debe ser un objeto.');
    }

    if (datos.version !== VERSION_PLANO) {
        throw new Error(`Version de plano no soportada. Se esperaba version ${VERSION_PLANO}.`);
    }

    validarArray(datos.muros, 'muros');
    validarArray(datos.puertas, 'puertas');
    validarArray(datos.ventanas, 'ventanas');
    validarArray(datos.elementos, 'elementos');

    const muros = datos.muros.map((muro, index) => validarMuroPortable(muro, index));
    const muroIds = new Set(muros.map((muro) => muro.id));

    if (muroIds.size !== muros.length) {
        throw new Error('El plano contiene muros con ids duplicados.');
    }

    const puertas = datos.puertas.map((puerta, index) => (
        validarPuertaPortable(puerta, index, muros, muroIds)
    ));
    const ventanas = datos.ventanas.map((ventana, index) => (
        validarVentanaPortable(ventana, index, muros, muroIds)
    ));
    const elementos = datos.elementos.map((elemento, index) => validarInteriorPortable(elemento, index, muroIds));

    validarIdsUnicos([...puertas, ...ventanas], 'puertas y ventanas');
    validarIdsUnicos(elementos, 'elementos interiores');

    return { muros, puertas, ventanas, elementos };
}

function validarMuroPortable(muro, index) {
    validarObjeto(muro, `muros[${index}]`);
    validarId(muro.id, `muros[${index}].id`);

    if (!DIRECCIONES_VALIDAS.includes(muro.direccion)) {
        throw new Error(`Direccion no valida en muros[${index}].`);
    }

    return {
        id: muro.id,
        longitud: validarNumeroPositivo(muro.longitud, `muros[${index}].longitud`),
        direccion: muro.direccion,
        auxiliar: muro.auxiliar === true,
    };
}

function validarPuertaPortable(puerta, index, muros, muroIds) {
    validarObjeto(puerta, `puertas[${index}]`);
    const comun = validarHuecoPortable(puerta, index, 'puertas', muros, muroIds);

    if (!SENTIDOS_APERTURA_VALIDOS.includes(puerta.sentidoApertura)) {
        throw new Error(`Sentido de apertura no valido en puertas[${index}].`);
    }

    if (!LADOS_APERTURA_VALIDOS.includes(puerta.ladoApertura)) {
        throw new Error(`Lado de apertura no valido en puertas[${index}].`);
    }

    return {
        ...comun,
        sentidoApertura: puerta.sentidoApertura,
        ladoApertura: puerta.ladoApertura,
    };
}

function validarVentanaPortable(ventana, index, muros, muroIds) {
    validarObjeto(ventana, `ventanas[${index}]`);
    return validarHuecoPortable(ventana, index, 'ventanas', muros, muroIds);
}

function validarHuecoPortable(hueco, index, coleccion, muros, muroIds) {
    validarId(hueco.id, `${coleccion}[${index}].id`);
    validarId(hueco.muroId, `${coleccion}[${index}].muroId`);

    if (!muroIds.has(hueco.muroId)) {
        throw new Error(`${coleccion}[${index}] referencia un muro inexistente.`);
    }

    const distanciaDesdeInicio = validarNumeroNoNegativo(hueco.distanciaDesdeInicio, `${coleccion}[${index}].distanciaDesdeInicio`);
    const ancho = validarNumeroPositivo(hueco.ancho, `${coleccion}[${index}].ancho`);
    const muro = muros.find((candidato) => candidato.id === hueco.muroId);

    if (distanciaDesdeInicio + ancho > muro.longitud) {
        throw new Error(`${coleccion}[${index}] no cabe dentro de su muro.`);
    }

    return {
        id: hueco.id,
        muroId: hueco.muroId,
        distanciaDesdeInicio,
        ancho,
    };
}

function validarInteriorPortable(elemento, index, muroIds) {
    validarObjeto(elemento, `elementos[${index}]`);
    validarId(elemento.id, `elementos[${index}].id`);

    if (!TIPOS_INTERIORES_VALIDOS.includes(elemento.tipo)) {
        throw new Error(`Tipo de elemento interior no valido en elementos[${index}].`);
    }

    return {
        id: elemento.id,
        tipo: elemento.tipo,
        x: validarNumeroFinito(elemento.x, `elementos[${index}].x`),
        y: validarNumeroFinito(elemento.y, `elementos[${index}].y`),
        ancho: validarNumeroPositivo(elemento.ancho, `elementos[${index}].ancho`),
        fondo: validarNumeroPositivo(elemento.fondo, `elementos[${index}].fondo`),
        rotacion: normalizarRotacion(validarNumeroFinito(elemento.rotacion, `elementos[${index}].rotacion`)),
        ...(elemento.muroId !== undefined ? { muroId: validarMuroIdInterior(elemento.muroId, `elementos[${index}].muroId`, muroIds) } : {}),
    };
}

function validarIdPortable(valor, nombre) {
    validarId(valor, nombre);

    return valor;
}

function validarMuroIdInterior(valor, nombre, muroIds) {
    const muroId = validarIdPortable(valor, nombre);

    if (!muroIds.has(muroId)) {
        throw new Error(`${nombre} referencia un muro inexistente.`);
    }

    return muroId;
}

function validarArray(valor, nombre) {
    if (!Array.isArray(valor)) {
        throw new Error(`El campo ${nombre} debe ser un array.`);
    }
}

function validarObjeto(valor, nombre) {
    if (!esObjetoPlano(valor)) {
        throw new Error(`${nombre} debe ser un objeto.`);
    }
}

function esObjetoPlano(valor) {
    return Boolean(valor) && typeof valor === 'object' && !Array.isArray(valor);
}

function validarId(valor, nombre) {
    if (typeof valor !== 'string' || valor.trim() === '') {
        throw new Error(`${nombre} debe ser un texto no vacio.`);
    }
}

function validarNumeroPositivo(valor, nombre) {
    const numero = validarNumeroFinito(valor, nombre);

    if (numero <= 0) {
        throw new Error(`${nombre} debe ser mayor que 0.`);
    }

    return numero;
}

function validarNumeroNoNegativo(valor, nombre) {
    const numero = validarNumeroFinito(valor, nombre);

    if (numero < 0) {
        throw new Error(`${nombre} debe ser mayor o igual que 0.`);
    }

    return numero;
}

function validarNumeroFinito(valor, nombre) {
    const numero = Number(valor);

    if (!Number.isFinite(numero)) {
        throw new Error(`${nombre} debe ser un numero valido.`);
    }

    return numero;
}

function validarIdsUnicos(elementos, nombre) {
    const ids = new Set(elementos.map((elemento) => elemento.id));

    if (ids.size !== elementos.length) {
        throw new Error(`El plano contiene ids duplicados en ${nombre}.`);
    }
}

function siguienteId(coleccion, prefijo) {
    const usados = coleccion
        .map((item) => item.id)
        .filter((id) => typeof id === 'string' && id.startsWith(`${prefijo}-`))
        .map((id) => Number(id.replace(`${prefijo}-`, '')))
        .filter(Number.isFinite);

    return `${prefijo}-${Math.max(0, ...usados) + 1}`;
}

function buscarElementoInterior(plano, elementoId) {
    const elemento = plano.interiores.find((candidato) => candidato.id === elementoId);

    if (!elemento) {
        throw new Error('Selecciona un elemento interior valido.');
    }

    return elemento;
}

function normalizarRotacion(rotacion) {
    const valor = Number(rotacion);

    if (!Number.isFinite(valor)) {
        return 0;
    }

    return ((valor % 360) + 360) % 360;
}
