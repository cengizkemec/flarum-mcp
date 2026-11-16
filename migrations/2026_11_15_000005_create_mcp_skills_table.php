<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mcp_skills', function (Blueprint $table) {
    $table->increments('id');
    $table->string('slug', 120)->unique();
    $table->string('name', 160);
    $table->text('description')->nullable();
    $table->string('version', 40)->default('1.0.0');
    $table->string('source_type', 30)->default('manual');
    $table->string('source_url', 500)->nullable();
    $table->longText('content');
    $table->char('checksum', 64);
    $table->boolean('enabled')->default(true)->index();
    $table->boolean('is_builtin')->default(false)->index();
    $table->string('required_permission', 120)->nullable();
    $table->text('metadata')->nullable();
    $table->unsignedInteger('installed_by_id')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
