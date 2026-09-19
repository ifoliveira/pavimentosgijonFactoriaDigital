<?php

namespace App\Entity;

use App\Repository\PresupuestoWebRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoWebRepository::class)]
#[ORM\Table(name: 'presupuesto_web')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRESUPUESTO_WEB_TOKEN', columns: ['token'])]
class PresupuestoWeb
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoWebLead::class, inversedBy: 'presupuestosWeb')]
    #[ORM\JoinColumn(nullable: false)]
    private PresupuestoWebLead $lead;

    #[ORM\Column(type: 'string', length: 32)]
    private string $tipoPresupuesto;

    #[ORM\Column(type: 'string', length: 64)]
    private string $token;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $total;

    #[ORM\Column(type: 'json')]
    private array $jsonSolicitudBudgetFlow;

    #[ORM\Column(type: 'json')]
    private array $jsonPresupuesto;

    #[ORM\Column(type: 'integer')]
    private int $numeroVisualizaciones = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $primeraVisualizacionAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimaVisualizacionAt = null;

    #[ORM\OneToMany(mappedBy: 'presupuestoWeb', targetEntity: PresupuestoWebComunicacion::class)]
    private Collection $comunicaciones;

    #[ORM\OneToMany(mappedBy: 'presupuestoWeb', targetEntity: PresupuestoWebAcceso::class)]
    private Collection $accesos;

    public function __construct(
        PresupuestoWebLead $lead,
        string $tipoPresupuesto,
        string $token,
        array $jsonSolicitudBudgetFlow,
        array $jsonPresupuesto,
        ?string $total,
        ?\DateTimeImmutable $now = null
    ) {
        $this->lead = $lead;
        $this->tipoPresupuesto = $tipoPresupuesto;
        $this->token = $token;
        $this->jsonSolicitudBudgetFlow = $jsonSolicitudBudgetFlow;
        $this->jsonPresupuesto = $jsonPresupuesto;
        $this->total = $total;
        $this->createdAt = $now ?? new \DateTimeImmutable();
        $this->comunicaciones = new ArrayCollection();
        $this->accesos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): PresupuestoWebLead
    {
        return $this->lead;
    }

    public function getTipoPresupuesto(): string
    {
        return $this->tipoPresupuesto;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function getJsonSolicitudBudgetFlow(): array
    {
        return $this->jsonSolicitudBudgetFlow;
    }

    public function getJsonPresupuesto(): array
    {
        return $this->jsonPresupuesto;
    }

    public function getNumeroVisualizaciones(): int
    {
        return $this->numeroVisualizaciones;
    }

    public function getPrimeraVisualizacionAt(): ?\DateTimeImmutable
    {
        return $this->primeraVisualizacionAt;
    }

    public function getUltimaVisualizacionAt(): ?\DateTimeImmutable
    {
        return $this->ultimaVisualizacionAt;
    }

    public function getComunicaciones(): Collection
    {
        return $this->comunicaciones;
    }

    public function getAccesos(): Collection
    {
        return $this->accesos;
    }

    public function registrarVisualizacion(\DateTimeImmutable $fechaAcceso): self
    {
        $this->numeroVisualizaciones++;

        if ($this->primeraVisualizacionAt === null) {
            $this->primeraVisualizacionAt = $fechaAcceso;
        }

        $this->ultimaVisualizacionAt = $fechaAcceso;

        return $this;
    }
}
