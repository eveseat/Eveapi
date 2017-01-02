<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017  Leon Jacobs
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

namespace Seat\Eveapi\Models\Character;

use Illuminate\Database\Eloquent\Model;
use Seat\Services\Traits\NotableTrait;

/**
 * Class CharacterSheet.
 * @package Seat\Eveapi\Models
 */
class CharacterSheet extends Model
{
    use NotableTrait;

    /**
     * @var string
     */
    protected $table = 'character_character_sheets';

    /**
     * @var string
     */
    protected $primaryKey = 'characterID';

    /**
     * @var array
     */
    protected $fillable = [
        'characterID', 'name', 'homeStationID', 'DoB', 'race', 'bloodLineID',
        'bloodLine', 'ancestryID', 'ancestry', 'gender', 'corporationName',
        'corporationID', 'allianceName', 'allianceID', 'factionName', 'factionID',
        'cloneTypeID', 'cloneName', 'cloneSkillPoints', 'freeSkillPoints',
        'freeRespecs', 'cloneJumpDate', 'lastRespecDate', 'lastTimedRespec',
        'remoteStationDate', 'jumpActivation', 'jumpFatigue', 'jumpLastUpdate',
        'balance', 'intelligence', 'memory', 'charisma', 'perception', 'willpower',
    ];

    /**
     * Return any Jump Clones the character has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function jump_clones()
    {

        return $this->hasMany(
            CharacterSheetJumpClone::class, 'characterID', 'characterID');
    }

    /**
     * Returns any implants the character may have.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function implants()
    {

        return $this->hasMany(
            CharacterSheetImplants::class, 'characterID', 'characterID');
    }

    /**
     * Returns any skills the character may have.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function skills()
    {

        return $this->hasMany(
            CharacterSheetSkills::class, 'characterID', 'characterID');
    }

    /**
     * Returns any corp titles the character may have.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function corporation_titles()
    {

        return $this->hasMany(
            CharacterSheetCorporationTitles::class, 'characterID', 'characterID');
    }
}
