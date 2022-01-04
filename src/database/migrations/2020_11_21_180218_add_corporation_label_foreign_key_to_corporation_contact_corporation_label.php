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
 * Class AddCorporationLabelForeignKeyToCorporationContactCorporationLabel.
 */
class AddCorporationLabelForeignKeyToCorporationContactCorporationLabel extends Migration
{
    public function up()
    {
        Schema::table('corporation_contact_corporation_label', function (Blueprint $table) {

            // preventatively drop any orphan corporation contact corporation label row
            DB::table('corporation_contact_corporation_label')
                ->leftJoin('corporation_labels', 'corporation_contact_corporation_label.corporation_contact_id', '=', 'corporation_labels.id')
                ->whereNull('corporation_labels.id')
                ->delete();

            $table->foreign('corporation_label_id', 'fk_corporation_labels')
                ->references('id')
                ->on('corporation_labels')
                ->onUpdate('no action')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('corporation_contact_corporation_label', function (Blueprint $table) {
            $table->dropForeign('fk_corporation_labels');
        });
    }
}
