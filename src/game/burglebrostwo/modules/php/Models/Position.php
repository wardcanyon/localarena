<?php

namespace BurgleBrosTwo\Models;

class Position
{
    public int $x;
    public int $y;
    public int $z;

    public static function fromRow($row): Position
    {
        $pos = new Position();
        $pos->x = intval($row["pos_x"]);
        $pos->y = intval($row["pos_y"]);
        $pos->z = intval($row["pos_z"]);
        return $pos;
    }

    public static function fromArray($a): Position
    {
        $pos = new Position();
        $pos->x = intval($a[0]);
        $pos->y = intval($a[1]);
        $pos->z = intval($a[2]);
        return $pos;
    }

    public function toArray()
    {
        return [$this->x, $this->y, $this->z];
    }
}
