<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headerH1 = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headerH2 = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headerH3 = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headerH4 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isPublished = false;

    #[ORM\OneToMany(
        mappedBy: 'post',
        targetEntity: Image::class,
        orphanRemoval: true,
        cascade: ['persist']
    )]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getHeaderH1(): ?string
    {
        return $this->headerH1;
    }

    public function setHeaderH1(?string $headerH1): self
    {
        $this->headerH1 = $headerH1;
        return $this;
    }

    public function getHeaderH2(): ?string
    {
        return $this->headerH2;
    }

    public function setHeaderH2(?string $headerH2): self
    {
        $this->headerH2 = $headerH2;
        return $this;
    }

    public function getHeaderH3(): ?string
    {
        return $this->headerH3;
    }

    public function setHeaderH3(?string $headerH3): self
    {
        $this->headerH3 = $headerH3;
        return $this;
    }

    public function getHeaderH4(): ?string
    {
        return $this->headerH4;
    }

    public function setHeaderH4(?string $headerH4): self
    {
        $this->headerH4 = $headerH4;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function isIsPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    /**
     * @return Collection<int, Image>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setPost($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getPost() === $this) {
                $image->setPost(null);
            }
        }

        return $this;
    }
}