<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_api_credential_secrets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('developer_api_credential_id');
            $table->string('scope', 64)->default('default');
            $table->string('secret_identifier', 128)->unique();
            $table->string('secret_last_four', 8)->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('developer_api_credential_id')
                ->references('id')
                ->on('developer_api_credentials')
                ->onDelete('cascade');

            $table->index(['developer_api_credential_id', 'scope'], 'developer_api_secret_scope_index');
        });

        Schema::create('developer_api_credential_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('developer_api_credential_id');
            $table->string('action', 100);
            $table->json('meta')->nullable();
            $table->string('performed_by_type', 50)->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->timestamps();

            $table->foreign('developer_api_credential_id')
                ->references('id')
                ->on('developer_api_credentials')
                ->onDelete('cascade');
        });

        Schema::table('developer_api_credentials', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('status');
        });

        $appKey = config('app.key');
        if (Str::startsWith($appKey, 'base64:')) {
            $appKey = base64_decode(Str::after($appKey, 'base64:')) ?: $appKey;
        }

        $existingCredentials = DB::table('developer_api_credentials')
            ->select('id', 'client_secret', 'mode', 'created_at', 'updated_at')
            ->whereNotNull('client_secret')
            ->get();

        foreach ($existingCredentials as $credential) {
            $plainSecret = $credential->client_secret;
            $identifier = hash_hmac('sha256', $plainSecret, $appKey);
            $lastFour = mb_substr($plainSecret, -4);

            DB::table('developer_api_credential_secrets')->insert([
                'developer_api_credential_id' => $credential->id,
                'scope' => $credential->mode ?? 'default',
                'secret_identifier' => $identifier,
                'secret_last_four' => $lastFour,
                'created_at' => $credential->created_at ?? now(),
                'updated_at' => $credential->updated_at ?? now(),
            ]);

            DB::table('developer_api_credentials')
                ->where('id', $credential->id)
                ->update(['client_secret' => $identifier]);
        }
    }

    public function down(): void
    {
        Schema::table('developer_api_credentials', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });

        Schema::dropIfExists('developer_api_credential_logs');
        Schema::dropIfExists('developer_api_credential_secrets');
    }
};

