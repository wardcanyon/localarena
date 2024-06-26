### Invoking `phan` on LocalArena

```
$ docker run -it --rm -v $PWD/src:/src  -v $PWD/phan.config.php:/src/phan.config.php:ro wardcanyon/localarena-testenv:latest  phan --config-file=/src/phan.config.php --progress-bar -
o /src/phan.analysis.txt
```

TODO: We should add a `grunt phan` target like we have for most of our
games now.

### Invoking `composer` on LocalArena

(For example, to add new packages.)

```
$ docker run -it --rm -v $PWD:/repo/localarena wardcanyon/localarena-testenv:latest bash

# (and then `cd /repo/localarena` and run `composer`)
```
