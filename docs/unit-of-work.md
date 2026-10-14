# Unit of work

The unit of work (`CCMBenchmark\Ting\UnitOfWork`) tracks changes made to [entities](entities.md) and writes them to the
database: `INSERT` for new entities, `UPDATE` of the changed columns for managed ones, `DELETE` for removed ones.

One instance is shared by the whole application: it is the one given to the `RepositoryFactory` and to the hydrators
(see [Getting started](getting-started.md#wiring-ting)).

## Managed entities

An entity is **managed** when the unit of work listens to its changes. Entities hydrated from the database (by
`get()`, `getBy()`, a query...) are managed automatically, provided they implement `NotifyPropertyInterface`. An entity
not managed becomes managed once it has been saved (see [Saving an entity not managed](#saving-an-entity-not-managed)).

Managed entities are stored in a `WeakMap`: the unit of work does not keep them alive. Once your code drops its last
reference to an entity, it is freed, and forgotten by the unit of work.

Changes are notified by the entities. Besides them, the unit of work keeps, per managed entity (in `WeakMap`s too):

* the metadata it was read with (those of the repository or of the alias that hydrated it), or written with when it
  became managed by its save. When several repositories map the same class (a full entity and a lighter projection),
  an entity read through the projection and saved through the full repository does not write the fields the
  projection does not have: their value was never read (a default set by the constructor, say). They are written
  only once known: set through their setter, or written (by its `INSERT` or an `UPDATE`).
  Register every metadata before saving entities (`batchLoadMetadata()` or `batchLoadMetadataFromCache()` at boot):
  after a save, the changes of the properties no registered metadata of the class maps are forgotten for good, so a
  repository whose metadata are registered later would not find them;
* the names of the fields left out of a partial read (a `SELECT` of some columns, a join), so that their default value
  is not written;
* the database values of its [mutable fields](entities.md#mutable-values) (a `\DateTime`, a JSON object...), which can
  change without notification: the raw value read, as the driver returned it, then the value last written. A mutable
  field is written only when its database value changes, and a mutable primary key targets its row by the value read.

All of them are dropped with the entity, by `detach()`, `detachAll()` and `reset()`.

## Saving and deleting

Changes are queued with `pushSave()` and `pushDelete()`, then written by `process()`:

```php
$unitOfWork->pushSave($city);    // managed: UPDATE of the changed columns
$unitOfWork->pushSave($newCity); // not managed, id null: INSERT
$unitOfWork->pushSave($cached);  // not managed, with its autoincrement id: UPDATE of its row
$unitOfWork->pushDelete($oldCity);
$unitOfWork->process();
```

`pushSave()` and `pushDelete()` return the unit of work, so calls can be chained:

```php
$unitOfWork->pushSave($city)->pushDelete($oldCity)->process();
```

`process()` writes the queued entities in the order they were pushed:

* **managed entity**: the properties reported by `propertyChanged()` since the last write (and not set back to their
  old value) are updated, together with the [mutable fields](entities.md#mutable-values) (a `\DateTime`, a JSON
  object...) whose database value differs from the one read or last written, except a field left out of a partial
  read and not set since. If nothing changed, no query is sent. An immutable property modified without calling its
  setter is not reported, so it is not updated: see [entities](entities.md#tracking-changes). Only the changes written
  are forgotten: saved through a lighter projection, the changes of the fields it does not map wait for a save through
  a repository mapping them.
* **entity not managed**: updated by its primary key when it holds its `autoincrement` id, inserted otherwise. See
  [Saving an entity not managed](#saving-an-entity-not-managed).
* **deleted entity**: it is deleted by its primary key, then detached. An entity that was never inserted (still queued
  for its `INSERT`, not managed and without primary key, or whose `INSERT` was [rolled back](#transactions)) has no row:
  `pushDelete()` only removes it from the queue, no query is sent. An entity not managed but whose primary key is set
  is deleted by that key; it does not become managed. Once deleted, an entity is detached but keeps its primary key:
  saving it again runs an `UPDATE` that matches no row. Set its `autoincrement` property to `null` to insert it again
  (with a new generated id).

Use `pushSave()` both to insert and to update: the unit of work knows which one applies.

`Repository::save($entity)` and `Repository::delete($entity)` are shortcuts for `pushSave($entity)->process()` and
`pushDelete($entity)->process()`, writing with the metadata of the repository. Note that they process the **whole**
queue, including entities pushed earlier.

If a query fails, `process()` closes the statements it prepared and rethrows the exception
(`CCMBenchmark\Ting\Driver\QueryException`...). Entities written before the failure are done, the failing one is
removed from the queue (its tracked changes are kept, so saving it again retries) and the following ones stay queued,
in their order: the next `process()` writes them, then the entities pushed since. See
[Retrying a failed batch](#retrying-a-failed-batch).

### Retrying a failed batch

An entity still queued keeps its place when it is pushed again: pushing a failed batch again as it is writes the
entities left queued **before** the ones pushed ahead of them in the batch (a child before its parent). To retry a
batch in its order:

1. call `detachAll()`: the queue is emptied, and no entity is managed any more. Outside a transaction, before calling
   it, check whether the failing entity, if it was to be inserted, is managed (`isManaged()`): its `INSERT` then ran,
   and only reading or setting its generated key failed (note whether it holds its id, see step 3);
2. then read again every entity of the batch whose row existed before the batch (read from the database, or built by
   hand and saved earlier), and apply your changes to the fresh objects; only the entities whose row did not exist
   before the batch are pushed again as they are. Read them after `detachAll()`, not before: it would detach the fresh
   objects too. A detached entity is saved as an [entity not managed](#saving-an-entity-not-managed): by an `INSERT`
   when it holds no `autoincrement` id, which fails on a duplicate key when its row exists, and otherwise by an
   `UPDATE` of every field, which writes its default values over the columns a partial read left out;
3. correct the failing entity, and push the entities again in the order of the batch:
   * after a rollback (the batch ran in a [transaction](#transactions) rolled back), nothing written before the
     failure is kept: push the whole batch. The entities inserted in the transaction are inserted again;
   * outside a transaction, the writes done before the failure are kept: push the failing entity and the ones after
     it, and leave out the entities written before the failure. A failing entity found managed at step 1 has its row
     inserted:
     * if it holds its generated id (its setter set it, then threw), push it again: detached, it is saved by an
       `UPDATE` of every field, which writes the changes its setter made (kept, see
       [Saving an entity not managed](#saving-an-entity-not-managed)). Left out, those changes are lost;
     * if its id is still `null` (reading the generated key failed, or its setter threw before setting it), leave it
       out: pushed again, it would be inserted a second time.

### Saving an entity not managed

An entity built by hand, detached, read from a cache or a session, or a clone, is not managed: nothing tells which of
its fields changed. `pushSave()` decides by its `autoincrement` primary key:

* its metadata have an `autoincrement` field and that property holds a value other than `null`: the entity is a row
  already stored. It is updated by its primary key with **every** readable mapped field (the primary key is in the
  `WHERE` clause, uninitialized typed properties are left out), then managed: its next saves write its changes only.
  `isNew()` is `false`;
* otherwise (no `autoincrement` field, as for a natural key, the property `null` or not initialized, or no
  property named after the `autoincrement` field, reached through `getter` / `setter` options only): the entity is
  new. Every mapped property is inserted, except the `autoincrement` primary key and uninitialized typed
  properties. The generated key is then set on the entity (through its setter, see
  [field options](repositories.md#field-options)) and the entity becomes managed. It becomes managed as soon as the
  `INSERT` has run: if reading or setting the generated key fails (a setter that throws, say), the error is rethrown
  but the entity is not inserted again by its next save. `isNew()` is `true`. Setting the generated key is not a
  change, but a change its setter makes to another mapped field (a slug named after the id, say) was not inserted: it
  is written by the next save of the entity, as any change (`isPropertyChanged()` reports it). A change made by a
  setter that throws is kept too once the setter has set the id; when it throws before setting the id, its changes
  are not kept: the entity holds no id of its new row to update it by. The id counts as set when it is not `null` and
  differs from the one held before the `INSERT`, or equals the generated one: an id kept from an `INSERT` rolled back
  is not the id of the new row, and a typed property not initialized holds no id, whatever its getter returns (an id
  reached through its getter only, with no property named after it, is read through it, before and after). Retry as
  described in [Retrying a failed batch](#retrying-a-failed-batch).

The `autoincrement` property is read directly, not through its getter, which may refuse an id not generated yet. It is
read again by `process()`: set to `null` between `pushSave()` and `process()`, the entity is inserted. If the `UPDATE`
fails, the entity stays not managed and can be saved again. The metadata given to `Repository::save()` are the ones
used: the row updated or inserted is the one of the table of that repository.

```php
$city = new City();
$city->setId(42);
$city->setName('Lyon');
$cityRepository->save($city);
// UPDATE t_city_cit SET cit_name = 'Lyon', cit_zip = NULL, ... WHERE cit_id = 42
```

Every field is written, so these entities must hold the value of every field:

* **an id defaulting to `0`** (`private int $id = 0;`) is an id: the entity is updated `WHERE id = 0`, nothing is
  inserted. Declare the id nullable: `private ?int $id = null;`, with `setId(?int $id)`;
* **a deleted entity** keeps its id: saving it again updates no row (see above);
* **an entity read partially** (a `SELECT` of some columns, a lighter projection), then detached and saved, writes its
  default values over the columns it did not read; so does **an entity restored from a cache or a session** whose
  `__sleep()` left properties out, and **a clone of an entity read partially**. Read the whole row first, or
  [manage](#managing-an-entity-yourself) such an entity before changing it: only its changes are then written.

#### Clones

A clone is not managed: `clone` copies the properties of an entity, not its tracking by the unit of work. Holding the
id of its original, it updates the row of its original with every field, as any entity not managed:

```php
$copy = clone $city;              // $city is managed, $copy is not
$copy->setName('Lyon');
$unitOfWork->pushSave($copy)->process();
// UPDATE t_city_cit SET cit_name = 'Lyon', cit_zip = ..., ... WHERE cit_id = <id of $city>
```

Then the clone is managed too: both objects track the same row, each with its own changes. A clone with another
natural key (not `autoincrement`) is inserted.

**To duplicate a row**, clone the entity and set its `autoincrement` property to `null` before saving the clone: it is
inserted, and gets its own id. The entity must accept `null` as id (`setId(?int $id)`), or reset it in `__clone()`:

```php
$copy = clone $city;
$copy->setId(null);
$cityRepository->save($copy); // INSERT, $copy->getId() is the new id

// or, once for all the clones of the class:
public function __clone()
{
    $this->id = null;
}
```

As anywhere in PHP, a clone shares the objects its properties hold with its original (a shallow copy): a `\DateTime`
modified in place on the clone is modified on the original too, and written by the next save of both. Clone the mutable
objects in `__clone()` (`$this->createdAt = clone $this->createdAt;`), or use immutable values
(`\DateTimeImmutable`, arrays).

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
    throw $e;
}
$cityRepository->commit();
```

`commit()` stays outside the `try`: a failed `commit()` throws a `CCMBenchmark\Ting\Exceptions\TransactionException`
and leaves no transaction open, so a `rollback()` after it would throw too and hide the original error (see
[Transactions](repositories.md#transactions)).

After a rollback, the rows written in the transaction no longer exist, or no longer hold the values written:

* **the entities inserted** in the transaction keep the id generated by their rolled back `INSERT`. The driver tells
  the unit of work, which stops managing them (without modifying them) and remembers that their row was rolled back:
  pushed again as they are, they are inserted again (`isNew()` is `true`), and their id is replaced by the newly
  generated one. This holds whatever ended the transaction: `rollback()`, a failed `commit()` (or a PostgreSQL
  `COMMIT` of an aborted transaction), or the connection lost or closed (`ping()` reconnecting, `close()`,
  `ConnectionPool::reset()`). It survives `detach()`, `detachAll()` and `reset()`: the retry can push the same objects.
  A queued save of such an entity becomes an `INSERT`, a queued deletion is dropped. Only the id of the entity itself
  is replaced: an id generated in the rolled back transaction and copied into another entity
  (`$ticket->setEventId($event->getId())`) keeps the old value, so the retry would write a reference to a row that
  does not exist. Run the code copying the generated ids again in each attempt, after the save generating them;
* **the entities updated** in the transaction stay managed, but their changes are forgotten (written, then rolled
  back): their objects no longer match the database. Pushed again as they are, they **write nothing**: no change is
  left, and setting the same value again is not a change. To retry (after a deadlock, say), read them again and apply
  your changes to the fresh objects (for a batch, see [Retrying a failed batch](#retrying-a-failed-batch));
* **the entities read** in the transaction stay managed too, with the values they were read with: a row inserted or
  updated in the transaction and read again (another object than the one saved, a hydrator result) no longer exists,
  or no longer holds those values. Only the objects inserted by the unit of work are told of the rollback: an object
  read again for a row inserted in the transaction keeps its id, and saving it runs an `UPDATE` that matches no row,
  silently. Read again, after the rollback, the entities read during a transaction that does not commit.

A `commit()` failing because the connection was lost (MySQL "server has gone away" or "lost connection", a broken
PostgreSQL connection) has an **unknown outcome**: the server may have committed before its reply was lost. Ting
treats it as a rollback, so a retry inserts the entities of the transaction again, which creates duplicate rows if the
server had committed. After such a failure, check the rows before retrying (or rely on a natural or unique key that
rejects the duplicate).

The unit of work learns that a transaction ended only from `commit()` and `rollback()`: a transaction ended by another
statement (an implicit commit of MySQL DDL or `LOCK TABLES`, a `COMMIT` or `ROLLBACK` sent as a query), or a MySQL
deadlock followed by `commit()` instead of `rollback()`, leaves it with entities inserted twice or managed without a
row. See [Transactions](repositories.md#transactions).

This protection needs a driver implementing `CCMBenchmark\Ting\Driver\RollbackListenerAwareDriverInterface`, as the
Mysqli and Pgsql drivers do. With a driver that does not implement it (a driver of your own), an entity inserted in a
rolled back transaction stays managed, as if its row existed: saved again, it writes nothing. `detach()` it (or
`detachAll()`), then set its `autoincrement` property back to `null` before saving it again: it is then inserted.

## Inspecting the state

| Method                                                              | Returns `true` when                                        |
|---------------------------------------------------------------------|------------------------------------------------------------|
| `isManaged(NotifyPropertyInterface $entity)`                        | the entity is managed: hydrated by Ting, saved, or passed to `manage()`, and not detached (nor its `INSERT` rolled back) since. An entity not managed queued by `pushSave()` is not managed until its `INSERT` or `UPDATE` has run, and `pushDelete()` of an entity not managed does not make it managed |
| `isNew(NotifyPropertyInterface $entity)`                            | the entity is queued for an `INSERT`: not managed, without `autoincrement` id (or whose `INSERT` was rolled back). `false` for an entity not managed queued for the `UPDATE` of its row |
| `shouldBePersisted(NotifyPropertyInterface $entity)`                | the entity is queued (save or delete)                      |
| `shouldBeRemoved(NotifyPropertyInterface $entity)`                  | the entity is queued for a `DELETE`                        |
| `isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName)` | the property will be written by the next save of a managed entity: its change was notified, or it is a mutable field, not left out of the read, whose database value differs from the one read or last written |

`isPropertyChanged()` is about managed entities. For an entity not managed (new, detached...), it is `false`: its
changes are not recorded. Saved, a new entity is inserted whole, and an entity not managed holding its `autoincrement`
id is updated with every field.

## Managing an entity yourself

`manage()` starts tracking an entity that was not hydrated by Ting, for instance one rebuilt from a cache or a
session. The entity must already exist in the database, and its primary key must be set: the next `pushSave()` will
`UPDATE` the properties changed after the call to `manage()` only: the current values of its mutable fields are taken
as the stored ones (a field whose getter or serializer throws is taken as unchanged: written only once set through its setter).
Its metadata are those registered for its class, unless you give others: `manage($entity, $metadata)`.

```php
$city = unserialize($cachedCity); // a City read earlier, with its id
$unitOfWork->manage($city);

$city->setName('Lyon');
$unitOfWork->pushSave($city)->process(); // UPDATE t_city_cit SET cit_name = 'Lyon' WHERE cit_id = ...
```

Without `manage()`, saving it would update every field (see
[Saving an entity not managed](#saving-an-entity-not-managed)).

## Detaching entities

`detach()` stops tracking an entity: it is no longer managed, its pending changes are forgotten and it is removed from
the queue. The changes made while it is detached are not tracked, even if it is managed again later. Pushing it again
with `pushSave()` saves it as an [entity not managed](#saving-an-entity-not-managed): when its `autoincrement`
property holds its id, every field is updated; when its primary key has no `autoincrement` column (a natural key),
it is inserted, which fails on a duplicate key when its row exists. To update its changes only,
[manage it](#managing-an-entity-yourself) again before changing it. `detachAll()` does the same for every entity.

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

* `UnitOfWork::reset()` detaches every entity and forgets the prepared statements to close (it still knows the
  entities whose `INSERT` was rolled back, whatever the order of the resets);
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
integrates Ting with the framework. A hydrator kept as a service needs no reset either: its
[identity map](hydrators.md#identity-map) only lives for one result.

Closing the connections after each request costs a reconnection on the next one. If you prefer to keep them open, reset
the unit of work only, and check the connections with `Repository::ping()` / `pingPrimary()` (see
[Repositories](repositories.md#other-methods)).
