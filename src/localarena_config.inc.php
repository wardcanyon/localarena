<?php

// This file contains configuration for LocalArena, the framework for local
// BGA development and testing.

// State of included games:
//
// - "hearts" requires at least 3 players; the game works!

// - "reversi" requires exactly 2 players; the game works!
//
// - "burglebrostwo" is an incomplete implementation.  A bug in
//   "ebg/stock" is visible; player-board interactions are disabled in
//   the included copy.
//
// - "thecrew": game starts, but runs into some client-side issues.

// *********
// It may be convenient to use `git update-index --skip-worktree
// src/localarena_config.inc.php` to make git ignore changes to this
// file!
// *********

// const LOCALARENA_GAME_NAME = 'hearts';
// const LOCALARENA_PLAYER_COUNT = 3;

// const LOCALARENA_GAME_NAME = 'reversi';
// const LOCALARENA_PLAYER_COUNT = 2;

// const LOCALARENA_GAME_NAME = 'thecrew';
// const LOCALARENA_PLAYER_COUNT = 3;

// const LOCALARENA_GAME_NAME = 'burglebrostwo';
// const LOCALARENA_PLAYER_COUNT = 1;

const LOCALARENA_GAME_NAME = 'effortlesswc';
const LOCALARENA_PLAYER_COUNT = 2;

const LOCALARENA_PLAYER_NAME_STEM = 'localdev';
