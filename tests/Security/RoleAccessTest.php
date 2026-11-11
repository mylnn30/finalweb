<?php

namespace App\Tests\Security;

use App\Controller\AdminApplicationController;
use App\Controller\AdminDashboardController;
use App\Controller\AdminLoanController;
use App\Controller\AdminPaymentController;
use App\Controller\AdminReportController;
use App\Controller\AdminSettingsController;
use App\Controller\AdminUserController;
use App\Entity\User;
use App\Controller\StaffDocumentController;
use App\Controller\StaffLoanController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RoleAccessTest extends WebTestCase
{
    public function testAnonymousUsersAreRedirectedFromAdminPages(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');

        self::assertResponseRedirects('/login');
    }

    public function testStaffCannotAccessAdminDashboardOrUserManagementUrls(): void
    {
        $container = static::getContainer();
        $accessMap = $container->get('security.access_map');
        $decisionManager = $container->get('security.access.decision_manager');
        $staff = (new User())->setUsername('staff-security-test')->setIsStaff(true);
        $token = new UsernamePasswordToken($staff, 'main', $staff->getRoles());

        foreach ([
            '/admin',
            '/admin/users/new',
            '/admin/users/42/access',
            '/admin/users/42/delete',
        ] as $uri) {
            [$attributes] = $accessMap->getPatterns(Request::create($uri));

            self::assertContains('ROLE_ADMIN', $attributes, sprintf('%s must require admin access.', $uri));
            self::assertFalse($decisionManager->decide($token, $attributes), sprintf('Staff must be denied at %s.', $uri));
        }
    }

    public function testStaffCanAccessOwnAccountAndAssignedApplicationRoutes(): void
    {
        $container = static::getContainer();
        $accessMap = $container->get('security.access_map');
        $decisionManager = $container->get('security.access.decision_manager');
        $staff = (new User())->setUsername('staff-security-test')->setIsStaff(true);
        $token = new UsernamePasswordToken($staff, 'main', $staff->getRoles());

        foreach ([
            ['/profile', 'ROLE_USER'],
            ['/profile/edit', 'ROLE_USER'],
            ['/account', 'ROLE_USER'],
            ['/account/password', 'ROLE_USER'],
            ['/staff', 'ROLE_STAFF'],
            ['/staff/applications', 'ROLE_STAFF'],
            ['/staff/applications/42', 'ROLE_STAFF'],
            ['/staff/applications/42/edit', 'ROLE_STAFF'],
            ['/staff/applications/42/delete', 'ROLE_STAFF'],
        ] as [$uri, $requiredRole]) {
            [$attributes] = $accessMap->getPatterns(Request::create($uri));

            self::assertContains($requiredRole, $attributes, sprintf('%s must require %s.', $uri, $requiredRole));
            self::assertTrue($decisionManager->decide($token, $attributes), sprintf('Staff must be allowed at %s.', $uri));
        }
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
