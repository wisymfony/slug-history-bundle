<?php

namespace Wisoft\SlugHistoryBundle\Entity;

use Wisoft\SlugHistoryBundle\Repository\WsSlugHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WsSlugHistoryRepository::class)]
#[ORM\Index(fields: ['oldPathKey'], name: "ws_slug_history_key_index")]
#[ORM\HasLifecycleCallbacks()]
class WsSlugHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $oldPathKey = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $oldPath = null;

    #[ORM\Column(length: 255)]
    private ?string $newPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(nullable: false)]
    private ?DateTimeImmutable $lastUpdatedAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->lastUpdatedAt = new DateTimeImmutable();
        $this->oldPathKey = md5($this->oldPath);
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->lastUpdatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOldPathKey(): ?string
    {
        return $this->oldPathKey;
    }

    public function setOldPathKey(string $oldPathKey): self
    {
        $this->oldPathKey = $oldPathKey;

        return $this;
    }

    public function getOldPath(): ?string
    {
        return $this->oldPath;
    }

    public function setOldPath(string $oldPath): self
    {
        $this->oldPath = $oldPath;

        return $this;
    }

    public function getNewPath(): ?string
    {
        return $this->newPath;
    }

    public function setNewPath(string $newPath): self
    {
        $this->newPath = $newPath;

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function getLastUpdatedAt(): ?DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(DateTimeImmutable $lastUpdatedAt): self
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }
}
