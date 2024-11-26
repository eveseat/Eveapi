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

namespace Seat\Eveapi\Jobs\Assets\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Mapping\Assets\AssetMapping;
use Seat\Eveapi\Models\Assets\CharacterAsset;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Assets.
 *
 * @package Seat\Eveapi\Jobs\Assets\Character
 */
class Assets extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/assets/';

    /**
     * @var string
     */
    protected $version = 'v5';

    /**
     * @var string
     */
    protected $scope = 'esi-assets.read_assets.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'asset'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Assets constructor.
     *
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(RefreshToken $token)
    {
        parent::__construct($token);
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle(): void
    {
        parent::handle();

        $start = now();

        $structure_batch = new StructureBatch();

        while (true) {

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            $assets = collect($response->getBody());

            $assets->each(function ($asset) use ($structure_batch, $start) {

                $model = CharacterAsset::firstOrNew([
                    'item_id' => $asset->item_id,
                ]);

                //make sure that the location is loaded if it is in a station or citadel
                if (in_array($asset->location_flag, StructureBatch::RESOLVABLE_LOCATION_FLAGS) && in_array($asset->location_type, StructureBatch::RESOLVABLE_LOCATION_TYPES)) {
                    $structure_batch->addStructure($asset->location_id);
                }

                // The model returned here will be modified based on the esi data.
                // However Laravel is smart and the isClean() method will only return false
                // If the esi fields have changed. Simply applying the same data keeps a clean model.
                $model = AssetMapping::make($model, $asset, [
                    'character_id' => function () {
                        return $this->getCharacterId();
                    },
                ]);

                if ($model->exists && $model->isClean()){
                    // No ESI data updated, just touch the timestamp
                    $model->updated_at = $start;

                    // There is no point triggering events when nothing has changed
                    $model->saveQuietly();
                } else {
                    // ESI data has changed. So just save the model.
                    // This will update the timestamp and also trigger events.
                    $model->save();
                }
            });

            if (! $this->nextPage($response->getPagesCount()))
                break;
        }

        // Cleanup old assets
        CharacterAsset::where('character_id', $this->getCharacterId())
            ->where('updated_at', '<', $start)
            ->delete();

        // schedule jobs for structures
        $structure_batch->submitJobs($this->getToken());
    }
}
