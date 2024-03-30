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

### Developing LocalArena

LocalArena is written in TypeScript and type-annotated PHP.

TODO: Add instructions on local machine setup necessary to get the
`grunt` build to work.

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

## Tips

- When you get an unexpected "connection closed" on the client, look
  at log output from the websock server ("GameServer.php"); sometimes
  that means that it crashed because of an unexpected error.

## Behavioral differences

- `PHP Fatal error: Uncaught mysqli_sql_exception: Field 'card_order'
  doesn't have a default value` -- and it's NOT NULL; I wonder why
  this works on BGA Studio.
