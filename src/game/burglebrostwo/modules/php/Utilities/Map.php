<?php

namespace BurgleBrosTwo\Utilities;

use BurgleBrosTwo\Models\Position;

// Represents a single-floor grid map.
class Map
{
    protected $width;
    protected $height;

    // Array [x:int][y:int] -> passable? bool
    protected $tiles;
    // Array [x:int][y:int][vertical:bool] -> present? bool
    protected $walls;

    function __construct($tiles, $walls)
    {
        // XXX: We should probably convert from raw DB rows to
        // something easier to work with here.
        $this->tiles = $tiles;

        $this->width = 4;
        $this->height = 4;

        // Initialize $this->walls to reflect that there are no walls.
        $this->walls = [];
        for ($x = 0; $x < $this->width; ++$x) {
            $this->walls[$x] = [];
            for ($y = 0; $y < $this->height; ++$y) {
                $this->walls[$x][$y] = [
                    true => false,
                    false => false,
                ];
            }
        }

        // Now go through $walls and update $this->walls to reflect
        // the position of each wall we've been given.
        foreach ($walls as $wall) {
            // throw new \feException(print_r($wall), true);
            $x = $wall["pos_x"];
            $y = $wall["pos_y"];
            $vertical = $wall["vertical"];
            $this->walls[$x][$y][$vertical] = true;
        }

        // print_r($this->walls);
    }

    // Returns true/false indicating whether each point on the map is
    // reachable from each other point.
    function isConnected()
    {
        $open = [Position::fromArray([0, 0, 0])];
        $closed = [];
        while (count($open) > 0) {
            $pos = array_pop($open);
            $closed[] = $pos;
            foreach ($this->getAdjacent($pos) as $adjacentPos) {
                if (
                    !(
                        in_array($adjacentPos, $open) ||
                        in_array($adjacentPos, $closed)
                    )
                ) {
                    $open[] = $adjacentPos;
                }
            }
        }

        // XXX: If we have maps where $tiles is not all "true", then
        // we need to count the number of "true" values here rather
        // than just taking the size of the map.
        $tileCount = $this->width * $this->height;
        if (count($closed) > $tileCount) {
            throw new \feException(
                "Explored more tiles than exist; that should be impossible!"
            );
        }
        // print("Found ".count($closed)." tiles.\n");
        return count($closed) == $tileCount;
    }

    // Returns the sequence of tiles that represents the shortest path
    // from $start to $dest.  If there are multiple paths of equal
    // length, the "more clockwise" one is selected.
    //
    // The return value does *not* include $start, but does include
    // $dest, which means that its length is the number of moves
    // required to follow the path.
    //
    // Returns null iff no path exists.
    function shortestPathClockwise(Position $start, Position $dest)
    {
        // The *end* of the array is the front of our exploration
        // queue, which is important; this works because
        // `getAdjacentClockwise()` returns adjacent tiles in a
        // particular order.  This causes us to find the "most
        // clockwise" path of equal length first.
        //
        // We can't use the `SplPriorityQueue` class here because it
        // does not promise to maintain order for items with the same
        // priority.
        $open = [$start];
        $closed = [];
        $cameFrom = [];
        $found = false;

        while (count($open) > 0) {
            $pos = array_pop($open);
            if ($pos == $dest) {
                $found = true;
                break;
            }
            $closed[] = $pos;

            // N.B.: In order to ensure that we prefer shorter paths,
            // we need to insert newly-discovered nodes at the end of
            // the $open queue.  In order to ensure that we prefer to
            // explore "more clockwise" paths, we need to insert new
            // items in the appropriate order (as they are returned
            // from `getAdjacentClockwise()`.

            // foreach (array_reverse($this->getAdjacentClockwise($pos, $dest)) as $adjacentPos) {
            foreach ($this->getAdjacentClockwise($pos, $dest) as $adjacentPos) {
                if (
                    !(
                        in_array($adjacentPos, $open) ||
                        in_array($adjacentPos, $closed)
                    )
                ) {
                    array_unshift($open, $adjacentPos);
                    $cameFrom[$this->posToStr($adjacentPos)] = $pos;
                }
            }
            // print('  Open set (front is at end): '.$this->formatPosList($open) . "\n");
        }

        if (!$found) {
            return null;
        }

        $path = [];
        $pos = $dest;
        while ($pos != $start) {
            array_unshift($path, $pos);
            $pos = $cameFrom[$this->posToStr($pos)];
        }
        return $path;
    }

