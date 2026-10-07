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

    public function setId(?int $id): void
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
* Values are compared with `===`: setting the same value again is not a change. Objects are compared by identity: the
  same object given as old and new value is not a change, even if modified in place. A property set back to its
  original value before the save is not updated.
* A property that never calls `propertyChanged()` is still written on `INSERT`, but its changes are never `UPDATE`d.
* A property notified by `propertyChanged()` but not mapped in the metadata is ignored by the unit of work.
* Make the `autoincrement` id nullable, `null` by default (`private ?int $id = null;`, `setId(?int $id)`): saved
  without being managed, an entity holding an id (`0` included) is taken as a stored row and updated, one with a
  `null` id is inserted (see [Saving an entity not managed](unit-of-work.md#saving-an-entity-not-managed)).
* Hydration goes through the same setters (unless the metadata says otherwise). A setter must accept every value the
  column can hold: if the column is nullable, type the parameter as nullable (`?\DateTimeImmutable` above), or
  hydration fails with a `TypeError`.

### Mutable values

A notification only happens when a setter is called. A value that can be modified **in place** escapes it:
`$event->getStartAt()->modify('+1 day')` on a `\DateTime`, or `$document->getPayload()->tags[] = 'php'` on a JSON
object (`\stdClass`), changes the entity without calling any setter.

So Ting splits the fields in two:

* **immutable** fields (scalars, `\DateTimeImmutable`, `\DateTimeZone`, enums, UUIDs, IPs, geometries, JSON decoded to
  arrays): their value can only change through the setter, they are updated **when notified**;
* **mutable** fields (`\DateTime`, JSON decoded to objects, values of a serializer of your own): no notification tells
  that they changed, so the unit of work compares them. When the entity is hydrated, it keeps the **raw value** of
  each mutable column read, as the driver returned it: nothing is serialized by a read. When it becomes managed
  otherwise (inserted, updated as an entity not managed, passed to `manage()`), it keeps the value Ting would write.
  On save, a mutable field is unchanged when its current database value is the kept one, or the kept raw value
  unserialized then serialized again (the value Ting would write back once read); it is written otherwise, or when
  its change was notified through its setter. After the `INSERT` or `UPDATE`, the value written is the kept one.

Comparing database values means that a value which does not survive a round trip is not rewritten by an unrelated
save, as long as it is not modified: the microseconds of a `DATETIME(6)` column read by a `Y-m-d H:i:s` serializer, a
time in the DST gap of the PHP timezone (read one hour later), a MySQL zero date (`0000-00-00`, read as
`-0001-11-30`), a JSON big integer (decoded to a float) or JSON written with other spaces or escapes. Once modified (in
place or through the setter), the field is written with what Ting can write: the microseconds, the zero date or the
big integer are then lost.

The kept values are packed into one string per entity, held weakly by the unit of work, for the managed entities that
have a mutable field only: they take about the memory of the raw values. Hydrating costs nothing more; saving
serializes the mutable fields as the `UPDATE` needs anyway, and unserializes a kept raw value only when it differs from
the current database value.

Change tracking never makes a read fail, nor a save of other fields: a value Ting can read but not write back (a JSON
number too large, decoded to `INF`; a document deeper than the encoding depth; a getter refusing `null`) is hydrated
and managed. As its database value cannot be computed, the field is taken as unchanged, as in 3.x: an unrelated save
leaves it alone, and a change made in place is not written. Set through its setter, it is written, and the error of
its getter or serializer is thrown. A kept raw value that cannot be unserialized again is taken as changed: the field
is written.

A mutable field is only compared, and written, when its value is **known**: read from the database, set through its
setter, or written (by the `INSERT` of the entity or an `UPDATE`, through any repository of its class). An entity read **partially** (a query selecting some of its columns,
a join selecting some columns of the joined entity) leaves the other fields with their PHP default (`null`, or a value
set by the constructor, such as `new \DateTime()`): the fields not read are left out of its `UPDATE`, so a save does
not overwrite their column with that default. Once set through its setter (with a new value), such a field is known,
and written. A value modified in place without setter, on a field not read, is not written.

The default comes from the serializer of the field: `Serializer\DateTime`, `Serializer\Json` decoding objects
(without the `assoc` unserialize option nor the `JSON_OBJECT_AS_ARRAY` flag) and any serializer of your own are mutable; the serializers shipped with Ting for immutable values
are not. A field of type `datetime` without serializer follows its property: typed `\DateTimeImmutable` (nullable or
not), it hydrates a `\DateTimeImmutable` (immutable); typed `\DateTimeInterface`, `\DateTime` or a union, not typed or
only reachable through a setter, it hydrates a `\DateTime` (mutable), as in 3.x. Override the default with the
`mutable` option of the field, see [field options](repositories.md#field-options) — `'mutable' => false` on a field
you only ever replace through its setter (a readonly value object serialized by your own serializer, for instance).

`UnitOfWork::isPropertyChanged()` tells whether a mutable field of a managed entity will be written: its change was
notified, or its database value differs from the kept one. A serializer returning an object, rather than a scalar,
makes its field always different, so always written. For a new entity or an entity not managed, see
[Inspecting the state](unit-of-work.md#inspecting-the-state).

Prefer immutable values: type your dates `\DateTimeImmutable` (not `\DateTimeInterface`), decode JSON to arrays
(`'serializer_options' => ['unserialize' => ['assoc' => true]]`), make your value objects `readonly`, and replace them
through the setter: nothing is kept nor compared for them. A mutable primary key (a `\DateTime` key, say), modified
in place or not, targets its row by its kept database value: the `UPDATE` (or `DELETE`) finds it by the value read,
microseconds included.

A clone shares the objects its properties hold with its original: a `\DateTime` modified in place on the clone is
modified on the original too. Clone such values in `__clone()`, or prefer immutable ones (see
[Clones](unit-of-work.md#clones)).

## How Ting reads and writes properties

Ting reads and writes properties with [Symfony PropertyAccess](https://symfony.com/doc/current/components/property_access.html):

* to read `name`, it calls `getName()`, `isName()` or `hasName()`, or reads the property if it is public;
* to write `name`, it calls `setName()`, or writes the property if it is public.

An array value (a `json` field decoded to arrays, for instance) is the exception: when the entity has an adder and a
remover for the property (`addTag()` / `removeTag()` for `tags`), PropertyAccess calls them **instead of**
`setTags()`, as in 3.x: it reads the current value with the getter, calls `removeTag()` for each element missing from
the new value, then `addTag()` for each element missing from the current one (the order of the new value is not kept).
The setter is not called, so these methods must call `propertyChanged()` themselves, and the property needs a default
value (`private array $tags = [];`): a typed property not initialized makes the hydration throw a
`Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException`. To have Ting call `setTags()`, declare it
with the `setter` option.

When your accessors do not follow these conventions, declare them in the metadata with the `getter` and `setter`
options (like `isCapitalCity()` / `capitalCityIs()` above): see [field options](repositories.md#field-options).

Typed properties that are not initialized (no default value and never set) are skipped: they are left out of the
`INSERT`, so the database default applies, and out of the `UPDATE`. This includes a field with a custom `getter`
that reads a property not initialized: it fails with "must not be accessed before initialization", and the field is
skipped. A getter that handles that case itself (`return $this->status ?? 'draft';`) is readable: its value is written.
Since Ting calls a custom getter to know whether it is readable, it must have no side effect.

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

The `NotifyProperty` trait stores the listeners (the unit of work) in a `$listeners` property. A listener is added
once: a clone copies the listeners of its original, and the unit of work does not add itself again when it manages
the clone. To keep them out of the way:

* `var_dump()` and other debug tools only show the entity's own properties (`__debugInfo()`);
* `serialize()` leaves them out (`__serialize()`), so an entity can be stored in a session or a cache. Every other
  property is kept, including the private properties of a parent class. An entity defining its own `__serialize()`
  must leave them out too. An unserialized entity is a new object, not managed by the unit of work: saving it updates
  every field of its row when it holds its `autoincrement` id (see
  [Saving an entity not managed](unit-of-work.md#saving-an-entity-not-managed)), unless you
  [manage it](unit-of-work.md#managing-an-entity-yourself) first, which writes its changes only.
