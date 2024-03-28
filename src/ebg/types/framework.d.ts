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

interface Counter {
    // How fast animations move.
    speed: number;

    // Associate the counter with the given DOM element, which must
    // already exist.
    create(elId: string): void;

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
