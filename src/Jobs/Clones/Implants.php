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
use Seat\Eveapi\Models\Clones\CharacterImplant;

/**
 * Class Implants.
 *
 * @package Seat\Eveapi\Jobs\Clones
 */
class Implants extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/implants/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-clones.read_implants.v1';

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

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterImplant::where('character_id', $this->getCharacterId())->exists())
            return;

        $implants = $response->getBody();

        collect($implants)->each(function ($implant) {

            CharacterImplant::firstOrCreate([
                'character_id' => $this->getCharacterId(),
                'type_id' => $implant,
            ]);
        });

        CharacterImplant::where('character_id', $this->getCharacterId())
            ->whereNotIn('type_id', collect($implants)->flatten()->all())
            ->delete();
    }
}
