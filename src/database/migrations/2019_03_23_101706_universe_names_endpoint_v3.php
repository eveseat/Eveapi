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

class UniverseNamesEndpointV3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('universe_names', function (Blueprint $table) {
            $table->renameColumn('category', 'category_v2');
        });

        Schema::table('universe_names', function (Blueprint $table) {
            $table->enum('category', ['alliance','character','constellation','corporation','inventory_type','region','solar_system','station','faction'])->after('category_v2');
        });

        DB::table('universe_names')
            ->update(['category' => DB::raw('"category_v2"')]);

        Schema::table('universe_names', function (Blueprint $table) {
            $table->dropColumn('category_v2');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('universe_names', function (Blueprint $table) {
            $table->renameColumn('category', 'category_v3');
        });

        Schema::table('universe_names', function (Blueprint $table) {
            $table->enum('category', ['alliance','character','constellation','corporation','inventory_type','region','solar_system','station'])->after('category_v3');
        });

        DB::table('universe_names')
            ->where('category_v3', 'faction')
            ->delete();

        DB::table('universe_names')
            ->update(['category' => DB::raw('"category_v3"')]);

        Schema::table('universe_names', function (Blueprint $table) {
            $table->dropColumn('category_v3');
        });
    }
}
