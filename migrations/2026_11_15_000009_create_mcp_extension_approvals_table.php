<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_extension_approvals', function (Blueprint $table) {
    $table->increments('id');
    $table->string('extension_id', 160)->unique();
    $table->string('status', 20)->default('pending')->index();
    $table->text('permissions')->nullable();
    $table->unsignedInteger('approved_by_id')->nullable();
    $table->dateTime('decided_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
