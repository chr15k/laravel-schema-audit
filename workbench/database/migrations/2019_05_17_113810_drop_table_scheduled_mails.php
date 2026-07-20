<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class DropTableScheduledMails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('scheduled_mails');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('scheduled_mails', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('event', 255)->default('');
            $table->dateTime('send_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('booking_id')->default(0);
        });
    }
}
