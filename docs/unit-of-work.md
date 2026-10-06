# Unit of work

The unit of work (`CCMBenchmark\Ting\UnitOfWork`) tracks changes made to [entities](entities.md) and writes them to the
database: `INSERT` for new entities, `UPDATE` of the changed columns for managed ones, `DELETE` for removed ones.
An entity not managed whose generated id is set is an existing row: `UPDATE` of every column.

One instance is shared by the whole application: it is the one given to the `RepositoryFactory` and to the hydrators
(see [Getting started](getting-started.md#wiring-ting)).

## Managed entities

An entity is **managed** when the unit of work listens to its changes. Entities hydrated from the database (by
`get()`, `getBy()`, a query...) are managed automatically, provided they implement `NotifyPropertyInterface`. A new
entity becomes managed once it has been inserted, an entity not managed with its generated id once it has been
updated (see [Saving an entity not managed](#saving-an-entity-not-managed)).

Managed entities are stored in a `WeakMap`: the unit of work does not keep them alive. Once your code drops its last
reference to an entity, it is freed, and forgotten by the unit of work.

The unit of work keeps no copy of the values of the managed entities: changes are notified by the entities, and the
[mutable fields](entities.md#mutable-values) are written on every save. For an entity read partially, only the names
of the mutable fields left out of the query are kept, so that their default value is not written. The only copied
value is that of a mutable primary key:
its database value is kept (in a `WeakMap` too) when the entity becomes managed (hydration, `manage()`, after its
`INSERT`) and refreshed by each `UPDATE`, so that a key modified in place still targets its row. It is dropped with the
entity, by `detach()`, `detachAll()` and `reset()`.

## Saving and deleting

Changes are queued with `pushSave()` and `pushDelete()`, then written by `process()`:

```php
$unitOfWork->pushSave($city);    // managed: UPDATE of the changed columns and the mutable ones
$unitOfWork->pushSave($newCity); // not managed, without id: INSERT
$unitOfWork->pushSave($copy);    // not managed, with its autoincrement id: UPDATE of every column
$unitOfWork->pushDelete($oldCity);
$unitOfWork->process();
```

`pushSave()` and `pushDelete()` return the unit of work, so calls can be chained:

```php
$unitOfWork->pushSave($city)->pushDelete($oldCity)->process();
```

`process()` writes the queued entities in the order they were pushed:

* **new entity**: every mapped property is inserted, except the `autoincrement` primary key and uninitialized typed
  properties. The generated key is then set on the entity, replacing any value set before (through its setter, see
  [field options](repositories.md#field-options)) and the entity becomes managed. It becomes managed as soon as the
  `INSERT` has run: if reading or setting the generated key fails (a setter that throws, say), the error is rethrown
  but the entity is not inserted again by its next save.
* **entity not managed whose `autoincrement` primary key is set** (a clone of a managed entity, a detached one, one
  read from a cache, one built with its id): it is an existing row, updated by its primary key with every readable
  mapped property (uninitialized typed properties are left out), then managed. See
  [Saving an entity not managed](#saving-an-entity-not-managed).
* **managed entity**: the properties reported by `propertyChanged()` since the last write (and not set back to their
  old value) are updated, together with every [mutable field](entities.md#mutable-values) (a `\DateTime`, a JSON
  object...), whether it changed or not, except a mutable field left out of a partial read and not set since. If the
  entity has no mutable field and nothing changed, no query is sent; with a mutable field, an `UPDATE` always runs. An immutable property modified without calling its setter is not
  reported, so it is not updated: see [entities](entities.md#tracking-changes).
* **deleted entity**: it is deleted by its primary key, then detached. An entity that was never inserted (still queued
  for its `INSERT`, or not managed and without primary key) has no row: `pushDelete()` only removes it from the queue,
  no query is sent. An entity not managed but whose primary key is set is deleted by that key; it does not become
  managed: if its `DELETE` fails, or a `pushSave()` replaces the deletion, saving it saves it as an entity not
  managed. Once deleted, an entity keeps its primary key, but saving it runs an `INSERT` (with a new generated id).

Use `pushSave()` both to insert and to update: the unit of work knows which one applies.

`Repository::save($entity)` and `Repository::delete($entity)` are shortcuts for `pushSave($entity)->process()` and
`pushDelete($entity)->process()`. Note that they process the **whole** queue, including entities pushed earlier.

If a query fails, `process()` closes the statements it prepared and rethrows the exception
(`CCMBenchmark\Ting\Driver\QueryException`...). Entities written before the failure are done, the failing one is
removed from the queue (its tracked changes are kept, so saving it again retries) and the following ones stay queued.

### Transactions

The unit of work does not open transactions. Wrap `process()` in a transaction started on a repository (see
[Transactions](repositories.md#transactions)) when the writes must succeed or fail together:

```php
$cityRepository->startTransaction();
try {
    $unitOfWork
        ->pushSave($city)
        ->pushSave($newCity)
        ->pushDelete($oldCity)
        ->process();
} catch (\Throwable $e) {
    $cityRepository->rollback();
    $unitOfWork->detachAll();
    throw $e;
}
$cityRepository->commit();
```

`commit()` stays outside the `try`: a failed `commit()` throws a `CCMBenchmark\Ting\Exceptions\TransactionException`
and leaves no transaction open, so a `rollback()` after it would throw too and hide the original error (see
[Transactions](repositories.md#transactions)).

## Inspecting the state

| Method                                                              | Returns `true` when                                        |
|---------------------------------------------------------------------|------------------------------------------------------------|
| `isManaged(NotifyPropertyInterface $entity)`                        | the entity is managed: hydrated by Ting, inserted, updated while not managed, or passed to `manage()`, and not detached since. An entity not managed queued by `pushSave()` is not managed until its `INSERT` or `UPDATE` has run, and `pushDelete()` of an entity not managed does not make it managed |
| `isNew(NotifyPropertyInterface $entity)`                            | the entity is queued for an `INSERT` (`false` for an entity not managed queued for the `UPDATE` of its row) |
| `shouldBePersisted(NotifyPropertyInterface $entity)`                | the entity is queued (save or delete)                      |
| `shouldBeRemoved(NotifyPropertyInterface $entity)`                  | the entity is queued for a `DELETE`                        |
| `isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName)` | the property will be written by the next save: its change was notified, or it is a mutable field of a managed entity, not left out of a partial read |

## Managing an entity yourself

`manage()` starts tracking an entity that was not hydrated by Ting, for instance one rebuilt from a cache or a
session. The entity must already exist in the database, and its primary key must be set: the next `pushSave()` will
`UPDATE` the properties changed after the call to `manage()`, and the mutable fields.

```php
$city = unserialize($cachedCity); // a City read earlier, with its id
$unitOfWork->manage($city);

$city->setName('Lyon');
$unitOfWork->pushSave($city)->process(); // UPDATE t_city_cit SET cit_name = 'Lyon' WHERE cit_id = ...
```

## Saving an entity not managed

The unit of work knows the rows of the entities it manages only. An entity it does not manage is a new row, unless its
metadata has an `autoincrement` primary key (with or without `sequenceName`) and the whole primary key is set: an
`INSERT` never writes the generated key, so such an entity can only come from an existing row. This is the case of a
clone of a managed entity (a clone is not managed), a detached entity, an entity read from a cache or a session, or one
built by hand with its id:

```php
$copy = clone $city;              // $city is managed, $copy is not
$copy->setName('Lyon');
$unitOfWork->pushSave($copy)->process();
// UPDATE t_city_cit SET cit_name = 'Lyon', cit_zip = ..., ... WHERE cit_id = <id of $city>
```

Its changes are not tracked, so every readable mapped property is written (the primary key is in the `WHERE` clause;
uninitialized typed properties are left out). Then the entity is managed, as if just read: its next saves write its
changes only. If the `UPDATE` fails, the entity stays not managed and can be saved again. To write only the properties
changed, call [`manage()`](#managing-an-entity-yourself) before changing them.

Limits:

* **To duplicate a row**, clone the entity, then set its `autoincrement` property to `null` (or unset it) before saving
  the copy: it is inserted, with a new generated id.
* **A copy of an entity read partially** (a `SELECT` of some columns, a join selecting some columns): the columns not
  read are recorded for the entity read only, so the copy writes its properties not read with their current value
  (`NULL`, or a default set by the constructor). Read the entity whole before copying it, or save the original (or
  detach it, then save it) instead.
* **Entities without `autoincrement` primary key** (natural or composite keys) are always inserted when not managed:
  saving a copy of a stored one fails on the duplicate key. `manage()` them first to update their row.
* **An id that does not exist in the database**: the `UPDATE` changes no row, and no error is raised (the number of
  affected rows can't tell: MySQL reports 0 as well when the values written are unchanged).
* An entity deleted by `process()` keeps its id, but saving it inserts it again, with a new generated id.

## Detaching entities

`detach()` stops tracking an entity: it is no longer managed, its pending changes are forgotten and it is removed from
the queue. The changes made while it is detached are not tracked, even if it is managed again later. Pushing it again
with `pushSave()` saves it as [an entity not managed](#saving-an-entity-not-managed): with its `autoincrement` id set,
its row is updated with every column. `detachAll()` does the same for every entity.

Entities are held weakly, but queued entities are not: an entity passed to `pushSave()` or `pushDelete()` stays in
memory until it is processed or detached. In a batch handling many entities, call `process()` regularly instead of once
at the end, and detach the entities you no longer need:

```php
foreach ($rows as $i => $row) {
    $city = new City();
    $city->setName($row['name']);
    $unitOfWork->pushSave($city);

    if ($i % 500 === 499) {
        $unitOfWork->process();
        $unitOfWork->detachAll();
    }
}
$unitOfWork->process();
$unitOfWork->detachAll();
```

## Worker mode

In a long-running process (FrankenPHP or RoadRunner workers, Swoole, Symfony Messenger consumers...), the same objects
serve many requests or messages. State left by one of them must not leak into the next: entities still managed or
queued, open connections, a transaction left open...

`ConnectionPool`, `UnitOfWork` and `Repository` implement `CCMBenchmark\Ting\ResetInterface`, whose `reset(): void`
method restores a clean state:

* `UnitOfWork::reset()` detaches every entity and forgets the prepared statements to close;
* `ConnectionPool::reset()` closes every connection; they are opened again on the next query;
* `Repository::reset()` resets the unit of work and its connection.

Call them once the request or message has been handled:

```php
use CCMBenchmark\Ting\ResetInterface;

/** @var list<ResetInterface> $resettables */
$resettables = [$unitOfWork, $connectionPool];

while ($message = $queue->next()) {
    try {
        $handler->handle($message); // uses repositories, the unit of work...
    } finally {
        foreach ($resettables as $service) {
            $service->reset();
        }
    }
}
```

Repositories created by `RepositoryFactory::get()` for each request need no reset. If your container shares repository
instances between requests, reset them too. With Symfony, [ting_bundle](https://github.com/xavierleune/ting_bundle)
integrates Ting with the framework.

Closing the connections after each request costs a reconnection on the next one. If you prefer to keep them open, reset
the unit of work only, and check the connections with `Repository::ping()` / `pingPrimary()` (see
[Repositories](repositories.md#other-methods)).
