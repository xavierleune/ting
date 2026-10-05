# Unit of work

The unit of work (`CCMBenchmark\Ting\UnitOfWork`) tracks changes made to [entities](entities.md) and writes them to the
database: `INSERT` for new entities, `UPDATE` of the changed columns for managed ones, `DELETE` for removed ones.

One instance is shared by the whole application: it is the one given to the `RepositoryFactory` and to the hydrators
(see [Getting started](getting-started.md#wiring-ting)).

## Managed entities

An entity is **managed** when the unit of work listens to its changes. Entities hydrated from the database (by
`get()`, `getBy()`, a query...) are managed automatically, provided they implement `NotifyPropertyInterface`. A new
entity becomes managed once it has been inserted.

Managed entities are stored in a `WeakMap`: the unit of work does not keep them alive. Once your code drops its last
reference to an entity, it is freed, and forgotten by the unit of work.

## Saving and deleting

Changes are queued with `pushSave()` and `pushDelete()`, then written by `process()`:

```php
$unitOfWork->pushSave($city);    // managed: UPDATE of the changed columns
$unitOfWork->pushSave($newCity); // not managed: INSERT
$unitOfWork->pushDelete($oldCity);
$unitOfWork->process();
```

`pushSave()` and `pushDelete()` return the unit of work, so calls can be chained:

```php
$unitOfWork->pushSave($city)->pushDelete($oldCity)->process();
```

`process()` writes the queued entities in the order they were pushed:

* **new entity**: every mapped property is inserted, except the `autoincrement` primary key and uninitialized typed
  properties. The generated key is then set on the entity (through its setter, see
  [field options](repositories.md#field-options)) and the entity becomes managed.
* **managed entity**: only the properties reported by `propertyChanged()` since the last write are updated. If nothing
  changed, no query is sent.
* **deleted entity**: it is deleted by its primary key, then detached.

Use `pushSave()` both to insert and to update: the unit of work knows which one applies.

`Repository::save($entity)` and `Repository::delete($entity)` are shortcuts for `pushSave($entity)->process()` and
`pushDelete($entity)->process()`. Note that they process the **whole** queue, including entities pushed earlier.

If a query fails, `process()` throws (`CCMBenchmark\Ting\Driver\QueryException`...). Entities written before the failure
are removed from the queue, the others stay queued.

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
    $cityRepository->commit();
} catch (\Throwable $e) {
    $cityRepository->rollback();
    $unitOfWork->detachAll();
    throw $e;
}
```

## Inspecting the state

| Method                                                              | Returns `true` when                                        |
|---------------------------------------------------------------------|------------------------------------------------------------|
| `isManaged(NotifyPropertyInterface $entity)`                        | the entity is managed or queued                            |
| `isNew(NotifyPropertyInterface $entity)`                            | the entity is queued for an `INSERT`                       |
| `shouldBePersisted(NotifyPropertyInterface $entity)`                | the entity is queued (save or delete)                      |
| `shouldBeRemoved(NotifyPropertyInterface $entity)`                  | the entity is queued for a `DELETE`                        |
| `isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName)` | the property changed and has not been written yet  |

## Managing an entity yourself

`manage()` starts tracking an entity that was not hydrated by Ting, for instance one rebuilt from a cache or a
session. The entity must already exist in the database, and its primary key must be set: the next `pushSave()` will
`UPDATE` the properties changed after the call to `manage()`.

```php
$city = unserialize($cachedCity); // a City read earlier, with its id
$unitOfWork->manage($city);

$city->setName('Lyon');
$unitOfWork->pushSave($city)->process(); // UPDATE t_city_cit SET cit_name = 'Lyon' WHERE cit_id = ...
```

## Detaching entities

`detach()` stops tracking an entity: it is no longer managed, its pending changes are forgotten and it is removed from
the queue. Pushing it again with `pushSave()` would insert it. `detachAll()` does the same for every entity.

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
