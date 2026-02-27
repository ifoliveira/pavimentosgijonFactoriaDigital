<?php

namespace App\Entity;

use App\Repository\VisitanteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisitanteRepository::class)]
class Visitante
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaPrimeraVisita = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaUltimaVisita = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $origenNormalizado = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $utmOrigen = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $utmMedio = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $utmCampaña = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $gclid = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $referente = null;

    #[ORM\OneToMany(mappedBy: 'visitante', targetEntity: Evento::class, orphanRemoval: true)]
    private Collection $eventos;

    #[ORM\Column(type: 'integer')]
    private int $numeroVisitas = 1;

    public function __construct()
    {
        $this->eventos = new ArrayCollection();
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS & SETTERS
    |--------------------------------------------------------------------------
    */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getFechaPrimeraVisita(): ?\DateTimeImmutable
    {
        return $this->fechaPrimeraVisita;
    }

    public function setFechaPrimeraVisita(?\DateTimeImmutable $fechaPrimeraVisita): self
    {
        $this->fechaPrimeraVisita = $fechaPrimeraVisita;
        return $this;
    }

    public function getFechaUltimaVisita(): ?\DateTimeImmutable
    {
        return $this->fechaUltimaVisita;
    }

    public function setFechaUltimaVisita(?\DateTimeImmutable $fechaUltimaVisita): self
    {
        $this->fechaUltimaVisita = $fechaUltimaVisita;
        return $this;
    }

    public function getOrigenNormalizado(): ?string
    {
        return $this->origenNormalizado;
    }

    public function setOrigenNormalizado(?string $origenNormalizado): self
    {
        $this->origenNormalizado = $origenNormalizado;
        return $this;
    }

    public function getUtmOrigen(): ?string
    {
        return $this->utmOrigen;
    }

    public function setUtmOrigen(?string $utmOrigen): self
    {
        $this->utmOrigen = $utmOrigen;
        return $this;
    }

    public function getUtmMedio(): ?string
    {
        return $this->utmMedio;
    }

    public function setUtmMedio(?string $utmMedio): self
    {
        $this->utmMedio = $utmMedio;
        return $this;
    }

    public function getUtmCampaña(): ?string
    {
        return $this->utmCampaña;
    }

    public function setUtmCampaña(?string $utmCampaña): self
    {
        $this->utmCampaña = $utmCampaña;
        return $this;
    }

    public function getGclid(): ?string
    {
        return $this->gclid;
    }

    public function setGclid(?string $gclid): self
    {
        $this->gclid = $gclid;
        return $this;
    }

    public function getReferente(): ?string
    {
        return $this->referente;
    }

    public function setReferente(?string $referente): self
    {
        $this->referente = $referente;
        return $this;
    }

    public function getNumeroVisitas(): int
    {
        return $this->numeroVisitas;
    }

    public function setNumeroVisitas(int $numeroVisitas): self
    {
        $this->numeroVisitas = $numeroVisitas;
        return $this;
    }

    public function incrementarVisitas(): self
    {
        $this->numeroVisitas++;
        return $this;
    }

    /**
     * @return Collection<int, Evento>
     */
    public function getEventos(): Collection
    {
        return $this->eventos;
    }

    public function addEvento(Evento $evento): self
    {
        if (!$this->eventos->contains($evento)) {
            $this->eventos[] = $evento;
            $evento->setVisitante($this);
        }

        return $this;
    }

    public function removeEvento(Evento $evento): self
    {
        if ($this->eventos->removeElement($evento)) {
            if ($evento->getVisitante() === $this) {
                $evento->setVisitante(null);
            }
        }

        return $this;
    }
}