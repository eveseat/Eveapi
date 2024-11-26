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

namespace Seat\Eveapi\Jobs\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Mapping\Structures\CorporationStructureMapping;
use Seat\Eveapi\Models\Corporation\CorporationStructure;
use Seat\Eveapi\Models\Corporation\CorporationStructureService;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Models\Universe\UniverseStructure;

/**
 * Class Structures.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Structures extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/structures/';

    /**
     * @var string
     */
    protected $version = 'v4';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_structures.v1';

    /**
     * @var array
     */
    protected $roles = ['Station_Manager'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'structure'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $known_structures;

    /**
     * Structures constructor.
     *
     * @param  int  $corporation_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $corporation_id, RefreshToken $token)
    {
        $this->known_structures = collect();

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

        do {

            $response = $this->retrieve([
                'corporation_id' => $this->getCorporationId(),
            ]);

            $structures = $response->getBody();

            collect($structures)->each(function ($structure) {

                // Ensure that we have an entry for this structure_id in the
                // UniverseStructures model. We set the name to unknown for now
                // but this will update when that models updater runs.
                $model = UniverseStructure::firstOrNew([
                    'structure_id' => $structure->structure_id,
                ]);

                // Persist the structure only if it doesn't already exist
                if (! $model->exists) {
                    $model->fill([
                        'solar_system_id' => $structure->system_id,
                        'type_id' => $structure->type_id,
                        'name' => $structure->name,
                        'x' => 0.0,
                        'y' => 0.0,
                        'z' => 0.0,
                    ])->save();
                }

                if ($model->name != $structure->name) {
                    $model->fill([
                        'name' => $structure->name,
                    ])->save();
                }

                // Persist the structure only if it doesn't already exists
                if (! $model->exists) $model->save();

                $model = CorporationStructure::firstOrNew([
                    'structure_id' => $structure->structure_id,
                ]);

                CorporationStructureMapping::make($model, $structure)->save();

                if (property_exists($structure, 'services')) {

                    collect($structure->services)->each(function ($service) use ($structure) {

                        CorporationStructureService::firstOrNew([
                            'structure_id' => $structure->structure_id,
                            'name' => $service->name,
                        ])->fill([
                            'state' => $service->state,
                        ])->save();
                    });

                    // Cleanup Services that may no longer be applicable to this structure.
                    CorporationStructureService::where('structure_id', $structure->structure_id)
                        ->whereNotIn('name', collect($structure->services)
                            ->pluck('name')->flatten()->all())
                        ->delete();

                } else {

                    // If no services are defined on this structure, remove all of the
                    // ones we might have in the database.
                    CorporationStructureService::where('structure_id', $structure->structure_id)
                        ->delete();
                }

                $this->known_structures->push($structure->structure_id);
            });

        } while ($this->nextPage($response->getPagesCount()));

        $outdated_structure = CorporationStructure::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('structure_id', $this->known_structures->flatten()->all())
            ->get();

        // Cleanup services and structures that were not in the response.
        CorporationStructureService::whereIn('structure_id', $outdated_structure->pluck('structure_id')->toArray())
            ->delete();

        CorporationStructure::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('structure_id', $this->known_structures->flatten()->all())
            ->delete();
    }
}
