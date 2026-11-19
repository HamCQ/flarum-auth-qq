<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasColumn('users', 'qq_unionid')) {
            $schema->table('users', function (Blueprint $table) {
                // NULL keeps legacy accounts valid while allowing a unique UnionID index.
                $table->string('qq_unionid', 64)->nullable()->charset('utf8mb4')->collation('utf8mb4_bin');
            });
        }
        $connection = $schema->getConnection();
        $table = $connection->getTablePrefix().'users';
        foreach ($connection->getDoctrineSchemaManager()->listTableIndexes($table) as $index) {
            if ($index->isUnique() && $index->getColumns() === ['qq_unionid']) return;
        }
        $schema->table('users', function (Blueprint $table) {
            $table->unique('qq_unionid', 'users_qq_unionid_unique');
        });
    },
    'down' => function (Builder $schema) {
        // Preserve existing identity associations when the extension is disabled/uninstalled.
    },
];
