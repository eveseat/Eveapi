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
use Seat\Eveapi\Models\Character\CharacterRole;

/**
 * Class Roles.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class Roles extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/roles/';

    /**
     * @var int
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_corporation_roles.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'role'];

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

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) && CharacterRole::where('character_id', $this->getCharacterId())->exists())
            return;

        $roles = $response->getBody();

        foreach (['roles', 'roles_at_hq', 'roles_at_base', 'roles_at_other'] as $scope) {

            // Add new roles for this scope
            collect($roles->$scope)->each(function ($role) use ($scope) {

                CharacterRole::firstOrCreate([
                    'character_id' => $this->getCharacterId(),
                    'role' => $role,
                    'scope' => $scope,
                ]);
            });

            // Remove roles that may no longer exist for this scope
            CharacterRole::where('character_id', $this->getCharacterId())
                ->whereScope($scope)
                ->whereNotIn('role', $roles->$scope)
                ->delete();
        }
    }
}
