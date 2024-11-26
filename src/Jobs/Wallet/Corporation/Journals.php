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

namespace Seat\Eveapi\Jobs\Wallet\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Mapping\Financial\WalletJournalMapping;
use Seat\Eveapi\Models\Wallet\CorporationWalletBalance;
use Seat\Eveapi\Models\Wallet\CorporationWalletJournal;

/**
 * Class Journals.
 *
 * @package Seat\Eveapi\Jobs\Wallet\Corporation
 */
class Journals extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/wallets/{division}/journal/';

    /**
     * @var string
     */
    protected $version = 'v4';

    /**
     * @var string
     */
    protected $scope = 'esi-wallet.read_corporation_wallets.v1';

    /**
     * @var array
     */
    protected $roles = ['Accountant', 'Junior_Accountant'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'wallet'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var int
     */
    protected $last_known_entry_id = 0;

    /**
     * @var bool
     */
    protected $at_last_entry = false;

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

        CorporationWalletBalance::where('corporation_id', $this->getCorporationId())->get()
            ->each(function ($balance) {

                // retrieve last known entry for the current division and active corporation
                $last_known_entry = CorporationWalletJournal::where('corporation_id', $this->getCorporationId())
                                                            ->where('division', $balance->division)
                                                            ->orderBy('date', 'desc')
                                                            ->first();

                $this->last_known_entry_id = is_null($last_known_entry) ? 0 : $last_known_entry->reference_id;

                // Perform a journal walk backwards to get all of the
                // entries as far back as possible. When the response from
                // ESI is empty, we can assume we have everything.
                do {

                    $response = $this->retrieve([
                        'corporation_id' => $this->getCorporationId(),
                        'division' => $balance->division,
                    ]);

                    $entries = collect($response->getBody());

                    // If we have no more entries, break the loop.
                    if ($entries->count() === 0)
                        break;

                    $entries->chunk(1000)->each(function ($chunk) use ($balance) {

                        // if we've reached the last known entry - abort the process
                        if ($this->at_last_entry)
                            return false;

                        // if we have reached the last known entry, exclude all entries which are lower or equal to the
                        // last known entry and flag the reached status.
                        if ($chunk->where('id', $this->last_known_entry_id)->isNotEmpty()) {
                            $chunk = $chunk->where('id', '>', $this->last_known_entry_id);

                            $this->at_last_entry = true;
                        }

                        $chunk->each(function ($entry) use ($balance) {

                            $model = CorporationWalletJournal::firstOrNew([
                                'corporation_id' => $this->getCorporationId(),
                                'division' => $balance->division,
                                'id' => $entry->id,
                            ]);

                            WalletJournalMapping::make($model, $entry, [
                                'corporation_id' => function () {
                                    return $this->getCorporationId();
                                },
                                'division' => function () use ($balance) {
                                    return $balance->division;
                                },
                            ])->save();
                        });
                    });

                    // in case the last known entry has been reached or we non longer have pages, terminate the job.
                } while ($this->nextPage($response->getPagesCount()) || $this->at_last_entry);

                // Reset the page for the next wallet division.
                $this->page = 1;

                // Reset the last known entry for the next wallet division.
                $this->last_known_entry_id = 0;

                // Reset the last known entry status for the next wallet division.
                $this->at_last_entry = false;
            });
    }
}
