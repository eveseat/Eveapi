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

namespace Seat\Eveapi\Jobs\Contacts\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Models\Contacts\CorporationContact;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Contacts.
 *
 * @package Seat\Eveapi\Jobs\Contacts\Corporation
 */
class Contacts extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/contacts/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_contacts.v1';

    /**
     * @var array
     */
    protected $tags = ['corporation', 'contact'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $known_contact_ids;

    /**
     * Contacts constructor.
     *
     * @param  int  $corporation_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $corporation_id, RefreshToken $token)
    {
        $this->known_contact_ids = collect();

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

            $contacts = $response->getBody();

            $this->known_contact_ids->push(collect($contacts)
                ->pluck('contact_id')->flatten()->all());

                // In this case, the cache guard here  will not save network ops, but should save the DB
            if ($this->shouldUseCache($response) &&
                CorporationContact::where('corporation_id', $this->getCorporationId())->exists())
                continue;

            collect($contacts)->each(function ($contact) {

                CorporationContact::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'contact_id' => $contact->contact_id,
                ])->fill([
                    'standing' => $contact->standing,
                    'contact_type' => $contact->contact_type,
                    'is_watched' => $contact->is_watched ?? false,
                    'label_ids' => $contact->label_ids ?? null,
                ])->save();
            });

        } while ($this->nextPage($response->getPagesCount()));

        // Cleanup old contacts
        CorporationContact::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('contact_id', $this->known_contact_ids->flatten()->all())
            ->delete();
    }
}
