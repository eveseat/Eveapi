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

namespace Seat\Eveapi\Jobs\Fittings\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\Fittings\CharacterFitting;
use Seat\Eveapi\Models\Fittings\CharacterFittingItem;

/**
 * Class Fittings.
 *
 * @package Seat\Eveapi\Jobs\FIttings\Character
 */
class Fittings extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/fittings/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-fittings.read_fittings.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'fitting'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterFitting::where('character_id', $this->getCharacterId())->exists())
            return;

        $fittings = $response->getBody();

        collect($fittings)->each(function ($fitting) {

            CharacterFitting::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'fitting_id' => $fitting->fitting_id,
            ])->fill([
                'name' => $fitting->name,
                'description' => $fitting->description,
                'ship_type_id' => $fitting->ship_type_id,
            ])->save();

            // Check that we have this fittings items in the database. If we don't,
            // just add it. Cleanups of the fittings table will cascade deletes to
            // these items.
            if (CharacterFittingItem::where('fitting_id', $fitting->fitting_id)->count() === 0) {

                collect($fitting->items)->each(function ($item) use ($fitting) {

                    CharacterFittingItem::firstOrCreate([
                        'fitting_id' => $fitting->fitting_id,
                        'type_id' => $item->type_id,
                        'flag' => $item->flag,
                    ], [
                        'quantity' => $item->quantity,
                    ]);
                });
            }
        });

        // Cleanup fittings.
        CharacterFitting::where('character_id', $this->getCharacterId())
            ->whereNotIn('fitting_id', collect($fittings)->pluck('fitting_id')->flatten()->all())
            ->delete();
    }
}
