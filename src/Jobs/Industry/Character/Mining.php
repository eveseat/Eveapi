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

namespace Seat\Eveapi\Jobs\Industry\Character;

use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\Industry\CharacterMining;

/**
 * Class Mining.
 *
 * @package Seat\Eveapi\Jobs\Industry\Character
 */
class Mining extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/mining/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-industry.read_character_mining.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'industry'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        do {

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            if ($this->shouldUseCache($response) &&
                CharacterMining::where('character_id', $this->getCharacterId())->exists())
                continue;

            $entries = $response->getBody();

            collect($entries)->each(function ($ledger_entry) {

                // retrieve daily mined amount for current type, system and character
                $row = CharacterMining::select(DB::raw('SUM(quantity) as quantity'))
                    ->where('character_id', $this->getCharacterId())
                    ->where('date', $ledger_entry->date)
                    ->where('solar_system_id', $ledger_entry->solar_system_id)
                    ->where('type_id', $ledger_entry->type_id)
                    ->first();

                // get the current UTC time for potential new mining ledger entry
                $delta_time = carbon()->setTimezone('UTC')->toTimeString();

                // compute delta between daily knew mined amount and new daily mined amount
                $delta_quantity = $ledger_entry->quantity - (is_null($row) ? 0 : $row->quantity);

                // in case delta is 0, we skip the entry since there are no needs to store empty value
                if ($delta_quantity != 0) {

                    // in case the entry date does not match with the current date, we reset entry time to midnight as
                    // a last entry
                    if ($ledger_entry->date != carbon()->setTimezone('UTC')->toDateString())
                        $delta_time = '23:59:59';

                    // finally, we create the new entry
                    CharacterMining::updateOrCreate([
                        'character_id' => $this->getCharacterId(),
                        'date' => $ledger_entry->date,
                        'time' => $delta_time,
                        'solar_system_id' => $ledger_entry->solar_system_id,
                        'type_id' => $ledger_entry->type_id,
                    ], [
                        'quantity' => $delta_quantity,
                    ]);

                }
            });
        } while ($this->nextPage($response->getPagesCount()));
    }
}
