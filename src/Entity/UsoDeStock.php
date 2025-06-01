<?php

namespace App\Entity;

use App\Repository\UsoDeStockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UsoDeStockRepository::class)
 */
class UsoDeStock
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Productos::class, inversedBy="usoDeStocks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $producto;

    /**
     * @ORM\ManyToOne(targetEntity=StockItem::class, inversedBy="usoDeStocks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $stockItem;

    /**
     * @ORM\ManyToOne(targetEntity=Presupuestos::class, inversedBy="usoDeStocks")
     */
    private $presupuesto;

    /**
     * @ORM\Column(type="integer")
     */
    private $cantidad;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fecha;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comentario;

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

    public function getStockItem(): ?StockItem
    {
        return $this->stockItem;
    }

    public function setStockItem(?StockItem $stockItem): self
    {
        $this->stockItem = $stockItem;

        return $this;
    }

    public function getPresupuesto(): ?Presupuestos
    {
        return $this->presupuesto;
    }

    public function setPresupuesto(?Presupuestos $presupuesto): self
    {
        $this->presupuesto = $presupuesto;

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

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getComentario(): ?string
    {
        return $this->comentario;
    }

    public function setComentario(?string $comentario): self
    {
        $this->comentario = $comentario;

        return $this;
    }
}
