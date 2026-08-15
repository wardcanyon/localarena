<?php declare(strict_types=1);

namespace LocalArena\Test\Unit;

/**
 * Base class for LocalArena's UNIT tests.
 *
 * Unlike `IntegrationTestCase`, this does not create a table, does not
 * touch MySQL, and does not require the LocalArena Docker stack to be
 * running.  It is for framework code that can be exercised in
 * isolation: argument parsing, pure helpers, data-shape rendering, and
 * so on.
 *
 * Two reasons that lane is worth having:
 *
 * - It is fast.  There is no table to create and no database to talk
 *   to, so these tests cost milliseconds.
 *
 * - It is runnable where the integration suite is not.  The
 *   integration tests need Docker plus a running MySQL service (see
 *   CLAUDE.md); these need only a PHP interpreter, so they can be run
 *   directly -- including in environments where the Docker daemon and
 *   image registry are unavailable.
 *
 * Unit tests live in `tests/unit/` and are named `*Test.php`, so they
 * are collected by `tests/phpunit.xml` and sharded by CI exactly like
 * the integration tests; no separate wiring is needed.
 */
class UnitTestCase extends \PHPUnit\Framework\TestCase
{
}

/**
 * Resolves a path beneath the LocalArena framework sources
 * (i.e. what the repository keeps in `src/`).
 *
 * Unit tests are run in two different layouts, and this papers over
 * the difference:
 *
 * - Inside the `testenv` container, where `src/module` is mounted at
 *   `/src/localarena/module` and `APP_GAMEMODULE_PATH` has been
 *   defined (by the PHPUnit bootstrap, `IntegrationTestCase.php`).
 *
 * - Straight from a checkout, where the sources are at `<repo>/src`
 *   and no constants have been defined at all -- the case that lets
 *   these tests run without Docker.
 */
function localarenaFrameworkPath(string $relative_path): string
{
  $root = defined('APP_GAMEMODULE_PATH') ? APP_GAMEMODULE_PATH : dirname(__DIR__, 2) . '/src/';
  return rtrim($root, '/') . '/' . ltrim($relative_path, '/');
}
