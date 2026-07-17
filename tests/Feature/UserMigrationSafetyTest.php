<?php

namespace IFRS\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use IFRS\Tests\TestCase;
use IFRS\User;

class UserMigrationSafetyTest extends TestCase
{
    public function testUserMigrationIsIdempotentAndDoesNotDropSharedColumns(): void
    {
        $migration = new \IfrsCreateOrUpdateUsersTable();

        $migration->up();
        $migration->up();

        $usersTable = (new User())->getTable();
        Schema::table($usersTable, function (Blueprint $table): void {
            $table->dropColumn('created');
        });

        $migration->down();

        $this->assertTrue(Schema::hasColumn($usersTable, 'entity_id'));
        $this->assertTrue(Schema::hasColumn($usersTable, 'destroyed_at'));
    }
}
