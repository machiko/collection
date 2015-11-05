<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToNewsSimilars extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('news_similars', function($table) {
            $table->double('value')->after('id');
            $table->string('url_ids')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('news_similars', function($table) {
            $table->dropColumn(['url_ids', 'value']);
        });
    }
}
