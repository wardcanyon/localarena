# LocalArena

## What is LocalArena?

LocalArena is a set of development and testing tools for games written
to run on Board Game Arena (BGA).

Concretely, it consists of

- A compatible reimplementation of the parts of BGA's client-side and
  server-side frameworks that are necessary to run and test individual
  games.

- Interfaces and type definitions for the relevant parts of BGA's
  client-side and server-side frameworks.

- Fixtures and frameworks for writing both unit and integration tests
  for the server-side components of a game.

- Docker Compose configuration for running all of the above.

_LocalArena is a community project.  It is not written, supported, or
endorsed by Board Game Arena or any of its staff._

If you notice differences in behavior, please report them here as bugs
after reading through the "Limitations" section below.  Please **do
not** ask BGA staff for support for LocalArena.

### Building and running LocalArena

LocalArena is written in TypeScript and type-annotated PHP.

You will need to have Node installed to build LocalArena; this part of
the project is not yet Dockerized.  The author is using v20.11.0.

Run `npm install` to install Node packages, and then `grunt` to build
LocalArena.  Once the `grunt` build succeeds, you are ready to build
and run Docker images.

### Writing integration tests for a game

TODO: Finish fleshing this out; explain what needs to be added to the
project repository.

First, build and tag the `testenv` container.  This contains a PHP
interpreter with the appropriate plugins and PHPUnit.

```
$ cd localarena
$ docker build -t wardcanyon/localarena-testenv:latest --target=testenv .
```

Then, after starting LocalArena (with `docker compose up --build`),
run your tests.

`bga-burglebrostwo` is an example of a game with these kinds of tests
in place.  Here's how they're run.

```
$ export LOCALARENA_ROOT=/path/to/localarena
$ cd bga-yourgame
$ docker run -it --rm --network localarena_default \
  -v ${LOCALARENA_ROOT}/db/password.txt:/run/secrets/db-password:ro \
  -v $PWD/build:/src/game/burglebrostwo \
  -v $PWD/server:/src/server \
  wardcanyon/localarena-testenv:latest \
  phpunit --configuration /src/server/modules/Test/phpunit.xml \
  /src/server/modules/StateTransitionTest.php
```

Notice that we mount two different things into the container:
`/src/game/burglebrostwo` and `/src/server`.  `bga-burglebrostwo` has
a build process that assembles the files that need to be uploaded to
BGA and puts them in the `build` subdirectory; this needs to be
mounted in a subdirectory of `/src/game`.  This build output doesn't
include tests, however, so we also mount the server sources at
`/src/server` so that we can run the tests.

If your game doesn't have a build process like that, you should be
able to simply mount your sources at `/src/game/<gamename>` and run
tests directly from there.

If you are making changes to LocalArena itself, you may want to mount
parts of it into the container as well, so that you don't need to
rebuild the container every time.

```
-v ${LOCALARENA_ROOT}/src/module:/src/localarena/module:ro
-v ${LOCALARENA_ROOT}/src/view:/src/localarena/view:ro
```

#### Legacy-games data in tests

