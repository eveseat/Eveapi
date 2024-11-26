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
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Mapping\Industry\BlueprintMapping;
use Seat\Eveapi\Models\Character\CharacterBlueprint;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Blueprints.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class Blueprints extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/blueprints/';

    /**
     * @var int
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_blueprints.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'industry'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Record of ID's that should be removed from this jobs
     * Model.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $cleanup_ids;

    /**
     * Blueprints constructor.
     *
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(RefreshToken $token)
    {

        $this->cleanup_ids = collect();

        parent::__construct($token);
    }

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

        // Start an infinite loop for the paged requests.
        while (true) {

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            $blueprints = collect($response->getBody());

            // Process the blueprints from the response
            $blueprints->chunk(100)->each(function ($chunk) use ($structure_batch) {

                $chunk->each(function ($blueprint) use ($structure_batch) {

                    if (in_array($blueprint->location_flag, StructureBatch::RESOLVABLE_LOCATION_FLAGS)) {
                        $structure_batch->addStructure($blueprint->location_id);
                    }

                    $model = CharacterBlueprint::firstOrNew([
                        'item_id' => $blueprint->item_id,
                    ]);

                    // Exit early in the case that the model exists.
                    if ($model->exists) return;

                    BlueprintMapping::make($model, $blueprint, [
                        'character_id' => function () {
                            return $this->getCharacterId();
                        },
                    ])->save();
                });
            });

            // Add item ID's we should remove from the database.
            $this->cleanup_ids->push($blueprints->pluck('item_id'));

            // Check for pages left.
            if (! $this->nextPage($response->getPagesCount()))
                break;
        }

        $this->cleanup();
        $structure_batch->submitJobs($this->getToken());
    }

    /**
     * Removes older entries from blueprints table.
     *
     * @throws \Exception
     */
    private function cleanup(): void
    {

        CharacterBlueprint::where('character_id', $this->getCharacterId())
            ->whereNotIn('item_id', $this->cleanup_ids->flatten()->unique()->all())
            ->delete();
    }
}
