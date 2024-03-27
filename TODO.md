- Don't allow game to start with an invalid number of players.

- There isn't client-side support for preferences; `this.prefs` is
  undefined.  ("hearts")

- Make player-board DOM elements more realistic, since some (many?)
  games manipulate them.  ("reversi", "burglebrostwo")

- Silence the ServerName warnings that Apache prints on startup; they
  might confuse people.

- Dockerize the `grunt` build process.
