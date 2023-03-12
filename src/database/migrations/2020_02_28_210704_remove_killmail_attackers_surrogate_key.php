<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2022 Leon Jacobs
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class RemoveKillmailAttackersSurrogateKey.
 */
class RemoveKillmailAttackersSurrogateKey extends Migration
{
    public function up()
    {
        Schema::table('killmail_attackers', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
            $table->string('attacker_hash')->after('id');
        });

        $count = DB::table('killmail_attackers')->count();

        $output = new ConsoleOutput();
        $progress = new ProgressBar($output, $count);

        DB::table('killmail_attackers')
            ->orderBy('killmail_id')
            ->chunk(200, function ($attackers) use ($progress) {
                $attackers->each(function ($attacker) use ($progress) {
                    $hash = md5(serialize([
                        $attacker->character_id,
                        $attacker->corporation_id,
                        $attacker->alliance_id,
                        $attacker->faction_id,
                    ]));

                    DB::table('killmail_attackers')
                        ->where('killmail_id', $attacker->killmail_id)
                        ->where('character_id', $attacker->character_id)
                        ->where('corporation_id', $attacker->corporation_id)
                        ->where('alliance_id', $attacker->alliance_id)
                        ->where('faction_id', $attacker->faction_id)
                        ->update([
                            'attacker_hash' => $hash,
                        ]);

                    $progress->advance();
                });
            });

        // remove duplicate entries using killmail_id and attacker hash as pivot
        DB::statement('DELETE a FROM killmail_attackers a INNER JOIN killmail_attackers b WHERE a.id > b.id AND a.killmail_id = b.killmail_id AND a.attacker_hash = b.attacker_hash');

        Schema::table('killmail_attackers', function (Blueprint $table) {
            $table->unique(['killmail_id', 'attacker_hash']);
        });

        $progress->finish();
        $output->writeln('');
    }

    public function down()
    {
        Schema::table('killmail_attackers', function (Blueprint $table) {
            $table->dropUnique(['killmail_id', 'attacker_hash']);
            $table->dropColumn('id');

            $table->dropColumn('attacker_hash');
        });
    }
}
