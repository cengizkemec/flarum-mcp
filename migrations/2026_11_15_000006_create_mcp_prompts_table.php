<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_prompts', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 120)->unique();
    $table->string('title', 160)->nullable();
    $table->text('description')->nullable();
    $table->longText('content');
    $table->text('arguments')->nullable();
    $table->boolean('enabled')->default(true)->index();
    $table->string('required_permission', 120)->default('prompts.read');
    $table->string('locale', 10)->default('en');
    $table->unsignedInteger('created_by_id')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
