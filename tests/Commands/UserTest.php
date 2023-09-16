<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\Shield\Commands\User;
use CodeIgniter\Shield\Commands\Utils\InputOutput;
use CodeIgniter\Shield\Entities\User as UserEntity;
use CodeIgniter\Shield\Models\UserModel;
use Tests\Commands\Utils\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class UserTest extends DatabaseTestCase
{
    private ?InputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        User::resetInputOutput();
    }

    /**
     * Set MockInputOutput and user inputs.
     *
     * @param array<int, string> $inputs User inputs
     * @phpstan-param list<string> $inputs
     */
    private function setMockIo(array $inputs): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        User::setInputOutput($this->io);
    }

    public function testCreate(): void
    {
        $this->setMockIo([
            'Secret Passw0rd!',
            'Secret Passw0rd!',
        ]);

        command('shield:user create -n user1 -e user1@example.com');

        $this->assertStringContainsString(
            'User "user1" created',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user1@example.com']);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'user1@example.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 0,
        ]);
    }

    /**
     * Create an active user.
     */
    private function createUser(array $userData): UserEntity
    {
        /** @var UserEntity $user */
        $user = fake(UserModel::class, ['username' => $userData['username']]);
        $user->createEmailIdentity([
            'email'    => $userData['email'],
            'password' => $userData['password'],
        ]);

        return $user;
    }

    public function testActivate(): void
    {
        $user = $this->createUser([
            'username' => 'user2',
            'email'    => 'user2@example.com',
            'password' => 'secret123',
        ]);

        $user->deactivate();
        $users = model(UserModel::class);
        $users->save($user);

        $this->setMockIo(['y']);

        command('shield:user activate -n user2');

        $this->assertStringContainsString(
            'User "user2" activated',
            $this->io->getLastOutput()
        );

        $user = $users->findByCredentials(['email' => 'user2@example.com']);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 1,
        ]);
    }

    public function testDeactivate(): void
    {
        $this->createUser([
            'username' => 'user3',
            'email'    => 'user3@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo(['y']);

        command('shield:user deactivate -n user3');

        $this->assertStringContainsString(
            'User "user3" deactivated',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user3@example.com']);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 0,
        ]);
    }

    public function testChangename(): void
    {
        $this->createUser([
            'username' => 'user4',
            'email'    => 'user4@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo(['y']);

        command('shield:user changename -n user4 --new-name newuser4');

        $this->assertStringContainsString(
            'Username "user4" changed to "newuser4"',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user4@example.com']);
        $this->seeInDatabase($this->tables['users'], [
            'id'       => $user->id,
            'username' => 'newuser4',
        ]);
    }

    public function testChangeemail(): void
    {
        $this->createUser([
            'username' => 'user5',
            'email'    => 'user5@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo(['y']);

        command('shield:user changeemail -n user5 --new-email newuser5@example.jp');

        $this->assertStringContainsString(
            'Email for "user5" changed to newuser5@example.jp',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'newuser5@example.jp']);
        $this->seeInDatabase($this->tables['users'], [
            'id'       => $user->id,
            'username' => 'user5',
        ]);
    }

    public function testDelete(): void
    {
        $this->createUser([
            'username' => 'user6',
            'email'    => 'user6@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo(['y']);

        command('shield:user delete -n user6');

        $this->assertStringContainsString(
            'User "user6" deleted',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user6@example.com']);
        $this->assertNull($user);
    }

    public function testPassword(): void
    {
        $this->createUser([
            'username' => 'user7',
            'email'    => 'user7@example.com',
            'password' => 'secret123',
        ]);
        $users           = model(UserModel::class);
        $user            = $users->findByCredentials(['email' => 'user7@example.com']);
        $oldPasswordHash = $user->password_hash;

        $this->setMockIo(['y', 'newpassword', 'newpassword']);

        command('shield:user password -n user7');

        $this->assertStringContainsString(
            'Password for "user7" set',
            $this->io->getLastOutput()
        );

        $user = $users->findByCredentials(['email' => 'user7@example.com']);
        $this->assertNotSame($oldPasswordHash, $user->password_hash);
    }

    public function testPasswordWithoutOptionsAndSpecifyEmail(): void
    {
        $this->createUser([
            'username' => 'user7',
            'email'    => 'user7@example.com',
            'password' => 'secret123',
        ]);
        $users           = model(UserModel::class);
        $user            = $users->findByCredentials(['email' => 'user7@example.com']);
        $oldPasswordHash = $user->password_hash;

        $this->setMockIo(['e', 'user7@example.com', 'y', 'newpassword', 'newpassword']);

        command('shield:user password');

        $this->assertStringContainsString(
            'Password for "user7" set',
            $this->io->getLastOutput()
        );

        $user = $users->findByCredentials(['email' => 'user7@example.com']);
        $this->assertNotSame($oldPasswordHash, $user->password_hash);
    }

    public function testList(): void
    {
        $this->createUser([
            'username' => 'user8',
            'email'    => 'user8@example.com',
            'password' => 'secret123',
        ]);
        $this->createUser([
            'username' => 'user9',
            'email'    => 'user9@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo([]);

        command('shield:user list');

        $this->assertStringContainsString(
            'Id	User
1	user8 (user8@example.com)
2	user9 (user9@example.com)
',
            $this->io->getOutputs()
        );
    }

    public function testListByEmail(): void
    {
        $this->createUser([
            'username' => 'user8',
            'email'    => 'user8@example.com',
            'password' => 'secret123',
        ]);
        $this->createUser([
            'username' => 'user9',
            'email'    => 'user9@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo([]);

        command('shield:user list -e user9@example.com');

        $this->assertStringContainsString(
            'Id	User
2	user9 (user9@example.com)
',
            $this->io->getOutputs()
        );
    }

    public function testAddgroup(): void
    {
        $this->createUser([
            'username' => 'user10',
            'email'    => 'user10@example.com',
            'password' => 'secret123',
        ]);

        $this->setMockIo(['y']);

        command('shield:user addgroup -n user10 -g admin');

        $this->assertStringContainsString(
            'User "user10" added to group "admin"',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user10@example.com']);
        $this->assertTrue($user->inGroup('admin'));
    }

    public function testRemovegroup(): void
    {
        $this->createUser([
            'username' => 'user11',
            'email'    => 'user11@example.com',
            'password' => 'secret123',
        ]);
        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user11@example.com']);
        $user->addGroup('admin');
        $this->assertTrue($user->inGroup('admin'));

        $this->setMockIo(['y']);

        command('shield:user removegroup -n user11 -g admin');

        $this->assertStringContainsString(
            'User "user11" removed from group "admin"',
            $this->io->getLastOutput()
        );

        $users = model(UserModel::class);
        $user  = $users->findByCredentials(['email' => 'user11@example.com']);
        $this->assertFalse($user->inGroup('admin'));
    }
}
