<?php

namespace App\Entity;

use App\Repository\StockItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockItemRepository::class)]
class StockItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Productos $producto = null;

    #[ORM\Column(type: 'integer')]
    private ?int $cantidad = null;

    #[ORM\Column(type: 'float')]
    private ?float $precioUnitario = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $fechaEntrada = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $proveedor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $facturaId = null;

    #[ORM\OneToMany(mappedBy: 'stockItem', targetEntity: UsoDeStock::class, orphanRemoval: true)]
    private Collection $usoDeStocks;

    public function __construct()
    {
        $this->usoDeStocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProducto(): ?Productos
    {
        return $this->producto;
    }

    public function setProducto(?Productos $producto): self
    {
        $this->producto = $producto;
        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getPrecioUnitario(): ?float
    {
        return $this->precioUnitario;
    }

    public function setPrecioUnitario(float $precioUnitario): self
    {
        $this->precioUnitario = $precioUnitario;
        return $this;
    }

    public function getFechaEntrada(): ?\DateTimeInterface
    {
        return $this->fechaEntrada;
    }

    public function setFechaEntrada(\DateTimeInterface $fechaEntrada): self
    {
        $this->fechaEntrada = $fechaEntrada;
        return $this;
    }

    public function getProveedor(): ?string
    {
        return $this->proveedor;
    }

    public function setProveedor(?string $proveedor): self
    {
        $this->proveedor = $proveedor;
        return $this;
    }

    public function getFacturaId(): ?string
    {
        return $this->facturaId;
    }

    public function setFacturaId(?string $facturaId): self
    {
        $this->facturaId = $facturaId;
        return $this;
    }

    /**
     * @return Collection<int, UsoDeStock>
     */
    public function getUsoDeStocks(): Collection
    {
        return $this->usoDeStocks;
    }

    public function addUsoDeStock(UsoDeStock $usoDeStock): self
    {
        if (!$this->usoDeStocks->contains($usoDeStock)) {
            $this->usoDeStocks[] = $usoDeStock;
            $usoDeStock->setStockItem($this);
        }
        return $this;
    }

    public function removeUsoDeStock(UsoDeStock $usoDeStock): self
    {
        if ($this->usoDeStocks->removeElement($usoDeStock)) {
            if ($usoDeStock->getStockItem() === $this) {
                $usoDeStock->setStockItem(null);
            }
        }
        return $this;
    }
}