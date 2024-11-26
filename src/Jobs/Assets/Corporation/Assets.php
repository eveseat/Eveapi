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

namespace Seat\Eveapi\Jobs\Assets\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Mapping\Assets\AssetMapping;
use Seat\Eveapi\Models\Assets\CorporationAsset;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Assets.
 *
 * @package Seat\Eveapi\Jobs\Assets\Corporation
 */
class Assets extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/assets/';

    /**
     * @var string
     */
    protected $version = 'v5';

    /**
     * @var string
     */
    protected $scope = 'esi-assets.read_corporation_assets.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'asset'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Assets constructor.
     *
     * @param  int  $corporation_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $corporation_id, RefreshToken $token)
    {
        parent::__construct($corporation_id, $token);
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $start = now();

        $structure_batch = new StructureBatch();

        while (true) {

            $response = $this->retrieve([
                'corporation_id' => $this->getCorporationId(),
            ]);

            $assets = collect($response->getBody());

            $assets->chunk(1000)->each(function ($chunk) use ($structure_batch, $start) {

                $chunk->each(function ($asset) use ($structure_batch, $start) {

                    $model = CorporationAsset::firstOrNew([
                        'item_id' => $asset->item_id,
                    ]);

                    // The model returned here will be modified based on the esi data.
                    // However Laravel is smart and the isClean() method will only return false
                    // If the esi fields have changed. Simply applying the same data keeps a clean model.
                    $model = AssetMapping::make($model, $asset, [
                        'corporation_id' => function () {
                            return $this->getCorporationId();
                        },
                    ]);

                    //make sure that the location is loaded if it is in a station or citadel
                    if (in_array($asset->location_flag, StructureBatch::RESOLVABLE_LOCATION_FLAGS) && in_array($asset->location_type, StructureBatch::RESOLVABLE_LOCATION_TYPES)) {
                        $structure_batch->addStructure($asset->location_id);
                    }

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
            });

            if (! $this->nextPage($response->getPagesCount()))
                break;
        }

        // Cleanup old assets
        CorporationAsset::where('corporation_id', $this->getCorporationId())
            ->where('updated_at', '<', $start)
            ->delete();

        // schedule jobs for structures
        $structure_batch->submitJobs($this->getToken());
    }
}
