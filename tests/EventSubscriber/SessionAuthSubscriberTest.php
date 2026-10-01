<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SessionAuthSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SessionAuthSubscriberTest extends TestCase
{
    public static function accessCases(): array
    {
        return [
            // path, role (null = anonymous), expected outcome
            'anonymous storefront page' => ['/about', null, 'pass'],
            'anonymous catalogue (not /livestock)' => ['/livestock-catalog', null, 'pass'],
            'anonymous product API' => ['/api/produit/3', null, 'pass'],
            'anonymous cart action' => ['/api/panier/add', null, '401'],
            'anonymous back office page' => ['/maintenance/3/delete', null, 'redirect:/login'],
            'anonymous back office API' => ['/api/dashboard', null, '401'],
            'client on equipment module' => ['/equipements', 'client', 'redirect:/home'],
            'client on back office API' => ['/api/dashboard', 'client', '403'],
            'client on user insights' => ['/chatbot/users-insights', 'client', 'redirect:/home'],
            'client on own profile' => ['/elfirma/profile', 'client', 'pass'],
            'employee on equipment module' => ['/equipements', 'employee', 'pass'],
            'employee on technician panel' => ['/employee/panel', 'employee', 'pass'],
            'admin on back office API' => ['/api/dashboard', 'admin', 'pass'],
        ];
    }

    #[DataProvider('accessCases')]
    public function testAccessRules(string $path, ?string $role, string $expected): void
    {
        $request = Request::create($path);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        if ($role !== null) {
            $session->set('user_id', 7);
            $session->set('user_role', $role);
        }

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
        $this->createSubscriber()->onKernelRequest($event);

        $response = $event->getResponse();
        $outcome = match (true) {
            $response === null => 'pass',
            $response->isRedirect() => 'redirect:' . $response->headers->get('Location'),
            default => (string) $response->getStatusCode(),
        };

        $this->assertSame($expected, $outcome);
    }

    private function createSubscriber(): SessionAuthSubscriber
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => $name === 'app_login' ? '/login' : '/home',
        );

        return new SessionAuthSubscriber($urlGenerator);
    }
}
