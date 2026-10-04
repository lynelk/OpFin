<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('audmon_api_clients',function(Blueprint $t){
   $t->id();$t->string('name');$t->string('client_key')->unique();$t->string('secret_hash');$t->string('status')->default('active')->index();
   $t->json('scopes');$t->json('financial_space_ids')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamps();
  });
 }
 public function down():void{Schema::dropIfExists('audmon_api_clients');}
};
