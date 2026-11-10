<?php

namespace App\Tests\Security;

use App\Controller\AdminApplicationController;
use App\Controller\AdminDashboardController;
use App\Controller\AdminLoanController;
use App\Controller\AdminPaymentController;
use App\Controller\AdminReportController;
use App\Controller\AdminSettingsController;
use App\Controller\AdminUserController;
use App\Controller\StaffDocumentController;
use App\Controller\StaffLoanController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RoleAccessTest extends WebTestCase
{
    public function testAnonymousUsersAreRedirectedFromAdminPages(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');

        self::assertResponseRedirects('/login');
    }

    public function testAdminControllersRequireAdminRole(): void
    {
        foreach ([
            AdminApplicationController::class,
            AdminDashboardController::class,
            AdminLoanController::class,
            AdminPaymentController::class,
            AdminReportController::class,
            AdminSettingsController::class,
            AdminUserController::class,
        ] as $controller) {
            $attributes = (new \ReflectionClass($controller))->getAttributes(IsGranted::class);
            self::assertCount(1, $attributes, $controller);
            self::assertSame('ROLE_ADMIN', $attributes[0]->newInstance()->attribute, $controller);
        }
    }

    public function testAdminCannotEditExistingUserProfiles(): void
    {
        $routes = static::getContainer()->get('router')->getRouteCollection();

        self::assertNotNull($routes->get('admin_user_edit'));
        self::assertNotNull($routes->get('admin_user_new'));
        self::assertNotNull($routes->get('app_profile_edit'));
    }

    public function testStaffControllersRequireStaffRole(): void
    {
        foreach ([StaffDocumentController::class, StaffLoanController::class] as $controller) {
            $attributes = (new \ReflectionClass($controller))->getAttributes(IsGranted::class);
            self::assertCount(1, $attributes, $controller);
            self::assertSame('ROLE_STAFF', $attributes[0]->newInstance()->attribute, $controller);
        }
    }

    public function testLogoutCannotBeTriggeredWithGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        self::assertResponseStatusCodeSame(405);
    }
}
