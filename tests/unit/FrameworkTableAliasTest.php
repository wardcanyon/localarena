<?php declare(strict_types=1);

namespace LocalArena\Test\Unit;

require_once __DIR__ . '/UnitTestCase.php';

localarenaDefineFrameworkPathConstants();
require_once localarenaFrameworkPath('module/table/table.game.php');

/**
 * Tests for the modern names BGA's framework gives the game base
 * class and the current game state.
 *
 * These are what a game's own linter now insists on -- it flags
 * `extends \Table`, `gamestate->state_id()`, and `gamestate->state()`
 * as deprecated -- so a game cannot follow that advice unless
 * LocalArena answers to the new names too.  Nothing here needs a
 * table or a database: the alias is established at class-load time
 * and the two state methods are plain delegations, so this belongs in
 * the unit lane (see `UnitTestCase`).
 */
class FrameworkTableAliasTest extends UnitTestCase
{
  public function testNamespacedNameResolvesToTheGlobalClass(): void
  {
    $this->assertTrue(class_exists(\Bga\GameFramework\Table::class));

    // Not merely "both exist": an alias and a subclass both satisfy
    // that, and only the alias makes a game extending the namespaced
    // name pass a `\Table` type hint.  Reflection reports the class
    // an alias names, so this distinguishes them.
    $this->assertSame(
      \Table::class,
      (new \ReflectionClass(\Bga\GameFramework\Table::class))->getName()
    );
  }

  public function testSubclassOfTheNamespacedNameIsAlsoATable(): void
  {
    // The shape a migrated game has: `class Foo extends
    // \Bga\GameFramework\Table`.  Framework code type-hints and tests
    // `\Table`, so this is the property that actually has to hold.
    $game = new \ReflectionClass(FrameworkTableAliasTestGame::class);
    $this->assertTrue($game->isSubclassOf(\Table::class));
  }

  public function testCurrentMainStateIdIsDeclared(): void
  {
    $method = new \ReflectionMethod(\Table::class, 'getCurrentMainStateId');
    $this->assertTrue($method->isPublic());
    $this->assertSame(0, $method->getNumberOfParameters());
    $this->assertSame('int', (string) $method->getReturnType());
  }

  public function testCurrentMainStateIsDeclared(): void
  {
    foreach ([\Table::class, \GameState::class] as $class) {
      $method = new \ReflectionMethod($class, 'getCurrentMainState');
      $this->assertTrue($method->isPublic(), $class);
      $this->assertSame(0, $method->getNumberOfParameters(), $class);
      $this->assertSame(
        \CurrentGameState::class,
        (string) $method->getReturnType(),
        $class
      );
    }
  }

  // The half of the migration that is not a rename: BGA's modern
  // accessor returns an object where `state()` returned the raw
  // descriptor array, so a call site reading `['name']` has to become
  // `->name` -- and one handing the state to `zombieTurn()`, which
  // still takes the array, has to say `->toArray()`.
  public function testCurrentMainStateReadsAsPropertiesAndAsAnArray(): void
  {
    $descriptor = [
      'name' => 'stAgentAction',
      'type' => 'activeplayer',
      'transitions' => ['done' => 42],
    ];
    $state = new \CurrentGameState($descriptor);

    $this->assertSame('stAgentAction', $state->name);
    $this->assertSame('activeplayer', $state->type);
    $this->assertSame(['done' => 42], $state->transitions);
    $this->assertSame($descriptor, $state->toArray());
  }

  // A descriptor omits its optional keys (`args` and `description` on
  // a state that needs neither), and reading one off the array was an
  // undefined index rather than an error.  Reading one off the object
  // stays as forgiving, so a state that omits a key does not have to
  // be special-cased at the call site.
  public function testAbsentDescriptorKeyReadsAsNull(): void
  {
    $state = new \CurrentGameState(['name' => 'stNextTurn']);

    $this->assertNull($state->args);
    $this->assertFalse(isset($state->args));
    $this->assertTrue(isset($state->name));
  }
}

// Declared, never instantiated: `Table::__construct()` opens a
// database connection, and none of the assertions above need an
// instance.
class FrameworkTableAliasTestGame extends \Bga\GameFramework\Table
{
}
