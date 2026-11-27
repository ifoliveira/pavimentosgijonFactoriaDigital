<?php

namespace App\Entity;

use App\Repository\BolsaGastosConfiguracionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BolsaGastosConfiguracionRepository::class)]
#[ORM\Table(name: 'bolsa_gastos_configuracion')]
class BolsaGastosConfiguracion
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id = 1;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaInicio = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $ivaEstimado = '21.00';

    #[ORM\Column(type: 'integer')]
    private int $plazoPagoDias = 30;

    #[ORM\Column(type: 'integer')]
    private int $plazoObraDias = 60;

    /** @var list<string> */
    #[ORM\Column(
        type: 'json',
        options: ['collation' => 'utf8mb4_bin']
    )]
    private array $proveedoresExcluidos = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function getFechaInicio(): ?\DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(?\DateTimeImmutable $fechaInicio): self
    {
        $this->fechaInicio = $fechaInicio?->setTime(0, 0);

        return $this;
    }

    public function getIvaEstimado(): string
    {
        return $this->ivaEstimado;
    }

    public function setIvaEstimado(string $ivaEstimado): self
    {
        $ivaEstimado = trim($ivaEstimado);
        if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $ivaEstimado) || (float) $ivaEstimado > 100) {
            throw new \InvalidArgumentException('El IVA estimado debe estar entre 0 y 100, con un máximo de dos decimales.');
        }
        $this->ivaEstimado = number_format((float) $ivaEstimado, 2, '.', '');

        return $this;
    }

    public function getPlazoPagoDias(): int
    {
        return $this->plazoPagoDias;
    }

    public function setPlazoPagoDias(int $plazoPagoDias): self
    {
        if ($plazoPagoDias < 0 || $plazoPagoDias > 365) {
            throw new \InvalidArgumentException('El plazo de pago debe estar entre 0 y 365 días.');
        }
        $this->plazoPagoDias = $plazoPagoDias;

        return $this;
    }

    public function getPlazoObraDias(): int
    {
        return $this->plazoObraDias;
    }

    public function setPlazoObraDias(int $plazoObraDias): self
    {
        if ($plazoObraDias < 0 || $plazoObraDias > 730) {
            throw new \InvalidArgumentException('El plazo de obra debe estar entre 0 y 730 días.');
        }
        $this->plazoObraDias = $plazoObraDias;

        return $this;
    }

    /** @return list<string> */
    public function getProveedoresExcluidos(): array
    {
        return $this->proveedoresExcluidos;
    }

    /** @param list<string> $proveedoresExcluidos */
    public function setProveedoresExcluidos(array $proveedoresExcluidos): self
    {
        $nombres = [];
        foreach ($proveedoresExcluidos as $nombre) {
            if (!is_string($nombre) || mb_strlen(trim($nombre)) > 255) {
                throw new \InvalidArgumentException('Cada proveedor excluido debe tener un nombre de hasta 255 caracteres.');
            }
            $nombre = trim($nombre);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }
        $this->proveedoresExcluidos = array_values(array_unique($nombres));

        return $this;
    }
}
