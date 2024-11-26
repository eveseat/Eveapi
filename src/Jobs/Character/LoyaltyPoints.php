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
use Seat\Eveapi\Jobs\Corporation\Info as CorporationInfoJob;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Character\CharacterLoyaltyPoints;
use Seat\Eveapi\Models\Corporation\CorporationInfo;

/**
 * Class Title.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class LoyaltyPoints extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/loyalty/points/';

    /**
     * @var int
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_loyalty.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'loyalty points'];

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
        //get character for whom to get the lp data
        $character_id = $this->getCharacterId();
        $character = CharacterInfo::find($character_id);

        //if the character doesn't exist, stop here
        if (is_null($character)){
            $this->fail();

            return;
        }

        //load lp data
        $response = $this->retrieve([
            'character_id' => $character_id,
        ]);

        if ($this->shouldUseCache($response) && CharacterLoyaltyPoints::where('character_id', $character_id)->exists()) return;

        //get the lp data as collection
        $loyalty_points = collect($response->getBody());

        //store the lp data
        $character->loyalty_points()->sync($loyalty_points->mapWithkeys(function ($corporation_loyalty) {
            //load npc corp data if we don't know the corporation. Per default, seat never loads data about npy corporations
            if(! CorporationInfo::where('corporation_id', $corporation_loyalty->corporation_id)->exists()){
                CorporationInfoJob::dispatch($corporation_loyalty->corporation_id);
            }

            return [$corporation_loyalty->corporation_id => ['amount' => $corporation_loyalty->loyalty_points]];
        }));

    }
}
