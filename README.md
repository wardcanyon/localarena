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

### Running tests

(Right now, this is hardwired to serve `emppty`.)

In one tab, start services with...

```
$ docker compose up --build
```

And then, in another, run the "test.php" script with...

```
$ docker compose run --build --rm server php /src/test.php
```

### Running a game locally

(Right now, this is hardwired to serve `thecrew`.)

You can visit the game itself by visiting http://localhost:9000.

You can interact with the game's database through phpMyAdmin by
visiting http://localhost:8080.  It is initialized by
`Table::initTable()` if the `players` table does not exist.

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

## Limitations

### To be addressed before "0.1"

- The logging functions (`trace()` et al.) just echo the message
  they're given, which isn't very helpful.

- The number of players, and their usernames and IDs, is fixed.

- At the moment, game options are always set to their defaults.

- Markup may not match current BGA markup very closely.

### Long-term

- 3D is not supported.

- Zombie players are not supported.

- Private states are not supported.

- `reflexion` is not supported.

- Client-side translation (i18n) is not supported.

## Behavioral differences

- `PHP Fatal error: Uncaught mysqli_sql_exception: Field 'card_order'
  doesn't have a default value` -- and it's NOT NULL; I wonder why
  this works on BGA Studio.
