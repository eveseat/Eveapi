<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018  Leon Jacobs
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

namespace Seat\Eveapi\Jobs\PlanetaryInteraction\Character;


use Seat\Eveapi\Jobs\EsiBase;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanet;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetContent;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetExtractor;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetFactory;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetHead;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetLink;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetPin;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetRoute;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetRouteWaypoint;

/**
 * Class Planet
 * @package Seat\Eveapi\Jobs\PlanetaryInteraction\Character
 */
class Planets extends EsiBase
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/planets/';

    /**
     * @var int
     */
    protected $version = 'v1';

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {

        $planets = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        collect($planets)->each(function ($planet) {

            CharacterPlanet::firstOrNew([
                'character_id'    => $this->getCharacterId(),
                'solar_system_id' => $planet->solar_system_id,
                'planet_id'       => $planet->planet_id,
            ])->fill([
                'upgrade_level'   => $planet->upgrade_level,
                'num_pins'        => $planet->num_pins,
                'last_update'     => carbon($planet->last_update),
                'planet_type'     => $planet->planet_type,
            ])->save();

        });

        // Cleanup solar system ids that have removed planets
        collect($planets)->unique('solar_system_id')
            ->pluck('solar_system_id')->each(function ($solar_system_id) use ($planets) {

                CharacterPlanet::where('character_id', $this->getCharacterId())
                    ->where('solar_system_id', $solar_system_id)
                    ->whereNotIn('planet_id', collect($planets)
                        ->pluck('planet_id')->flatten()->all())
                    ->delete();
            });

        // Remove empty solarsystem ids
        CharacterPlanet::where('character_id', $this->getCharacterId())
            ->whereNotIn('solar_system_id', collect($planets)
                ->pluck('solar_system_id')->flatten()->all())
            ->delete();
    }
}
