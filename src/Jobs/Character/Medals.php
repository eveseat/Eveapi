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

namespace Seat\Eveapi\Jobs\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Characters\MedalsMapping;
use Seat\Eveapi\Models\Character\CharacterMedal;

/**
 * Class Medals.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class Medals extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/medals/';

    /**
     * @var int
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_medals.v1';

    /**
     * @var array
     */
    protected $tags = ['character'];

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
            CharacterMedal::where('character_id', $this->getCharacterId())->exists())
            return;

        $medals = collect($response->getBody());

        $medals->each(function ($medal) {

            $model = CharacterMedal::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'medal_id' => $medal->medal_id,
            ]);

            MedalsMapping::make($model, $medal, [
                'character_id' => function () {
                    return $this->getCharacterId();
                },
                'graphics' => function () use ($medal) {
                    return json_encode($medal->graphics);
                },
            ])->save();
        });
    }
}
