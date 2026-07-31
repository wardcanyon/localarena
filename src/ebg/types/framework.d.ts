// XXX: Bring over our more complete type definitions.

// declare let gameui: GameGui;
declare let g_replayFrom: number | undefined;
declare let g_gamethemeurl: string;
declare let g_themeurl: string;
declare let g_archive_mode: boolean;
declare function _(str: string): string;
declare function __(site: string, str: string): string;
declare function $(text: string | Element): HTMLElement;

declare const define;
declare let ebg: any;
declare const dojo;
declare const dijit;
declare type eventhandler = (event?: any) => void;

// ----
// XXX: New stuff added here
// ----

type PlayerId = number;
type PlayerIdString = string;

type Coords = {
  x: number;
  y: number;
  w: number;
  h: number;
};

// The options that `Counter.create()` accepts.
interface CounterCreateOptions {
  // The counter's starting value; null displays "-" (which is how a
  // value that this player may not see is published).
  value?: number | null;

  // The name of the server-side PlayerCounter to follow, so that the
  // counter updates itself from that counter's notifications.
  playerCounter?: string;

  // The name of the server-side TableCounter to follow.
  tableCounter?: string;

  // With `playerCounter`, the player whose value is displayed: their
  // player id, or their player no if the server-side counter is keyed
  // that way.
  playerId?: PlayerId | PlayerIdString;
}

interface Counter {
  // How fast animations move.
  speed: number;

  // Associate the counter with the given DOM element, which must
  // already exist.  With `options.playerCounter`/`options.tableCounter`,
  // the counter follows a server-side counter of that name and updates
  // itself whenever it changes.
  create(elId: string, options?: CounterCreateOptions): void;

  // Return the counter's current value.
  getValue(): number;

  // Changes the current value by `delta`; the change is animated.
  incValue(delta: number): void;

  // Changes the current value to `value` without animation.
  setValue(value: number): void;

  // Changes the current value to `value` with animation.
  toValue(value: number): void;

  // Makes the counter display "-" instead of a value.  Does not
  // change the counter's current value.
  disable(): void;
}

interface PreferenceOption {
  name: string;
}

interface Preference {
  name: string;
  needReload: boolean;
  generic: boolean;
  value: number;
  values: PreferenceOption[];
}
