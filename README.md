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

- "GameServer.php" looks like it might be code for the WebSocket-based
  notification system.  That probably needs to be run as a service.

  ```
  ebg/core/gamegui.js:30:        	this.socket = new WebSocket("ws://localhost:3000/"+this.player_id);
  ```

- Bind-mount /src into the `server` container so that we don't need to
  rebuild it and restart services every time something is edited.

- 404: "/css/csslayer.css"

- "thecrew.js" script error: `http://localhost:9000/:248`

  And when I check "load saved game state", for example, I see this in the console (also as a script error thrown from "thecrew.js"):

  ```
  http://localhost:9000/?loadDatabase=1&testplayer0:248
  ```

- Ah, game JS is not being loaded: "https://localhost/game/thecrew/thecrew.js"
