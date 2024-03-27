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

type Coords = {
  x: number;
  y: number;
  w: number;
  h: number;
};
