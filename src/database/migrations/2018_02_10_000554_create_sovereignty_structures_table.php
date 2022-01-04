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
use Illuminate\Support\Facades\Schema;

class CreateSovereigntyStructuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('sovereignty_structures', function (Blueprint $table) {

            $table->bigInteger('structure_id');
            $table->integer('structure_type_id');
            $table->bigInteger('alliance_id');
            $table->integer('solar_system_id');
            $table->float('vulnerability_occupancy_level')->nullable();
            $table->dateTime('vulnerable_start_time')->nullable();
            $table->dateTime('vulnerable_end_time')->nullable();

            $table->primary('structure_id');
            $table->index('structure_type_id');
            $table->index('alliance_id');
            $table->index('solar_system_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::dropIfExists('sovereignty_structures');
    }
}
