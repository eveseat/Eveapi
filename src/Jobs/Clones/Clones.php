<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
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

namespace Seat\Eveapi\Jobs\Clones;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Models\Clones\CharacterClone;
use Seat\Eveapi\Models\Clones\CharacterJumpClone;

/**
 * Class Clones.
 *
 * @package Seat\Eveapi\Jobs\Clones
 */
class Clones extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/clones/';

    /**
     * @var string
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-clones.read_clones.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'clone'];

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $structure_batch = new StructureBatch();

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterClone::where('character_id', $this->getCharacterId())->exists())
            return;

        $clone_informations = $response->getBody();

        // Populate current clone information
        CharacterClone::firstOrNew([
            'character_id' => $this->getCharacterId(),
        ])->fill([
            'last_clone_jump_date' => isset($clone_informations->last_clone_jump_date) ?
                carbon($clone_informations->last_clone_jump_date) : null,
            'home_location_id' => isset($clone_informations->home_location) ?
                $clone_informations->home_location->location_id : null,
            'home_location_type' => isset($clone_informations->home_location) ?
                $clone_informations->home_location->location_type : null,
            'last_station_change_date' => isset($clone_informations->last_station_change_date) ?
                carbon($clone_informations->last_station_change_date) : null,
        ])->save();

        $structure_batch->addStructure($clone_informations->home_location->location_id);

        // Populate jump clone information
        collect($clone_informations->jump_clones)->each(function ($jump_clone) use ($structure_batch) {
            $structure_batch->addStructure($jump_clone->location_id);

            CharacterJumpClone::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'jump_clone_id' => $jump_clone->jump_clone_id,
            ])->fill([
                'name' => $jump_clone->name ?? null,
                'location_id' => $jump_clone->location_id,
                'location_type' => $jump_clone->location_type,
                'implants' => $jump_clone->implants,
            ])->save();
        });

        // Remove invalid jump clones
        CharacterJumpClone::where('character_id', $this->getCharacterId())
            ->whereNotIn('jump_clone_id', collect($clone_informations->jump_clones)
                ->pluck('jump_clone_id')->flatten()->all())
            ->delete();

        $structure_batch->submitJobs($this->getToken());
    }
}
