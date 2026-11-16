<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_approvals', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('connection_id')->index();
    $table->unsignedInteger('user_id')->nullable();
    $table->string('tool_name', 120);
    $table->string('permission', 120);
    $table->longText('arguments');
    $table->string('status', 20)->default('pending')->index();
    $table->string('ip_address', 45)->nullable();
    $table->dateTime('requested_at')->nullable();
    $table->dateTime('expires_at')->nullable()->index();
    $table->dateTime('decided_at')->nullable();
    $table->unsignedInteger('decided_by_id')->nullable();
    $table->text('decision_note')->nullable();
    $table->dateTime('executed_at')->nullable();
    $table->text('error')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