The [legacy-games API](https://en.doc.boardgamearena.com/Main_game_logic:_yourgamename.game.php#Legacy_games_API)
(`$this->bga->legacy`, plus the deprecated `storeLegacyData()`-style
aliases) is supported; legacy data persists across tables in the
`legacy_player_data` and `legacy_team_data` tables in the shared
`localarena` database.  (It lives outside the per-table database
deliberately: `undoRestorePoint()` restores the table database, and
must not roll back legacy data.)

In addition to the game name, legacy data is keyed by a table's
*legacy scope* (`TableParams::$legacy_scope`); legacy data is shared
exactly by the tables of a game that share a scope.  Outside of tests
the scope is empty -- the game's one shared pool, which gives BGA's
semantics that every table of a game shares its legacy data.  In
tests, `IntegrationTestCase` instead assigns each test case its own
scope (unique by construction: the database allocates it), so tests'
legacy data is *isolated by default*: nothing leaks between tests,
between runs, or between tests and interactive play against the same
database, and nothing is ever cleared.

Because campaign-style games read legacy data during `setupNewGame()`,
tests usually need to arrange it *before* their table is created.
`IntegrationTestCase` creates the table lazily and provides helpers
that write straight into the test's scope, so a test can seed first
and then let table creation read the data:

```php
public function testCampaignSetup(): void
{
    // No table exists yet.  stGameSetup() assigns deterministic
    // player IDs; presetPlayerId() knows them in advance.
    $this->seedLegacyData(self::presetPlayerId(0), 'campaign', ['unlocked' => ['red']]);
    $this->seedLegacyTeamData(['chapter' => 3]);

    // The first table() call creates the table; setupNewGame() can
    // read the seeded data via $this->bga->legacy.
    $this->table();
    // ... assertions ...
}
```

A test that creates further tables itself (e.g. to drive a campaign
from one table into the next) should set
`TableParams::$legacy_scope = $this->legacyScope()` on each of them so
they share the test's pool.  See `tests/LegacyTest.php` for full
examples.

#### The notification size limit

BGA does not send notifications as your game code produces them: they
are bundled, and the bundle goes out once the request has finished --
so one bundle covers the action *and* every state transition that
followed it synchronously.  That bundle may not exceed **128 KiB**.  A
request that generates more than that fails outright:

```
Unexpected error: Error: generated notifications are larger than 128k (140501)
```

...and the move is lost.  LocalArena keeps the same books, so a test
that would blow the limit on BGA fails here instead, with BGA's own
message (a `LocalArenaNotifSizeLimitException`, which is a
`BgaVisibleSystemException`).  As on BGA, the failing request is rolled
back in its entirety: no notifications are sent, no move is consumed,
and the state machine stays where the request found it.

What makes the limit worth modelling is that no single notification
need look suspicious.  It is one *action's* worth of notifications that
is capped, not one call's and not a table's, so a cascade of
"game"-type states that each send a few kilobytes -- plus the
framework's own state-change notifications, which count too -- can
overrun it between them.  Where BGA reports only the total, LocalArena
also logs a breakdown of the bundle by notification type, so you can
see which ones were expensive.

What LocalArena counts is the serialized notification (the JSON that
goes into the `gamelog` row), once per `notifyAllPlayers()` /
`notifyPlayer()` call rather than once per recipient.  BGA's exact
accounting is not documented, so treat the limit as the cliff edge it
is: a game tuned to sit just under it here could still be over it on
the site.

Tests are held to BGA's limit by default.  To exercise the limit
without generating 128 KiB of padding, or to opt out of it entirely,
set `TableParams::$notif_size_limit` (or call
`$table->localarenaSetNotifSizeLimit()`):

```php
protected function defaultTableParams(): TableParams
{
    $params = parent::defaultTableParams();
    // A small limit, so a test can exceed it cheaply...
    $params->notif_size_limit = 16 * 1024;
    // ...or no limit at all.
    $params->notif_size_limit = \LocalArenaNotifBudget::NO_LIMIT;
    return $params;
}
```

`$table->localarenaNotifBudget()` exposes what the request in flight
has spent so far (`total()`, `count()`, `breakdown()`), which is
useful for asserting that a chatty action stays clear of the limit
rather than merely under it.  See `tests/NotifSizeLimitTest.php`.

### Generating code-coverage reports

The `testenv` image ships with the [PCOV](https://github.com/krakjoe/pcov)
coverage driver installed. PCOV is much faster than Xdebug for
coverage-only work, but to keep normal test runs at **zero overhead** it
is **disabled by default** (`pcov.enabled=0` is baked into the image).
PCOV is a no-op while disabled, so it costs nothing until you ask for it.

To produce a report, enable PCOV for that single invocation by passing
`php -d pcov.enabled=1` and adding a `--coverage-*` option to PHPUnit. The
command is the same as a normal test run (see `Gruntfile.cjs`'s
`shell:test` task), with those two additions. For example, to get a
text summary plus an HTML report written to `./coverage/html` on the host:

```
$ mkdir -p coverage
$ docker run -i --rm \
  --network localarena_default \
  -v $PWD/db/password.txt:/run/secrets/db-password:ro \
  -v $PWD/src/module:/src/localarena/module:ro \
  -v $PWD/src/game/localarenanoop:/src/game/localarenanoop:ro \
  -v $PWD/tests:/src/localarena/tests:ro \
  -v $PWD/coverage:/coverage \
  wardcanyon/localarena-testenv:latest \
  php -d pcov.enabled=1 /vendor/bin/phpunit \
    --configuration /src/localarena/tests/phpunit.xml \
    --coverage-text \
    --coverage-html /coverage/html
```

Notes:

- `php -d pcov.enabled=1` is what turns coverage on. Without it, the
  `--coverage-*` options will report that no coverage driver is active.
- `--coverage-text` prints a per-file summary to the console;
  `--coverage-html /coverage/html` writes a browsable report. Other
  formats (`--coverage-clover`, `--coverage-cobertura`, etc.) work too.
- The extra `-v $PWD/coverage:/coverage` mount is only needed for report
  formats that write files to disk; `--coverage-text` alone needs no mount.
- What gets measured is configured by the `<source>` element in
  `tests/phpunit.xml` (currently the LocalArena `module` sources).
- This driver is **not** wired into CI; coverage is an on-demand,
  local-only tool.

#### Coverage for a game built on top of the `testenv` image

The example above measures LocalArena's own `module` sources. If you are
running a **game's** tests against the `testenv` image, the mechanism is
identical — you just point it at your own sources:

1. **Enable PCOV per-invocation.** Run phpunit (or paratest) through PHP
   with the flag: `php -d pcov.enabled=1 /vendor/bin/phpunit …` (the
   binaries live in `/vendor/bin`, which is on `PATH`, but you must invoke
   them via `php -d …` so the setting is applied). Without this flag the
   `--coverage-*` options report that no driver is active — that is by
   design, so normal runs stay at zero overhead.

2. **Tell PHPUnit which code to measure.** Add a `<source>` element to
   *your* `phpunit.xml`, pointing at the in-container path where your game
   sources are mounted, e.g.:

   ```xml
   <source>
       <include>
           <directory>/src/game/yourgame</directory>
       </include>
   </source>
   ```

   Without a `<source>` block (or a `--coverage-filter` CLI option) the
   report will be empty.

3. **Get the report out of the container.** Mount a host directory for
   file-based reports (`--coverage-html`, `--coverage-clover`, …);
   `--coverage-text` prints to stdout and needs no mount.

```
$ mkdir -p coverage
$ docker run -i --rm \
  --network localarena_default \
  -v $PWD/db/password.txt:/run/secrets/db-password:ro \
  -v $PWD/your-sources:/src/game/yourgame:ro \
  -v $PWD/tests:/src/game/yourgame/tests:ro \
  -v $PWD/coverage:/coverage \
  wardcanyon/localarena-testenv:latest \
  php -d pcov.enabled=1 /vendor/bin/phpunit \
    --configuration /src/game/yourgame/tests/phpunit.xml \
    --coverage-text \
    --coverage-html /coverage/html
```

Adjust the mounts and paths to match your project layout; the only
coverage-specific additions are `php -d pcov.enabled=1`, the
`--coverage-*` flags, the `<source>` block, and the `/coverage` mount.
Do **not** bake `pcov.enabled=1` into a derived image unless you actually
want coverage overhead on every run — the default exists precisely so the
driver is loaded and ready but free.

### Running a game locally

You can change which game will be launched, how many players will be
at the table, etc. by editing `src/localarena_config.inc.php`.

To start LocalArena, issue

```
$ grunt
$ docker compose up --build
```

Once the containers are running, you can visit the game itself by
visiting http://localhost:9000.

You can interact with the game's database through phpMyAdmin by
visiting http://localhost:8080.

To wipe the database so that it will be reinitialized, issue...

```
$ docker compose down
$ docker volume rm localarena_db-data
```

### TODOs

- Reject actions from players who are not active, including
  non-multiactive players when in multiactive states, on the server
  side.

- Be specific about which client/server-side components, notifs,
  etc. are supported.

- Multiple table support.  This will be necessary for integration
  testing.

- Write integration tests with PhpUnit.  This will require test
  fixtures built on top of multiple table support.

- Probably worth eventually having some tests for LocalArena itself.

- Better validation all over; e.g. we should be strict about rejecting
  invalid state definitions, etc.

- Refine types/interfaces.  Can we also support unit testing?

- Separate games, vendored deps, and the local framework.

- Set up tests for the local framework itself. (e.g. "can you
  initialize a table and run through a few actions without blowing
  up?" for a couple of different games)

- Add type annotations in PHP codebase.

- There are a few client-side components that are probably common that
  we don't support yet; and some of the ones that *are* supported

- Move framework types/interfaces over from BB2 repository.

- Update bundled deps.

- There are still a few deps (jQuery, Popper, Bootstrap) that are
  loaded from CDNs.  We should bundle those to allow for completely
  offline development.

- Bind-mount /src into the `server` container so that we don't need to
  rebuild it and restart services every time something is edited.

### Future ideas

- Integrate some project-linting stuff (e.g. from BGA Workbench).

- Make it easy to use LocalArena without copying a built project into the
  LocalArena repository and rebuilding the Docker images.

- Add a viewer for logs, server-side errors, etc.

- Add facilities for client-side testing.

- Support database save/loads like BGA Studio does.

## Limitations

- The logging functions (`trace()` et al.) just echo the message
  they're given, which isn't very helpful.

- The number of players, and their usernames and IDs, can only be
  configured by editing "localarena_config.inc.php".

- At the moment, game options are always set to their defaults.

- Markup may not match current BGA markup very closely.

- Replays are not supported.

- 3D is not supported.

- Zombie players are not supported.

- Spectators are not supported.

- Private states are not supported.

- `reflexion` is not supported.

- Client-side translation (i18n) is not supported.

- Image-loading control functions (e.g. `ensure...ImageLoading()`) are
  not supported; they are no-ops.

- Support for preferences is minimal; in particular, there is no UI
  for changing them.

- Game-end functionality is missing; there is no score display.

- The [legacy-games API](https://en.doc.boardgamearena.com/Main_game_logic:_yourgamename.game.php#Legacy_games_API)
  is supported (see "Legacy-games data in tests" above), but legacy
  data is never garbage-collected here beyond its TTL (in particular,
  per-test scopes accumulate rows, much as tests accumulate `table_N`
  databases), and writes to it are not covered by the per-action
  transaction on the table database.

## Tips

- When you get an unexpected "connection closed" on the client, look
  at log output from the websock server ("GameServer.php"); sometimes
  that means that it crashed because of an unexpected error.

## Behavioral differences

- `PHP Fatal error: Uncaught mysqli_sql_exception: Field 'card_order'
  doesn't have a default value` -- and it's NOT NULL; I wonder why
  this works on BGA Studio.
