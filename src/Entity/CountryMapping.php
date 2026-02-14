<?php

namespace App\Entity;

use App\Repository\CountryMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryMappingRepository::class)]
class CountryMapping
{
    const STATUS_PENDING = 'pending';
    const STATUS_AUTO = 'auto';
    const STATUS_MANUAL = 'manual';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->match = 0;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TourOperator $tourOperator = null;

    #[ORM\Column(length: 255)]
    private ?string $externalName = null;

    #[ORM\Column(length: 100)]
    private ?string $externalCode = null;

    #[ORM\ManyToOne]
    private ?Country $country = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column]
    private ?int $match = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTourOperator(): ?TourOperator
    {
        return $this->tourOperator;
    }

    public function setTourOperator(?TourOperator $tourOperator): static
    {
        $this->tourOperator = $tourOperator;

        return $this;
    }

    public function getExternalName(): ?string
    {
        return $this->externalName;
    }

    public function setExternalName(string $externalName): static
    {
        $this->externalName = $externalName;

        return $this;
    }

    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    public function setExternalCode(string $externalCode): static
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMatch(): ?int
    {
        return $this->match;
    }

    public function setMatch(int $match): static
    {
        $this->match = $match;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
