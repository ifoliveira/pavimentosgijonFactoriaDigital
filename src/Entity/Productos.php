<?php

namespace App\Entity;

use App\Repository\ProductosRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductosRepository::class)]
class Productos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion_Pd = null;

    #[ORM\Column(type: 'float')]
    private ?float $precio_Pd = null;

    #[ORM\Column(type: 'float')]
    private ?float $pvp_Pd = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $stock_Pd = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $fecAlta_Pd = null;

    #[ORM\ManyToOne(inversedBy: 'productos')]
    #[ORM\JoinColumn(name: 'tipo_pd_id', referencedColumnName: 'id', nullable: true)]
    private ?Tipoproducto $tipo_pd_id = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $obsoleto = null;

    #[ORM\OneToMany(mappedBy: 'producto', targetEntity: StockItem::class, orphanRemoval: true)]
    private Collection $stockItems;

    #[ORM\OneToMany(mappedBy: 'producto', targetEntity: UsoDeStock::class, orphanRemoval: true)]
    private Collection $usoDeStocks;

    public function __construct()
    {
        $this->setFecAltaPd(new \DateTime());
        $this->stockItems = new ArrayCollection();
        $this->usoDeStocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescripcionPd(): ?string
    {
        return $this->descripcion_Pd;
    }

    public function setDescripcionPd(string $descripcion_Pd): self
    {
        $this->descripcion_Pd = $descripcion_Pd;
        return $this;
    }

    public function getPrecioPd(): ?float
    {
        return $this->precio_Pd;
    }

    public function setPrecioPd(float $precio_Pd): self
    {
        $this->precio_Pd = $precio_Pd;
        return $this;
    }

    public function getPvpPd(): ?float
    {
        return $this->pvp_Pd;
    }

    public function setPvpPd(float $pvp_Pd): self
    {
        $this->pvp_Pd = $pvp_Pd;
        return $this;
    }

    public function getStockPd(): ?int
    {
        return $this->stock_Pd;
    }

    public function setStockPd(int $stock_Pd): self
    {
        $this->stock_Pd = $stock_Pd;
        return $this;
    }

    public function getFecAltaPd(): ?\DateTimeInterface
    {
        return $this->fecAlta_Pd;
    }

    public function setFecAltaPd(\DateTimeInterface $fecAlta_Pd): self
    {
        $this->fecAlta_Pd = $fecAlta_Pd;
        return $this;
    }

    public function getTipoPdId(): ?Tipoproducto
    {
        return $this->tipo_pd_id;
    }

    public function setTipoPdId(?Tipoproducto $tipo_pd): self
    {
        $this->tipo_pd_id = $tipo_pd;
        return $this;
    }

    public function __toString()
    {
        return (string) $this->descripcion_Pd;
    }

    public function isObsoleto(): ?bool
    {
        return $this->obsoleto;
    }

    public function setObsoleto(?bool $obsoleto): self
    {
        $this->obsoleto = $obsoleto;
        return $this;
    }

    /**
     * @return Collection<int, StockItem>
     */
    public function getStockItems(): Collection
    {
        return $this->stockItems;
    }

    public function addStockItems(StockItem $stockItems): self
    {
        if (!$this->stockItems->contains($stockItems)) {
            $this->stockItems[] = $stockItems;
            $stockItems->setProducto($this);
        }
        return $this;
    }

    public function removeStockItems(StockItem $stockItems): self
    {
        if ($this->stockItems->removeElement($stockItems)) {
            if ($stockItems->getProducto() === $this) {
                $stockItems->setProducto(null);
            }
        }
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
            $usoDeStock->setProducto($this);
        }
        return $this;
    }

    public function removeUsoDeStock(UsoDeStock $usoDeStock): self
    {
        if ($this->usoDeStocks->removeElement($usoDeStock)) {
            if ($usoDeStock->getProducto() === $this) {
                $usoDeStock->setProducto(null);
            }
        }
        return $this;
    }
}