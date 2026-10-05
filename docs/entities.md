# Entities

An entity is a plain PHP object: it does not extend any Ting class. How its properties map to the columns of a table is
described by its [repository](repositories.md), not by the entity itself.

To be saved by the [unit of work](unit-of-work.md), an entity only has to tell Ting which of its properties changed.

## Tracking changes

When an entity is persisted, Ting only updates the columns that changed since the entity was read from the database. To
know them, the entity implements the observer pattern: it implements
`CCMBenchmark\Ting\Entity\NotifyPropertyInterface` and reports every change to its listeners (the unit of work is one
of them).

The `CCMBenchmark\Ting\Entity\NotifyProperty` trait implements the interface. Call its
`propertyChanged($propertyName, $oldValue, $newValue)` method in each setter, **before** assigning the new value:

```php
<?php

namespace App\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class City implements NotifyPropertyInterface
{
    use NotifyProperty;

    private ?int $id = null;
    private string $name = '';
    private bool $capitalCity = false;
    private CityStatus $status = CityStatus::Active;
    private array $tags = [];
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->propertyChanged('id', $this->id, $id);
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->propertyChanged('name', $this->name, $name);
        $this->name = $name;
    }

    public function isCapitalCity(): bool
    {
        return $this->capitalCity;
    }

    public function capitalCityIs(bool $capitalCity): void
    {
        $this->propertyChanged('capitalCity', $this->capitalCity, $capitalCity);
        $this->capitalCity = $capitalCity;
    }

    public function getStatus(): CityStatus
    {
        return $this->status;
    }

    public function setStatus(CityStatus $status): void
    {
        $this->propertyChanged('status', $this->status, $status);
        $this->status = $status;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->propertyChanged('tags', $this->tags, $tags);
        $this->tags = $tags;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->propertyChanged('createdAt', $this->createdAt, $createdAt);
        $this->createdAt = $createdAt;
    }
}
```

```php
<?php

namespace App\Entity;

enum CityStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
```

The matching `CityRepository` is shown in [Repositories](repositories.md#defining-a-repository).

A few rules:

* The first argument of `propertyChanged()` is the **property name** (the `fieldName` of the metadata), not the column
  name.
* Values are compared with `===`: setting the same value again is not a change. Objects are compared by identity, so
  for a `DateTime` modified in place Ting sees no change; prefer immutable objects (`DateTimeImmutable`, enums...) and
  replace them.
* A property that never calls `propertyChanged()` is still written on `INSERT`, but its changes are never `UPDATE`d.
* Hydration goes through the same setters (unless the metadata says otherwise). A setter must accept every value the
  column can hold: if the column is nullable, type the parameter as nullable (`?\DateTimeImmutable` above), or
  hydration fails with a `TypeError`.

## How Ting reads and writes properties

Ting reads and writes properties with [Symfony PropertyAccess](https://symfony.com/doc/current/components/property_access.html):

* to read `name`, it calls `getName()`, `isName()` or `hasName()`, or reads the property if it is public;
* to write `name`, it calls `setName()`, or writes the property if it is public.

When your accessors do not follow these conventions, declare them in the metadata with the `getter` and `setter`
options (like `isCapitalCity()` / `capitalCityIs()` above): see [field options](repositories.md#field-options).

Typed properties that are not initialized (no default value and never set) are skipped: they are left out of the
`INSERT`, so the database default applies, and out of the `UPDATE`.

## Public properties and property hooks

Entities can expose public properties instead of getters and setters. To keep change tracking, use PHP 8.4
[property hooks](https://www.php.net/manual/en/language.oop5.property-hooks.php) to call `propertyChanged()`:

```php
<?php

namespace App\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class Country implements NotifyPropertyInterface
{
    use NotifyProperty;

    public string $code {
        set(string $value) {
            $this->propertyChanged('code', $this->code ?? null, $value);
            $this->code = $value;
        }
    }

    public string $name = '' {
        set(string $value) {
            $this->propertyChanged('name', $this->name, $value);
            $this->name = $value;
        }
    }

    public ?int $population = null {
        set(?int $value) {
            $this->propertyChanged('population', $this->population, $value);
            $this->population = $value;
        }
    }
}
```

```php
$country = $countryRepository->get('FRA');
$country->name = 'République française';
$countryRepository->save($country); // UPDATE ... SET cou_name = ... WHERE cou_code = 'FRA'
```

How Ting deals with hooks:

* **`set` hooks are bypassed when Ting writes a property** (hydration, auto-increment value after an insert): the value
  coming from the database is stored as is, without running the hook. Do not rely on a `set` hook to normalize or
  validate database values.
* **`get` hooks are applied when Ting reads a property**: the value returned by the hook is the one written to the
  database.

Without hooks, public properties are read and written directly, but changes made by assigning them are not reported:
such an entity can be inserted, but its updates are not detected.

## Entities without change tracking

An object that does not implement `NotifyPropertyInterface` can still be hydrated from the database, but the unit of
work cannot save it: `save()`, `delete()`, `pushSave()` and `pushDelete()` only accept `NotifyPropertyInterface`
objects. This is fine for read-only data. To hydrate objects without declaring metadata at all, see
`HydratorValueObject` in [Hydrators](hydrators.md#hydratorvalueobject).

## Enums, dates, JSON...

Properties can hold any PHP value: backed enums, `DateTimeImmutable`, `DateTimeZone`, arrays stored as JSON, UUIDs...
The conversion between the PHP value and the column is done by a serializer declared in the metadata, see
[Serializers](repositories.md#serializers).

## Debugging and serialization

The `NotifyProperty` trait stores the listeners (the unit of work) in a `$listeners` property. To keep it out of the
way:

* `var_dump()` and other debug tools only show the entity's own properties (`__debugInfo()`);
* `serialize()` leaves the listeners out (`__serialize()`), so an entity can be stored in a session or a cache. An
  unserialized entity is a new object, not managed by the unit of work: saving it inserts it, unless you
  [manage it](unit-of-work.md#managing-an-entity-yourself) first.
