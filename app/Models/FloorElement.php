<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A part of the hall's floor plan other than a table: stage, bar, door, corridor, wall, technical tower, label...
 * Coordinates are grid units (see App\Support\Shop\FloorPlan::size()).
 */
class FloorElement extends Model
{
    protected $fillable = ['type', 'label', 'x', 'y', 'w', 'h', 'color', 'z'];

    protected function casts(): array
    {
        return ['x' => 'float', 'y' => 'float', 'w' => 'float', 'h' => 'float', 'z' => 'integer'];
    }
}
