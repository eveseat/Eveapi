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
use Seat\Eveapi\Models\Corporation\CorporationRole;

/**
 * Class Roles.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Roles extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/roles/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_corporation_membership.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'role'];

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
            CorporationRole::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $roles = $response->getBody();

        $returned_characters_ids = collect();

        collect($roles)->each(function ($role) use ($returned_characters_ids) {

            $returned_characters_ids->push($role->character_id);

            collect($this->types)->each(function ($type) use ($role) {

                // in case the searched type do not exist, exit the current type loop
                // make sure we drop all roles tied to both this character and corporation from that type
                if (! property_exists($role, $type)) {
                    CorporationRole::where('corporation_id', $this->getCorporationId())
                        ->where('character_id', $role->character_id)
                        ->where('type', $type)
                        ->delete();

                    return true;
                }

                collect($role->{$type})->each(function ($name) use ($role, $type) {

                    CorporationRole::firstOrCreate([
                        'corporation_id' => $this->getCorporationId(),
                        'character_id' => $role->character_id,
                        'type' => $type,
                        'role' => $name,
                    ]);

                });

                CorporationRole::where('corporation_id', $this->getCorporationId())
                    ->where('character_id', $role->character_id)
                    ->where('type', $type)
                    ->whereNotIn('role', collect($role->{$type})->flatten()->all())
                    ->delete();
            });
        });

        // drop all role entries related to characters which have not be returned by ESI
        CorporationRole::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('character_id', $returned_characters_ids)
            ->delete();
    }
}
