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
 * Class UpdateCharacterAssetsV4.
 */
class UpdateCharacterAssetsV4 extends Migration
{
    public function up()
    {
        Schema::table('character_assets', function (Blueprint $table) {
            $table->renameColumn('location_type', 'location_type_v3');
        });

        Schema::table('character_assets', function (Blueprint $table) {
            $table->enum('location_type', ['station','solar_system','other', 'item'])->nullable(false);
        });

        DB::table('character_assets')
            ->update(['location_type' => DB::raw('"location_type_v3"')]);

        Schema::table('character_assets', function (Blueprint $table) {
            $table->dropColumn('location_type_v3');
        });
    }

    public function down()
    {
        Schema::table('character_assets', function (Blueprint $table) {
            $table->renameColumn('location_type', 'location_type_v4');
        });

        Schema::table('character_assets', function (Blueprint $table) {
            $table->enum('location_type', ['station','solar_system','other'])->nullable(false);
        });

        DB::table('character_assets')
            ->where('location_type_v4', 'item')
            ->delete();

        DB::table('character_assets')
            ->update(['location_type' => DB::raw('"location_type_v4"')]);

        Schema::table('character_assets', function (Blueprint $table) {
            $table->dropColumn('location_type_v4');
        });
    }
}