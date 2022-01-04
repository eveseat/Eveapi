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

/**
 * Class RemoveUniqueKeyFromKillmailVictimItemsTable.
 */
class RemoveUniqueKeyFromKillmailVictimItemsTable extends Migration
{
    public function up()
    {
        Schema::table('killmail_victim_items', function (Blueprint $table) {
            $table->dropUnique(['killmail_id', 'item_type_id', 'flag']);
        });
    }

    public function down()
    {
        Schema::table('killmail_victim_items', function (Blueprint $table) {
            $table->bigIncrements('id');
        });

        DB::statement('DELETE a FROM killmail_victim_items a INNER JOIN killmail_victim_items b WHERE a.id < b.id AND a.killmail_id = b.killmail_id AND a.item_type_id = b.item_type_id AND a.flag = b.flag');

        Schema::table('killmail_victim_items', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->unique(['killmail_id', 'item_type_id', 'flag']);
        });
    }
}
