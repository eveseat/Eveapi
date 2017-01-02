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

namespace Seat\Eveapi\Api\Character;

use Seat\Eveapi\Api\Base;
use Seat\Eveapi\Models\Character\Standing;

/**
 * Class Standings.
 * @package Seat\Eveapi\Api\Character
 */
class Standings extends Base
{
    /**
     * Run the Update.
     *
     * @return mixed|void
     */
    public function call()
    {

        $pheal = $this->setScope('char')->getPheal();

        foreach ($this->api_info->characters as $character) {

            $this->writeJobLog('standings',
                'Processing characterID: ' . $character->characterID);

            $result = $pheal->Standings([
                'characterID' => $character->characterID, ]);

            // We will receive 3 standings types from the API.
            // All of them are recorded in the same table, and
            // are distinguished by the type enum column.

            // Agents Standings
            foreach ($result->characterNPCStandings->agents as $standing)
                $this->_update_standing(
                    $character->characterID, $standing, 'agents');

            // NPCCorporations Standings
            foreach ($result->characterNPCStandings->NPCCorporations as $standing)
                $this->_update_standing(
                    $character->characterID, $standing, 'NPCCorporations');

            // Factionss Standings
            foreach ($result->characterNPCStandings->factions as $standing)
                $this->_update_standing(
                    $character->characterID, $standing, 'factions');
        }

    }

    /**
     * Update the character standing based on the type.
     *
     * @param $characterID
     * @param $standing
     * @param $type
     */
    public function _update_standing($characterID, $standing, $type)
    {

        $standing_info = Standing::firstOrNew([
            'characterID' => $characterID,
            'fromID'      => $standing->fromID, ]);

        $standing_info->fill([
            'type'     => $type,
            'fromName' => $standing->fromName,
            'standing' => $standing->standing,
        ]);

        $standing_info->save();

    }
}
