# Notes for Claude (and other agents)

## Running the test suite

The suite has two lanes, which differ in what they need to run:

- **Integration tests** (`tests/*Test.php`) create a real table in a
  real database, and so require Docker: a `testenv` image plus a
  running MySQL `db` service on the `localarena_default` network (see
  `Gruntfile.cjs`'s `shell:test` task and `compose.yaml`). Locally,
  they are normally run with `grunt test` (which builds the `testenv`
  image and runs `phpunit` against `tests/phpunit.xml`).

- **Unit tests** (`tests/unit/*Test.php`) exercise framework code in
  isolation -- no table, no database, no Docker. They need only a PHP
  interpreter and PHPUnit. See `tests/unit/UnitTestCase.php`.

Both lanes are collected by `tests/phpunit.xml` and sharded by CI
together, so `grunt test` runs everything.

### Claude Code Remote (web) sessions

The guidance below applies **only** when running in a Claude Code Remote
execution environment (e.g. a Claude Code on the web session), where the
Docker daemon and image registry are **not** reliably available (image
pulls are blocked by the network policy), so the **integration** lane
generally **cannot be run in the sandbox**.

For integration tests, **do not** rely on running the tests in the
sandbox. Instead, push the branch, wait for a PR to be opened, and
examine the **CI** state for the test results. Investigate and address
CI failures from there.

The **unit** lane, however, usually *can* be run in the sandbox, since
it needs no Docker -- install PHPUnit somewhere outside the repository
(e.g. `composer require --dev phpunit/phpunit` in a scratch directory)
and point it at the files:

```
$ phpunit --no-configuration tests/unit/
```

Prefer doing that over pushing blind: it is much faster than a CI
round-trip, and any framework logic that can be tested this way should
be.

If you are running locally (not in a remote/web session) with a working
Docker setup, just run the whole suite directly with `grunt test`.

## Code coverage

The `testenv` image has the PCOV coverage driver installed but **disabled
by default** (`pcov.enabled=0`), so it adds zero overhead to normal runs.
Enable it per-invocation with `php -d pcov.enabled=1` plus a PHPUnit
`--coverage-*` option. See the "Generating code-coverage reports" section
of `README.md` for the full command. Coverage is a local-only tool and is
not wired into CI.
