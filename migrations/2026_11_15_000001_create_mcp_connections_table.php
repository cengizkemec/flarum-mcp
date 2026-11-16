<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_connections', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->text('description')->nullable();
    $table->unsignedInteger('user_id')->nullable()->index();
    $table->string('permission_profile', 80)->default('minimum');
    $table->text('custom_permissions')->nullable();
    $table->char('token_hash', 64)->unique();
    $table->string('token_prefix', 16);
    $table->dateTime('token_expires_at')->nullable();
    $table->dateTime('token_revoked_at')->nullable();
    $table->string('status', 20)->default('active')->index();
    $table->unsignedInteger('rate_limit_per_minute')->default(60);
    $table->unsignedInteger('rate_limit_per_hour')->default(1000);
    $table->unsignedInteger('rate_limit_per_day')->default(10000);
    $table->boolean('require_approval')->default(false);
    $table->dateTime('last_used_at')->nullable();
    $table->string('last_used_ip', 45)->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
