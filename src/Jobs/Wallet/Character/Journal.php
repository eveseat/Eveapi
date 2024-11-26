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

namespace Seat\Eveapi\Jobs\Wallet\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Financial\WalletJournalMapping;
use Seat\Eveapi\Models\Wallet\CharacterWalletJournal;

/**
 * Class Journal.
 *
 * @package Seat\Eveapi\Jobs\Wallet\Character
 */
class Journal extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/wallet/journal/';

    /**
     * @var string
     */
    protected $version = 'v6';

    /**
     * @var string
     */
    protected $scope = 'esi-wallet.read_character_wallet.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'wallet'];

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
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        // retrieve latest known journal entry for the active character.
        $last_known_entry = CharacterWalletJournal::where('character_id', $this->getCharacterId())
                                                  ->orderBy('date', 'desc')
                                                  ->first();

        $this->last_known_entry_id = is_null($last_known_entry) ? 0 : $last_known_entry->id;

        // Perform a journal walk backwards to get all of the
        // entries as far back as possible. When the response from
        // ESI is empty, we can assume we have everything.
        do {

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            $entries = collect($response->getBody());

            // If we have no more entries, break the loop.
            if ($entries->count() === 0)
                break;

            $entries->each(function ($entry) {

                // if we've reached the last known entry - abort the process
                if ($entry->id == $this->last_known_entry_id) {
                    $this->at_last_entry = true;

                    return false;
                }

                $journal_entry = CharacterWalletJournal::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'id' => $entry->id,
                ]);

                // If this journal entry has already been recorded,
                // move on to the next.
                if ($journal_entry->exists)
                    return;

                WalletJournalMapping::make($journal_entry, $entry, [
                    'character_id' => function () {
                        return $this->getCharacterId();
                    },
                ])->save();

            });

            // in case the last known entry has been reached or we non longer have pages, terminate the job.
        } while ($this->nextPage($response->getPagesCount()) || $this->at_last_entry);
    }
}
