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

namespace Seat\Eveapi\Models\Universe;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Contracts\ContractDetail;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\SolarSystem;

/**
 * Class UniverseStructure.
 *
 * @package Seat\Eveapi\Models\Universe
 *
 * @OA\Schema(
 *     description="Universe Structure",
 *     title="UniverseStructure",
 *     type="object"
 * )
 *
 * @OA\Property(
 *     property="structure_id",
 *     type="integer",
 *     format="int64",
 *     description="Structure identifier"
 * )
 *
 * @OA\Property(
 *     property="name",
 *     type="string",
 *     description="Structure name"
 * )
 */
class UniverseStructure extends Model
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
    protected $primaryKey = 'structure_id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function contracts_from()
    {
        return $this->morphMany(ContractDetail::class, 'start_location');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function contracts_to()
    {
        return $this->morphMany(ContractDetail::class, 'end_location');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function solar_system()
    {
        return $this->hasOne(SolarSystem::class, 'system_id', 'solar_system_id')
            ->withDefault([
                'name' => trans('web::seat.unknown'),
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function type()
    {
        return $this->hasOne(InvType::class, 'typeID', 'type_id')
            ->withDefault([
                'typeName' => trans('web::seat.unknown'),
            ]);
    }
}
