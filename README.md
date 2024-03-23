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
$ docker volume rm local_db-data
```

### TODOs

- Refine types/interfaces.  Can we also support unit testing?

- Separate games, vendored deps, and the local framework.

- Write integration tests with PhpUnit.

- Set up tests for the local framework itself. (e.g. "can you
  initialize a table and run through a few actions without blowing
  up?" for a couple of different games)

- Add type annotations in PHP codebase.

- Convert client-side codebase to TypeScript; add types.  Move
  framework types/interfaces over from BB2 repository.

### Future ideas

- Integrate some project-linting stuff (e.g. from BGA Workbench).

- Make it easy to use LBGA without copying a built project into the
  LBGA repository and rebuilding the Docker images.

### Limitations

- The logging functions (`trace()` et al.) just echo the message
  they're given, which isn't very helpful.

- The number of players, and their usernames and IDs, is fixed.

- We don't do anything with game options at the moment;
  e.g. `getGameStateValue("optionSuspicion")` returns -1.  (In fact,
  it doesn't look like we ever include "gameoptions.inc.php"!)

- Markup may not match current BGA markup very closely.

### Behavioral differences

- `PHP Fatal error:  Uncaught mysqli_sql_exception: Field 'card_order' doesn't have a default value` -- and it's NOT NULL; I wonder why this works on BGA Studio.

### TODOs

- Bind-mount /src into the `server` container so that we don't need to
  rebuild it and restart services every time something is edited.

- 404: "/css/csslayer.css".  This appears to have been part of the BGA
  theme, but it looks like an empty file today on BGA (2024-03).

  From running a snippet I found quickly online, the following CSS classes appear to be undefined:

  0: "dj_ff124"
  1: "dj_contentbox"
  2: "finalbutton"
  3: "bgabutton"
  4: "bgabutton_blue"
  5: "bgabutton_gray"
  6: "socketButton"
  7: "bg_game_thinking"
  8: "id="
  9: "bg_game_score"
  10: "bg_game_debug_user"
  11: "dijitTooltipContents"
