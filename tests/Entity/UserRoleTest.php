<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function testNewAccountsAreBorrowers(): void
    {
        self::assertSame(['ROLE_USER', 'ROLE_BORROWER'], (new User())->getRoles());
    }

    public function testStaffRoleIsDistinctFromBorrowerAndAdmin(): void
    {
        $user = (new User())->setRole('staff');

        self::assertSame(['ROLE_USER', 'ROLE_STAFF'], $user->getRoles());
        self::assertTrue($user->isStaff());
        self::assertFalse($user->isAdmin());
    }

    public function testAdminRoleChangeClearsStaffFlag(): void
    {
        $user = (new User())->setIsStaff(true)->setIsAdmin(true);

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        self::assertTrue($user->isAdmin());
        self::assertFalse($user->isStaff());
    }

    public function testStaffRoleChangeClearsAdminFlag(): void
    {
        $user = (new User())->setIsAdmin(true)->setIsStaff(true);

        self::assertSame(['ROLE_USER', 'ROLE_STAFF'], $user->getRoles());
        self::assertFalse($user->isAdmin());
        self::assertTrue($user->isStaff());
    }
}
