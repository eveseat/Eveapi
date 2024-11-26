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
use Seat\Eveapi\Models\Corporation\CorporationTitle;
use Seat\Eveapi\Models\Corporation\CorporationTitleRole;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Titles.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Titles extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/titles/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_titles.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'role'];

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $known_titles;

    /**
     * @var array
     */
    protected $types = [
        'roles',
        'grantable_roles',
        'roles_at_hq',
        'grantable_roles_at_hq',
        'roles_at_base',
        'grantable_roles_at_base',
        'roles_at_other',
        'grantable_roles_at_other',
    ];

    /**
     * Titles constructor.
     *
     * @param  int  $corporation_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $corporation_id, RefreshToken $token)
    {
        $this->known_titles = collect();

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

        $response = $this->retrieve([
            'corporation_id' => $this->getCorporationId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CorporationTitle::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $titles = $response->getBody();

        collect($titles)->each(function ($title) {

            $title_model = CorporationTitle::firstOrNew([
                'corporation_id' => $this->getCorporationId(),
                'title_id' => $title->title_id,
            ])->fill([
                'name' => $title->name ?? sprintf('Untitled %d', (int) sqrt($title->title_id - 1)),
            ]);
            $title_model->save();

            collect($this->types)->each(function ($type) use ($title, $title_model) {

                if (! property_exists($title, $type))
                    return;

                collect($title->{$type})->each(function ($name) use ($title_model, $type) {

                    CorporationTitleRole::firstOrCreate([
                        'title_id' => $title_model->id,
                        'type' => $type,
                        'role' => $name,
                    ]);
                });

                CorporationTitleRole::where('title_id', $title_model->id)
                    ->where('type', $type)
                    ->whereNotIn('role', collect($title->{$type})->flatten()->all())
                    ->delete();
            });

            $this->known_titles->push($title->title_id);
        });

        CorporationTitle::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('title_id', $this->known_titles->flatten()->all())
            ->delete();
    }
}
