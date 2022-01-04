<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2022 Leon Jacobs
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

namespace Seat\Eveapi\Models\Corporation;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Assets\CorporationAsset;
use Seat\Eveapi\Models\Sde\DgmTypeAttribute;
use Seat\Eveapi\Models\Sde\InvControlTowerResource;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\Moon;
use Seat\Eveapi\Models\Sde\SolarSystem;
use Seat\Eveapi\Traits\HasCompositePrimaryKey;

/**
 * Class CorporationStarbase.
 *
 * @package Seat\Eveapi\Models\Corporation
 */
class CorporationStarbase extends Model
{
    use HasCompositePrimaryKey;

    /**
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * @var array
     */
    protected $primaryKey = ['corporation_id', 'starbase_id'];

    /**
     * @return float
     */
    public function getBaseFuelUsageAttribute()
    {

        $resources = InvControlTowerResource::where('controlTowerTypeID', $this->type_id)
            ->whereBetween('resourceTypeID', [4000, 5000])// base fuel usage are between 4000 and 5000
            ->where('purpose', 1)
            ->first();

        if (! is_null($resources))
            return $resources->quantity;

        return 0.0;
    }

    /**
     * @return float
     */
    public function getBaseStrontiumUsageAttribute()
    {

        $resources = InvControlTowerResource::where('controlTowerTypeID', $this->type_id)
            ->where('resourceTypeID', 16275)// base strontium usage is 16275
            ->where('purpose', 4)
            ->first();

        if (! is_null($resources))
            return $resources->quantity;

        return 0.0;
    }

    /**
     * @return float
     */
    public function getStrontiumBaySizeAttribute()
    {

        $attributes = DgmTypeAttribute::where('typeID', $this->type_id)
            ->where('attributeID', 1233)// strontium bay attribute
            ->first();

        if (! is_null($attributes))
            return $attributes->valueFloat;

        return 0.0;
    }

    /**
     * @param $value
     */
    public function setOnlinedSinceAttribute($value)
    {
        $this->attributes['onlined_since'] = is_null($value) ? null : carbon($value);
    }

    /**
     * @param $value
     */
    public function setReinforcedUntilAttribute($value)
    {
        $this->attributes['reinforced_until'] = is_null($value) ? null : carbon($value);
    }

    /**
     * @param $value
     */
    public function setUnanchorAtAttribute($value)
    {
        $this->attributes['unanchor_at'] = is_null($value) ? null : carbon($value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function detail()
    {

        return $this->hasOne(CorporationStarbaseDetail::class, 'starbase_id', 'starbase_id')
            ->withDefault([
                'allow_corporation_members'                => 0,
                'allow_alliance_members'                   => 0,
                'use_alliance_standings'                   => 0,
                'attack_standing_threshold'                => 0,
                'attack_security_status_threshold'         => 0,
                'attack_if_other_security_status_dropping' => 0,
                'attack_if_at_war'                         => 0,
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fuelBays()
    {

        return $this->hasMany(CorporationStarbaseFuel::class, 'starbase_id', 'starbase_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {

        return $this->belongsTo(CorporationAsset::class, 'starbase_id', 'item_id')
            ->withDefault([
                'type_id' => 0,
                'name' => trans('web::seat.unknown'),
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function moon()
    {
        return $this->belongsTo(Moon::class, 'moon_id', 'moon_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function solar_system()
    {
        return $this->belongsTo(SolarSystem::class, 'system_id', 'system_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type()
    {

        return $this->belongsTo(InvType::class, 'type_id', 'typeID');
    }
}
