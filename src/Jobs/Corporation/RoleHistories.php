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
use Seat\Eveapi\Models\Corporation\CorporationRoleHistory;

/**
 * Class RoleHistories.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class RoleHistories extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/roles/history/';

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
     * @var int
     */
    protected $page = 1;

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

            if ($this->shouldUseCache($response) &&
                CorporationRoleHistory::where('corporation_id', $this->getCorporationId())->exists())
                return;

            $roles = $response->getBody();

            collect($roles)->each(function ($role) {

                collect($role->old_roles)->each(function ($role_id) use ($role) {

                    CorporationRoleHistory::firstOrNew([
                        'corporation_id' => $this->getCorporationId(),
                        'character_id' => $role->character_id,
                        'changed_at' => carbon($role->changed_at),
                        'role_type' => $role->role_type,
                        'state' => 'old',
                        'role' => $role_id,
                    ])->fill([
                        'issuer_id' => $role->issuer_id,
                    ])->save();

                });

                collect($role->new_roles)->each(function ($role_id) use ($role) {

                    CorporationRoleHistory::firstOrNew([
                        'corporation_id' => $this->getCorporationId(),
                        'character_id' => $role->character_id,
                        'changed_at' => carbon($role->changed_at),
                        'role_type' => $role->role_type,
                        'state' => 'new',
                        'role' => $role_id,
                    ])->fill([
                        'issuer_id' => $role->issuer_id,
                    ])->save();

                });

            });
        } while ($this->nextPage($response->getPagesCount()));
    }
}
