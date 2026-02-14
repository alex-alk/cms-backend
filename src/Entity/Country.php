<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;

//todo: cc sa fie unic
#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2, unique: true)]
    private ?string $cc = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $nameRo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCc(): ?string
    {
        return $this->cc;
    }

    public function setCc(string $cc): static
    {
        $this->cc = $cc;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameRo(): ?string
    {
        return $this->nameRo;
    }

    public function setNameRo(string $nameRo): static
    {
        $this->nameRo = $nameRo;

        return $this;
    }
}
