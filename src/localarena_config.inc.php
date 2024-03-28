<?php

// This file contains configuration for LocalArena, the framework for local
// BGA development and testing.

// State of included games:
//
// - "hearts" requires at least 3 players; the game can start, but
//   runs into an undefined-variable error because there is not yet
//   support for private state.
//
// - "reversi" requires exactly 2 players; the game works!
//
// - "burglebrostwo" is an incomplete implementation.  A bug in
//   "ebg/stock" is visible; player-board interactions are disabled in
//   the included copy.
//
// - "thecrew": status unknown.


const LOCALARENA_GAME_NAME = 'hearts';
const LOCALARENA_PLAYER_COUNT = 3;

// const LOCALARENA_GAME_NAME = 'reversi';
// const LOCALARENA_PLAYER_COUNT = 2;

const LOCALARENA_PLAYER_NAME_STEM = 'localdev';
