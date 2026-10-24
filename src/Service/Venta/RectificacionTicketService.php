<?php

namespace App\Service\Venta;

use App\Entity\Cestas;
use App\Entity\Documento;
use App\Entity\DocumentoCobro;
use App\Entity\DocumentoLinea;
use App\Entity\Efectivo;
use App\Entity\Forecast;
use App\Entity\SerieDocumento;
use App\Entity\Tiposmovimiento;
use App\Service\Documento\SerieService;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class RectificacionTicketService
{
    public function __construct(private ManagerRegistry $doctrine) {}

    public function rectificar(string $uuidOriginal, array $data): RectificacionTpvResultado
    {
        $uuidOriginal = $this->uuid($uuidOriginal);
        $input = $this->validar($data, false);
        $huella = $this->huella($uuidOriginal, $input);

        return $this->transaccion(function (EntityManagerInterface $em) use ($uuidOriginal, $input, $huella) {
            $original = $this->original($em, $uuidOriginal, $input['caja']);
            $existente = $this->bloquear($em, ['tpvUuid' => $input['uuid']]);
            if ($existente !== null) {
                if (!$existente->isRectificativaTpv() || $existente->getDocumentoRectificado()?->getId() !== $original->getId()
                    || $existente->getTpvRectificacionHuella() !== $huella) {
                    throw new TpvOperacionException('UUID_REUTILIZADO', 'El UUID ya está asociado a otra operación o a otros datos.');
                }
                return new RectificacionTpvResultado($existente, true);
            }
            if ($em->getRepository(Cestas::class)->findOneBy(['tpvUuid' => $input['uuid']])) {
                throw new TpvOperacionException('UUID_REUTILIZADO', 'El UUID pertenece a una venta antigua.');
            }
            // Lectura bloqueante: ve también la rectificación confirmada por otra petición mientras esperábamos.
            if ($this->bloquear($em, ['documentoRectificado' => $original]) !== null) {
                throw new TpvOperacionException('TICKET_YA_RECTIFICADO', 'El ticket ya tiene una rectificativa total.');
            }
            if ($original->getEstadoCobro() !== 'cobrado' || bccomp($original->getTotal(), '0', 2) < 0) {
                throw new TpvOperacionException('TICKET_NO_ADMITIDO', 'Solo se pueden rectificar ventas TPV cobradas.');
            }
            $this->validarFechaPosterior($input['fecha'], $original);
            if ($original->getLineas()->isEmpty()) {
                throw new TpvOperacionException('TICKET_SIN_LINEAS', 'El ticket original no contiene líneas.');
            }

            $rectificativa = new Documento();
            $rectificativa->setTipoDocumento('ticket_rectificativo');
            $rectificativa->setDocumentoRectificado($original);
            $rectificativa->setMotivoRectificacion($input['motivo']);
            $rectificativa->setEstadoComercial('aceptado');
            $rectificativa->setEstadoCobro('pendiente');
            $rectificativa->setEstadoEjecucion('no_aplica');
            $rectificativa->setFechaEmision(\DateTime::createFromInterface($input['fecha']));
            $rectificativa->setTpvFecha($input['fecha']);
            $rectificativa->setCliente($original->getCliente());
            $rectificativa->setTpvUuid($input['uuid']);
            $rectificativa->setTpvCaja($input['caja']);
            $rectificativa->setTpvNumeroTicketLocal($input['numeroTicketLocal']);
            $rectificativa->setTpvRectificacionHuella($huella);

            foreach ($original->getLineas() as $origen) {
                $linea = new DocumentoLinea();
                foreach (['Posicion', 'TipoLinea', 'Producto', 'CatalogoProducto', 'Descripcion', 'Unidad',
                    'PrecioUnitario', 'PrecioUnitarioConIva', 'TpvPrecioOriginal', 'CosteUnitario', 'Descuento', 'TipoIva',
                    'CosteUnitarioBase', 'PrecioCosteUnitario', 'PorcentajeIva', 'PorcentajeRecargoEquivalencia',
                    'ImporteIvaUnitario', 'ImporteRecargoUnitario'] as $campo) {
                    $linea->{'set' . $campo}($origen->{'get' . $campo}());
                }
                $linea->setOrigenLinea('tpv_rectificacion');
                $linea->setDestinoFacturacion(DocumentoLinea::DESTINO_TICKET_TIENDA);
                $linea->setCantidad(bcsub('0', $origen->getCantidad(), 3));
                $linea->setSubtotal(bcsub('0', $origen->getSubtotal(), 2));
                $linea->setTotalIva(bcsub('0', $origen->getTotalIva(), 2));
                $linea->setTotalCoste(bcsub('0', $origen->getTotalCoste(), 2));
                $rectificativa->addLinea($linea);
            }
            // Se compensan los importes históricos exactos; nunca se consulta el precio/IVA actual.
            $rectificativa->setBaseImponible(bcsub('0', $original->getBaseImponible(), 2));
            $rectificativa->setTotalIva(bcsub('0', $original->getTotalIva(), 2));
            $rectificativa->setTotal(bcsub('0', $original->getTotal(), 2));
            $rectificativa->setTotalCoste(bcsub('0', $original->getTotalCoste(), 2));
            (new SerieService($em, $em->getRepository(SerieDocumento::class)))->asignarNumeracion($rectificativa);
            $original->getRectificativas()->add($rectificativa);
            $em->persist($rectificativa);

            return new RectificacionTpvResultado($rectificativa, false);
        });
    }

    public function confirmarReembolso(string $uuidOriginal, string $uuidRectificativa, array $data): RectificacionTpvResultado
    {
        $uuidOriginal = $this->uuid($uuidOriginal);
        $uuidRectificativa = $this->uuid($uuidRectificativa);
        $input = $this->validar($data, true);
        $huella = $this->huella($uuidOriginal . '/' . $uuidRectificativa, $input);

        return $this->transaccion(function (EntityManagerInterface $em) use ($uuidOriginal, $uuidRectificativa, $input, $huella) {
            $original = $this->original($em, $uuidOriginal, $input['caja']);
            $rectificativa = $this->bloquear($em, ['tpvUuid' => $uuidRectificativa]);
            if ($rectificativa === null || !$rectificativa->isRectificativaTpv()
                || $rectificativa->getDocumentoRectificado()?->getId() !== $original->getId()) {
                throw new TpvOperacionException('RECTIFICATIVA_NO_ENCONTRADA', 'No existe esa rectificativa para el ticket.', 404);
            }
            if ($rectificativa->getTpvReembolsoUuid() !== null) {
                if ($rectificativa->getTpvReembolsoUuid() !== $input['uuid'] || $rectificativa->getTpvReembolsoHuella() !== $huella) {
                    throw new TpvOperacionException('REEMBOLSO_YA_REGISTRADO', 'La devolución ya se ha registrado con otra operación o datos.');
                }
                return new RectificacionTpvResultado($rectificativa, true);
            }
            if ($this->bloquear($em, ['tpvReembolsoUuid' => $input['uuid']]) !== null) {
                throw new TpvOperacionException('UUID_REUTILIZADO', 'El UUID pertenece al reembolso de otro ticket.');
            }
            $this->validarFechaPosterior($input['fecha'], $rectificativa);

            $cobro = new DocumentoCobro();
            $cobro->setFecha(\DateTime::createFromInterface($input['fecha']));
            $cobro->setMetodo(strtolower($input['formaPago']));
            $cobro->setImporteBruto($rectificativa->getTotal());
            $cobro->setImporteNeto($rectificativa->getTotal());
            $cobro->setReferencia($input['referencia'] ?? $input['uuid']);
            $cobro->setNotas('Reembolso confirmado por TPV ' . $input['caja'] . '; operación ' . $input['uuid']);
            $rectificativa->addCobro($cobro);
            $rectificativa->setTotalCobrado($rectificativa->getTotal());
            $rectificativa->setEstadoCobro('devuelto');
            $rectificativa->setTpvFormaPago($input['formaPago']);
            $rectificativa->setTpvReembolsoUuid($input['uuid']);
            $rectificativa->setTpvReembolsoHuella($huella);

            if ($input['formaPago'] === 'Efectivo') {
                $efectivo = new Efectivo();
                $efectivo->setTipoEf($em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Ventas']));
                $efectivo->setConceptoEf('Devolución TPV ' . $rectificativa->getNumeroFormateado());
                $efectivo->setFechaEf(\DateTime::createFromInterface($input['fecha']));
                $efectivo->setImporteEf((float) $rectificativa->getTotal());
                $em->persist($efectivo);
            } elseif (bccomp($rectificativa->getTotal(), '0', 2) < 0) {
                $tipo = $em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Ventas']);
                if ($tipo === null) {
                    throw new TpvOperacionException('CONFIGURACION_INCOMPLETA', 'Falta la categoría Ventas para registrar la previsión del reembolso.');
                }
                $forecast = new Forecast();
                $forecast->setTipoFr($tipo);
                $forecast->setConceptoFr('Devolución TPV ' . $rectificativa->getNumeroFormateado()
                    . ' · ' . ($input['referencia'] ?? $input['uuid']));
                $forecast->setFechaFr(\DateTime::createFromInterface($input['fecha']));
                $forecast->setImporteFr((float) $rectificativa->getTotal());
                $forecast->setOrigenFr('TPV');
                $forecast->setEstadoFr('P');
                $em->persist($forecast);
            }
            $em->persist($rectificativa);
            return new RectificacionTpvResultado($rectificativa, false);
        });
    }

    private function original(EntityManagerInterface $em, string $uuid, string $caja): Documento
    {
        $original = $this->bloquear($em, ['tpvUuid' => $uuid]);
        if ($original === null) {
            if ($em->getRepository(Cestas::class)->findOneBy(['tpvUuid' => $uuid])) {
                throw new TpvOperacionException('TICKET_NO_ADMITIDO', 'Los tickets antiguos de Cestas no se rectifican por esta API.');
            }
            throw new TpvOperacionException('VENTA_NO_SINCRONIZADA', 'Sincroniza la venta original antes de rectificarla.');
        }
        if (!$original->isTicketTpv() || $original->getTipoDocumento() !== 'ticket') {
            throw new TpvOperacionException('TICKET_NO_ADMITIDO', 'Solo se admiten tickets de venta generados por el TPV.');
        }
        if ($original->getTpvCaja() !== $caja) {
            throw new TpvOperacionException('CAJA_NO_AUTORIZADA', 'La venta pertenece a otra caja.', 403);
        }
        return $original;
    }

    private function bloquear(EntityManagerInterface $em, array $criteria): ?Documento
    {
        $qb = $em->getRepository(Documento::class)->createQueryBuilder('d');
        foreach ($criteria as $campo => $valor) {
            $qb->andWhere('d.' . $campo . ' = :' . $campo)->setParameter($campo, $valor);
        }
        $doc = $qb->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if ($doc !== null) {
            $em->refresh($doc, LockMode::PESSIMISTIC_WRITE);
        }
        return $doc;
    }

    private function transaccion(callable $operacion): RectificacionTpvResultado
    {
        for ($intento = 0; $intento < 3; ++$intento) {
            try {
                return $this->doctrine->getManager()->wrapInTransaction($operacion);
            } catch (UniqueConstraintViolationException|RetryableException $e) {
                $this->doctrine->resetManager();
                if ($intento === 2) {
                    throw new TpvOperacionException('REINTENTAR_OPERACION', 'Conflicto temporal; reintenta con el mismo UUID.', 503);
                }
            } catch (TpvOperacionException $e) {
                $this->doctrine->resetManager();
                throw $e;
            }
        }
        throw new \LogicException('Operación no completada.');
    }

    private function validar(array $data, bool $reembolso): array
    {
        $campos = $reembolso
            ? ['uuid', 'fecha', 'caja', 'formaPago', 'referencia']
            : ['uuid', 'fecha', 'caja', 'tipo', 'motivo', 'numeroTicketLocal'];
        if (array_diff(array_keys($data), $campos)) {
            throw new TpvOperacionException('DATOS_INVALIDOS', 'La petición contiene campos no admitidos.', 400);
        }
        $input = [
            'uuid' => $this->uuid($this->texto($data, 'uuid', 36)),
            'caja' => $this->texto($data, 'caja', 50),
        ];
        $fecha = $this->texto($data, 'fecha', 40);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $fecha)) {
            throw new TpvOperacionException('DATOS_INVALIDOS', 'fecha debe tener formato ISO 8601 con zona horaria.', 400);
        }
        try {
            $input['fecha'] = new \DateTimeImmutable($fecha);
            $errores = \DateTimeImmutable::getLastErrors();
            if ($errores && ($errores['warning_count'] || $errores['error_count'])) {
                throw new \InvalidArgumentException();
            }
        } catch (\Exception) {
            throw new TpvOperacionException('DATOS_INVALIDOS', 'fecha no es válida.', 400);
        }
        if ($reembolso) {
            $input['formaPago'] = match (strtolower($this->texto($data, 'formaPago', 20))) {
                'efectivo' => 'Efectivo',
                'tarjeta' => 'Tarjeta',
                default => throw new TpvOperacionException('DATOS_INVALIDOS', 'La devolución debe ser en efectivo o tarjeta.', 400),
            };
            $input['referencia'] = isset($data['referencia']) ? $this->texto($data, 'referencia', 100) : null;
        } else {
            if (($data['tipo'] ?? null) !== 'total') {
                throw new TpvOperacionException('DATOS_INVALIDOS', 'Solo se admite tipo total.', 400);
            }
            $input['tipo'] = 'total';
            $input['motivo'] = $this->texto($data, 'motivo', 255);
            $input['numeroTicketLocal'] = isset($data['numeroTicketLocal']) ? $this->texto($data, 'numeroTicketLocal', 55) : null;
        }
        return $input;
    }

    private function texto(array $data, string $campo, int $max): string
    {
        $valor = $data[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '' || mb_strlen(trim($valor)) > $max) {
            throw new TpvOperacionException('DATOS_INVALIDOS', sprintf('%s es obligatorio y admite hasta %d caracteres.', $campo, $max), 400);
        }
        return trim($valor);
    }

    private function uuid(string $valor): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $valor)) {
            throw new TpvOperacionException('DATOS_INVALIDOS', 'UUID no válido.', 400);
        }
        return strtolower($valor);
    }

    private function huella(string $original, array $input): string
    {
        $input['fecha'] = $input['fecha']->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
        return hash('sha256', json_encode([$original, $input], JSON_THROW_ON_ERROR));
    }

    private function validarFechaPosterior(\DateTimeImmutable $fecha, Documento $original): void
    {
        if ($fecha->format('Y-m-d') < $original->getFechaEmision()->format('Y-m-d')) {
            throw new TpvOperacionException('DATOS_INVALIDOS', 'La fecha no puede ser anterior a la del documento original.', 400);
        }
    }
}
