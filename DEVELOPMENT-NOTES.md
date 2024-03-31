### Invoking `phan` on LocalArena

```
$ docker run -it --rm -v $PWD/src:/src  -v $PWD/phan.config.php:/src/phan.config.php:ro wardcanyon/localarena-testenv:latest  phan --config-file=/src/phan.config.php --progress-bar -
o /src/phan.analysis.txt
```
