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

use Seat\Eveapi\Jobs\AbstractCharacterJob;
use Seat\Eveapi\Models\Character\CharacterCorporationHistory;

class CorporationHistory extends AbstractCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/corporationhistory/';

    /**
     * @var int
     */
    protected $version = 'v2';

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

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterCorporationHistory::where('character_id', $this->getCharacterId())->exists())
            return;

        $corporations = collect($response->getBody());

        $corporations->each(function ($corporation) {

            CharacterCorporationHistory::firstOrCreate([
                'character_id' => $this->getCharacterId(),
                'record_id' => $corporation->record_id,
            ], [
                'start_date' => carbon($corporation->start_date),
                'corporation_id' => $corporation->corporation_id,
                'is_deleted' => $corporation->is_deleted ?? false,
            ]);
        });
    }
}
