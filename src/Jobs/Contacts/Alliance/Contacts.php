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

namespace Seat\Eveapi\Jobs\Contacts\Alliance;

use Seat\Eveapi\Jobs\AbstractAuthAllianceJob;
use Seat\Eveapi\Models\Contacts\AllianceContact;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Contacts.
 *
 * @package Seat\Eveapi\Jobs\Contacts\Alliance
 */
class Contacts extends AbstractAuthAllianceJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/alliances/{alliance_id}/contacts/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-alliances.read_contacts.v1';

    /**
     * @var array
     */
    protected $tags = ['alliance', 'contact'];

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
     * @param  int  $alliance_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $alliance_id, RefreshToken $token)
    {
        $this->known_contact_ids = collect();

        parent::__construct($alliance_id, $token);
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
        do {

            $response = $this->retrieve([
                'alliance_id' => $this->getAllianceId(),
            ]);

            $contacts = $response->getBody();

            $this->known_contact_ids->push(collect($contacts)
                ->pluck('contact_id')->flatten()->all());

            // This wont save network calls, but should save DB writes
            if ($this->shouldUseCache($response) &&
                AllianceContact::where('alliance_id', $this->getAllianceId())->exists())
                continue; // This page has no changes so move onto the next page

            collect($contacts)->each(function ($contact) {

                AllianceContact::firstOrNew([
                    'alliance_id' => $this->getAllianceId(),
                    'contact_id' => $contact->contact_id,
                ])->fill([
                    'standing' => $contact->standing,
                    'contact_type' => $contact->contact_type,
                    'label_ids' => $contact->label_ids ?? null,
                ])->save();
            });

        } while ($this->nextPage($response->getPagesCount()));

        // Cleanup old contacts
        AllianceContact::where('alliance_id', $this->getAllianceId())
            ->whereNotIn('contact_id', $this->known_contact_ids->flatten()->all())
            ->delete();
    }
}
