<?php

namespace App\Entity;

use App\Repository\BolsaGastosAjusteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BolsaGastosAjusteRepository::class)]
#[ORM\Table(name: 'bolsa_gastos_ajuste')]
#[ORM\Index(name: 'IDX_BOLSA_GASTOS_AJUSTE_FECHA', columns: ['fecha'])]
class BolsaGastosAjuste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $fecha;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $importeBase = '0.00';

    #[ORM\Column(type: 'string', length: 255)]
    private string $motivo = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
        $this->fecha = $this->creadoEn->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeImmutable $fecha): self
    {
        $this->fecha = $fecha->setTime(0, 0);

        return $this;
    }

    public function getImporteBase(): string
    {
        return $this->importeBase;
    }

    public function setImporteBase(string $importeBase): self
    {
        $importeBase = trim($importeBase);
        if (!preg_match('/^[+-]?\d{1,10}(?:\.\d{1,2})?$/D', $importeBase) || (float) $importeBase == 0.0) {
            throw new \InvalidArgumentException('El ajuste debe tener un importe distinto de cero, con un máximo de dos decimales y diez cifras enteras.');
        }
        $this->importeBase = number_format((float) $importeBase, 2, '.', '');

        return $this;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function setMotivo(string $motivo): self
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 255) {
            throw new \InvalidArgumentException('Indica un motivo para el ajuste, de hasta 255 caracteres.');
        }
        $this->motivo = $motivo;

        return $this;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
