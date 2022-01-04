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

/**
 * Class CreateMoonsTable.
 */
class CreateMoonsTable extends Migration
{
    public function up()
    {
        Schema::create('moons', function (Blueprint $table) {
            $table->integer('moon_id');
            $table->integer('planet_id');
            $table->integer('system_id');
            $table->integer('constellation_id');
            $table->integer('region_id');
            $table->string('name');
            $table->integer('type_id');
            $table->double('x');
            $table->double('y');
            $table->double('z');
            $table->double('radius');
            $table->integer('celestial_index');
            $table->integer('orbit_index');

            $table->primary(['moon_id']);
            $table->index(['region_id']);
            $table->index(['constellation_id']);
            $table->index(['system_id']);
            $table->index(['planet_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('moons');
    }
}