    // Returns the same elements as `getAdjacent($pos)`, but in a
    // particular order that puts tiles that will lead to "more
    // clockwise" paths first.
    function getAdjacentClockwise(Position $pos, $dest)
    {
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        if ($pos->x < $dest->x) {
            if ($pos->y < $dest->y) {
                $order = ["E", "S", "W", "N"];
            } else {
                $order = ["N", "E", "S", "W"];
            }
        } else {
            if ($pos->y < $dest->y) {
                $order = ["S", "W", "N", "E"];
            } else {
                $order = ["W", "N", "E", "S"];
            }
        }

        // print('getAdjacentClockwise(): pos='.$this->formatPos($pos).' dest='.$this->formatPos($dest).' order=' . implode(',', $order) . "\n");

        $result = [];
        foreach ($order as $o) {
            switch ($o) {
                case "W":
                    if ($x > 0 && !$this->walls[$x - 1][$y][true]) {
                        $result[] = [$x - 1, $y, $z];
                    }
                    break;
                case "E":
                    if ($x < $this->width - 1 && !$this->walls[$x][$y][true]) {
                        $result[] = [$x + 1, $y, $z];
                    }
                    break;
                case "N":
                    if ($y > 0 && !$this->walls[$x][$y - 1][false]) {
                        $result[] = [$x, $y - 1, $z];
                    }
                    break;
                case "S":
                    if (
                        $y < $this->height - 1 &&
                        !$this->walls[$x][$y][false]
                    ) {
                        $result[] = [$x, $y + 1, $z];
                    }
                    break;
                default:
                    throw new Exception('Unexpected direction in $order!');
            }
        }

        // print('  returning: ' . $this->formatPosList($result) . "\n");
        return array_map(function ($a) {
            return Position::fromArray($a);
        }, $result);
    }

    // Returns adjacent tiles on the same floor, considering walls and
    // map boundaries.
    function getAdjacent(Position $pos)
    {
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        // print("getAdjacent() pos=" . self::formatPos($pos) . "\n");

        $result = [];
        if ($x > 0 && !$this->walls[$x - 1][$y][true]) {
            $result[] = [$x - 1, $y, $z];
        }
        if ($x < $this->width - 1 && !$this->walls[$x][$y][true]) {
            $result[] = [$x + 1, $y, $z];
        }
        if ($y > 0 && !$this->walls[$x][$y - 1][false]) {
            $result[] = [$x, $y - 1, $z];
        }
        if ($y < $this->height - 1 && !$this->walls[$x][$y][false]) {
            $result[] = [$x, $y + 1, $z];
        }

        // XXX: If we have maps where $tiles is not all "true" (some
        // tiles are not passable), we should filter $result for
        // passable locations here.

        // print("  returning ".count($result)." adjacent positions:" . self::formatPosList($result) . "\n");

        return array_map(function ($a) {
            return Position::fromArray($a);
        }, $result);
    }

    // Returns true iff positions $a and $b are adjacent.
    function isAdjacent(Position $a, Position $b)
    {
        return in_array($a, $this->getAdjacent($b));
    }

    // For internal use (e.g. array keys).
    function posToStr(Position $pos)
    {
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;
        return "{$x},{$y},{$z}";
    }

    // For debug/printing use.
    function formatPos(Position $pos)
    {
        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;
        return "({$x}, {$y}, {$z})";
    }

    // For debug/printing use.
    function formatPosList($posList)
    {
        return implode(
            ", ",
            array_map(function ($pos) {
                return self::formatPos($pos);
            }, $posList)
        );
    }
}
