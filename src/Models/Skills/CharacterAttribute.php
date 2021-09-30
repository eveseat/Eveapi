<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2021 Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Models\Skills;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CharacterAttribute.
 *
 * @package Seat\Eveapi\Models\Market
 */
class CharacterAttribute extends Model
{

    /**
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $primaryKey = 'character_id';

    /**
     * @param $value
     */
    public function setLastRemapDateAttribute($value)
    {
        $this->attributes['last_remap_date'] = is_null($value) ? null : carbon($value);
    }

    /**
     * @param $value
     */
    public function setAccruedRemapCooldownDateAttribute($value)
    {
        $this->attributes['accrued_remap_cooldown_date'] = is_null($value) ? null : carbon($value);
    }
}
