<?php

// This file contains configuration for LocalArena, the framework for local
// BGA development and testing.

// State of included games:
//
// - "hearts" requires at least 3 players; the game can start, but the
//   client hangs because "this.prefs" is not available.
//
// - "reversi" requires exactly 2 players; the game can start, but the
//   client hangs because it tries to manipulate player-board DOM
//   elements that are not present.
//
// - "burglebrostwo" is an incomplete implementation.  A bug in
//   "ebg/stock" is visible; player-board interactions are disabled in
//   the included copy.
//
// - "thecrew": status unknown.


const LOCALARENA_GAME_NAME = 'hearts';

const LOCALARENA_PLAYER_NAME_STEM = 'localdev';

const LOCALARENA_PLAYER_COUNT = 3;
