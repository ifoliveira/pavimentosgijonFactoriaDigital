<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\SerieDocumento;
use App\Repository\SerieDocumentoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class SerieService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerieDocumentoRepository $serieDocumentoRepository,
    ) {
    }

    public function obtenerSerieParaTipo(string $tipoDocumento, ?\DateTimeInterface $fecha = null): string
    {
        $fecha ??= new \DateTime();
        $anio = $fecha->format('Y');

        return match ($tipoDocumento) {
            'presupuesto' => 'P' . $anio,
            'factura' => 'F' . $anio,
            'ticket' => 'T' . $anio,
            default => throw new \InvalidArgumentException(sprintf(
                'Tipo de documento no soportado para serie: %s',
                $tipoDocumento
            )),
        };
    }

    public function siguienteNumero(string $codigoSerie): int
    {
        return $this->em->wrapInTransaction(function () use ($codigoSerie) {
            $serie = $this->serieDocumentoRepository->findOneByCodigo($codigoSerie);

            if (!$serie) {
                $serie = new SerieDocumento();
                $serie->setCodigo($codigoSerie);
                $serie->setUltimoNumero(0);

                $this->em->persist($serie);
                $this->em->flush();
            }

            // Bloqueo pesimista para evitar duplicados en concurrencia
            $this->em->lock($serie, LockMode::PESSIMISTIC_WRITE);

            $numero = $serie->incrementar();
            $this->em->flush();

            return $numero;
        });
    }

    public function asignarNumeracion(Documento $documento): void
    {
        if ($documento->getSerie() && $documento->getNumero()) {
            return;
        }

        $serie = $this->obtenerSerieParaTipo(
            $documento->getTipoDocumento(),
            $documento->getFechaEmision()
        );

        $numero = $this->siguienteNumero($serie);

        $documento->setSerie($serie);
        $documento->setNumero($numero);
    }
}