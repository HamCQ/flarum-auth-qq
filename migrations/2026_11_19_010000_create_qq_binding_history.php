<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('qq_binding_history')) return;
        $schema->create('qq_binding_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedInteger('binding_id')->nullable();
            $table->string('provider', 100);
            $table->string('identifier', 100)->nullable()->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->string('union_id', 64)->nullable()->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->string('action', 10);
            $table->string('ip', 45)->nullable();
            $table->dateTime('bound_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index('identifier');
            $table->index('union_id');
        });
    },
    'down' => function (Builder $schema) {
        // Binding history must survive extension uninstall and account deletion.
    },
];
