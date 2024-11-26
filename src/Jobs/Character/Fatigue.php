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
use Seat\Eveapi\Models\Character\CharacterFatigue;

/**
 * Class Fatigue.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class Fatigue extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/fatigue/';

    /**
     * @var int
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_fatigue.v1';

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

        $model = CharacterFatigue::firstOrNew([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) && $model->exists) return;

        $fatigue = $response->getBody();

        $model->fill([
            'last_jump_date' => property_exists($fatigue, 'last_jump_date') ?
                carbon($fatigue->last_jump_date) : null,
            'jump_fatigue_expire_date' => property_exists($fatigue, 'jump_fatigue_expire_date') ?
                carbon($fatigue->jump_fatigue_expire_date) : null,
            'last_update_date' => property_exists($fatigue, 'last_update_date') ?
                carbon($fatigue->last_update_date) : null,
        ])->save();
    }
}
