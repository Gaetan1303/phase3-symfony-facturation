<?php

namespace App\Entity;

use App\Enum\InvoiceStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $number;

    #[ORM\Column(enumType: InvoiceStatus::class, length: 32)]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\Column(name: 'total_ttc', type: 'decimal', precision: 10, scale: 2)]
    private string $totalTtc = '0.00';

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var Collection<int, InvoiceLine>
     */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->number = sprintf('FACT-%s-%s', $this->createdAt->format('Ymd'), substr(uniqid(), -6));
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $dt): self
    {
        $this->createdAt = $dt instanceof \DateTime ? $dt : \DateTime::createFromInterface($dt);

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalTtc(): string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): self
    {
        $this->totalTtc = $totalTtc;

        return $this;
    }

    public function getAmount(): float
    {
        return (float) $this->totalTtc;
    }

    public function setAmount(float $amount): self
    {
        $this->totalTtc = number_format($amount, 2, '.', '');

        return $this;
    }

    /**
     * Recalculate amount from lines and apply to this invoice.
     */
    public function recalculateAmount(): float
    {
        $total = 0.0;
        foreach ($this->getLines() as $line) {
            // InvoiceLine::getLineTotal() returns float
            $total += $line->getLineTotal();
        }
        $this->setAmount($total);

        return $total;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, InvoiceLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): self
    {
        if ($this->lines->removeElement($line) && $line->getInvoice() === $this) {
            $line->setInvoice(null);
        }

        return $this;
    }
}
