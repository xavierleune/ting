# CLAUDE.md

## Copyright and license headers (Apache 2.0)

- Never remove or alter existing `CCM Benchmark Group` (or `CCMBenchmark Group`) copyright lines, in source headers or in `NOTICE`.
- When significantly modifying an existing file, add a line below the existing copyright:
  `* Copyright (C) <year> Xavier Leune`
- New files carry only `Copyright (C) <year> Xavier Leune`, with the same Apache 2.0 header block as existing files.

## Tests

- Run the suite with `composer test` (PHPUnit, xdebug disabled: in `develop` mode it keeps the last exception alive, which delays destructors such as `Pgsql\Statement::__destruct()` past the end of a test).
- Tests live in `tests/Unit/<same path as src/Ting>/<Class>Test.php` and extend `CCMBenchmark\Ting\Tests\Support\TestCase`, which provides `assertThrows()` and `collectErrorTypes()`.
- Native functions called unqualified from `src/` (`pg_*`, `mysqli_init`, `class_exists`) are overridden with `NativeFunctionMock::override()`. A new one must be declared in `tests/Support/native_functions.php`, in the block of the namespace calling it. Overrides are reset after each test.
- Build wired Ting objects with `CCMBenchmark\Ting\Tests\Support\TingServices` (Ting has no service container).
- Use the fakes in `tests/fixtures` (`Fake\Mysqli`, `Fake\MysqliStatement`, `FakeDriver\MysqliResult::setFields()`…) rather than mocking them.
- Prefer `createStub()` when nothing is verified. `#[AllowMockObjectsWithoutExpectations]` is only for tests that need a partial mock without expectations (PHPUnit 12 has no partial stub).
- The suite must stay compatible with PHPUnit 11.5 (PHP 8.2) and 12.
