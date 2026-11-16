<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_audit_logs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedInteger('connection_id')->nullable()->index();
    $table->unsignedInteger('user_id')->nullable()->index();
    $table->string('operation', 40)->index();
    $table->string('tool_name', 120)->nullable()->index();
    $table->string('permission', 120)->nullable();
    $table->string('permission_decision', 30)->index();
    $table->string('status', 20)->index();
    $table->string('error_code', 60)->nullable();
    $table->text('error_message')->nullable();
    $table->unsignedInteger('duration_ms')->nullable();
    $table->unsignedBigInteger('approval_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->string('request_id', 64)->nullable()->index();
    $table->text('meta')->nullable();
    $table->dateTime('created_at')->index();
});
