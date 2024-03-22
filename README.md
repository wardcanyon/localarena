### Running tests

In one tab, start services with...

```
$ docker compose up --build
```

And then, in another, run the "test.php" script with...

```
$ docker compose run --build --rm server php /src/test.php
```
