### Running tests

In one tab, start services with...

```
$ docker compose up --build
```

And then, in another, run the "test.php" script with...

```
$ docker compose run --build --rm server php /src/test.php
```

You can interact with the game's database through phpMyAdmin by
visiting http://localhost:8080.  It is initialized by
`Table::initTable()` if the `players` table does not exist.

You can visit the game itself by visiting http://localhost:9000.
