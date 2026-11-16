<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_tool_states', function (Blueprint $table) {
    $table->increments('id');
    $table->string('tool_name', 120)->unique();
    $table->boolean('enabled')->default(true);
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
