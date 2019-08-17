<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
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
 * Class RemoveCorporationContactLabelsSurrogateKey.
 */
class RemoveCorporationContactLabelsSurrogateKey extends Migration
{
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('corporation_contact_labels', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::rename('corporation_contact_labels', 'corporation_labels');

        Schema::table('corporation_labels', function (Blueprint $table) {
            $table->increments('id')->first();
            $table->unique(['corporation_id', 'label_id']);
            $table->renameColumn('label_name', 'name');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('corporation_labels', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropUnique(['corporation_id', 'label_id']);
            $table->renameColumn('name', 'label_name');
        });

        Schema::rename('corporation_labels', 'corporation_contact_labels');

        Schema::table('corporation_contact_labels', function (Blueprint $table) {
            $table->primary(['corporation_id', 'label_id']);
        });

        Schema::enableForeignKeyConstraints();
    }
}
