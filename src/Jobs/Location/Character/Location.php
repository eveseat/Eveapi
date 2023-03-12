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

namespace Seat\Eveapi\Jobs\Location\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\Location\CharacterLocation;

/**
 * Class Location.
 *
 * @package Seat\Eveapi\Jobs\Location\Character
 */
class Location extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/location/';

    /**
     * @var int
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-location.read_location.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'meta'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $location = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($location->isCachedLoad() && CharacterLocation::find($this->getCharacterId())) return;

        CharacterLocation::firstOrNew([
            'character_id' => $this->getCharacterId(),
        ])->fill([
            'solar_system_id' => $location->solar_system_id,
            'station_id'   => property_exists($location, 'station_id') ?
                $location->station_id : null,
            'structure_id' => property_exists($location, 'structure_id') ?
                $location->structure_id : null,
        ])->save();
    }
}
