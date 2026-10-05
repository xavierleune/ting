# Ting documentation

Ting is a datamapper for PHP, for MySQL and PostgreSQL.

* **Simple**: no magic inside, it's basically SQL queries
* **Fast**: with no magic comes no overhead
* **Supports** MySQL and PostgreSQL

**Why another datamapper for PHP?**

There are already good datamappers for MySQL and PostgreSQL, but the best known ones are complex: a long learning curve,
a new language to query the database, or tedious XML files to describe it. Ting was created to stay simple.

Ting is not an ORM: it has no relation system between objects. But you can easily write queries with joins and get all
the objects involved in the query.

## Contents

1. [Getting started without a framework](getting-started.md)
2. [Entities](entities.md)
3. [Repositories](repositories.md)
4. [Queries](queries.md)
5. [Hydrators](hydrators.md)
6. [Unit of work](unit-of-work.md)
7. [Cache](cache.md)

With Symfony, use [ting_bundle](https://github.com/xavierleune/ting_bundle): it wires Ting in the Symfony container
and documents its configuration.

Upgrading from 3.x: see [UPGRADE-4.0.md](../UPGRADE-4.0.md).

---

This documentation is based on [ccmbenchmark/ting_documentation](https://github.com/ccmbenchmark/ting_documentation),
Copyright 2014-2018 CCM Benchmark Group, licensed under the Apache License, Version 2.0. Updated for Ting 4.0,
Copyright 2026 Xavier Leune.
