# Enums — Définitions et implémentation

Ce document recense les énumérations utilisées par l'application
## Enums à définir

- **ENUM_STATUS** — statut d'une facture
  - `draft` (brouillon)
  - `pending_payment` (en attente de paiement)
  - `paid` (payée)

- **ENUM_UNIT** — unité d'une ligne de facture / produit
  - `piece`
  - `hour`
  - `day`
  - `month`
  - `year`

## Recommandation d'implémentation (PHP 8.1+)

1) Créer des `backed enums` en PHP (exemples) :

```php
// src/Enum/InvoiceStatus.php
<?php
namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
}

// src/Enum/Unit.php
<?php
namespace App\Enum;

enum Unit: string
{
    case PIECE = 'piece';
    case HOUR = 'hour';
    case DAY = 'day';
    case MONTH = 'month';
    case YEAR = 'year';
}
```

2) Stockage en base (Doctrine)

- Option simple : stocker la valeur de l'enum dans une colonne `string`.

```php
// dans l'entité InvoiceItem
#[ORM\Column(type: 'string', length: 32)]
private string $unit;

// setter
public function setUnit(Unit $unit): self
{
    $this->unit = $unit->value;
    return $this;
}

// getter
public function getUnit(): Unit
{
    return Unit::from($this->unit);
}
```
