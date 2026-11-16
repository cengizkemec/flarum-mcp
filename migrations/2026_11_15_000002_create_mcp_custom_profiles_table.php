<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_custom_profiles', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100)->unique();
    $table->text('description')->nullable();
    $table->text('permissions');
    $table->unsignedInteger('created_by_id')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
