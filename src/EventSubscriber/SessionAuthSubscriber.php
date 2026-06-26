<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Centralized, role-aware session guard, plus cache hardening.
 *
 * This application uses a hand-rolled, session-based auth model (a "user_id" and
 * "user_role" stored on the session) instead of a Symfony firewall. This subscriber
 * provides what a firewall normally would:
 *
 *  1. Back-office authorization (kernel.request): /elfirma and /admin are restricted
 *     to staff (admin/employee). Anonymous users go to login; logged-in clients go
 *     back to the public home. A small allow-list (e.g. a user's own profile) stays
 *     open to any authenticated user.
 *
 *  2. Customer-interaction authorization (kernel.request): browsing the storefront is
 *     public, but any *interaction* (cart, checkout/buying, orders, ratings,
 *     notifications) requires being logged in. Page requests are redirected to login;
 *     API/AJAX requests get a 401 JSON so the front-end JavaScript can react.
 *
 *  3. Cache hardening (kernel.response): authenticated pages are marked "no-store" so
 *     the browser cannot reveal a protected page from its cache via the Back button
 *     after logout.
 *
 * Stricter page-specific rules (e.g. the Users module being admin-only and requiring
 * 2FA) intentionally remain in their controllers.
 */
final class SessionAuthSubscriber implements EventSubscriberInterface
{
    /** Back-office areas: staff (admin/employee) only. */
    private const STAFF_PREFIXES = ['/elfirma', '/admin'];

    /** Back-office paths any authenticated user may use (e.g. own profile). */
    private const SHARED_PREFIXES = ['/elfirma/profile'];

    /** Roles allowed into the back-office. */
    private const STAFF_ROLES = ['admin', 'administrateur', 'employee'];

    /**
     * Customer interactions that require being logged in (any role). Browsing and
     * read-only catalogue endpoints are intentionally NOT listed, so the storefront
     * stays public; only actions that create/modify data or expose personal data
     * are gated.
     */
    private const LOGIN_REQUIRED_PREFIXES = [
        '/panier',                 // cart page
        '/api/panier/add',
        '/api/panier/update',
        '/api/panier/remove',
        '/api/panier/clear',
        '/commandes',              // "my orders"
        '/commander',              // checkout (buying)
        '/commande',               // order details, receipts, stripe intent, create
        '/api/commande',           // quick order, checkout helpers
        '/api/rating/add',         // submitting a supplier rating
        '/api/user',               // per-user notifications
        '/api/notification',       // mark notification read
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $isStaffArea = $this->matchesAnyPrefix($path, self::STAFF_PREFIXES);
        $isLoginRequired = $this->matchesAnyPrefix(
            $path,
            self::LOGIN_REQUIRED_PREFIXES,
        );

        // Public route: nothing to enforce.
        if (!$isStaffArea && !$isLoginRequired) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $userId = $session?->get('user_id');

        if ($isStaffArea) {
            // Anonymous -> login. Pages any authenticated user may use are allowed.
            if (empty($userId)) {
                $event->setResponse($this->loginRedirect());

                return;
            }
            if ($this->matchesAnyPrefix($path, self::SHARED_PREFIXES)) {
                return;
            }
            // Management screens: staff only; a logged-in client goes to the home.
            $role = (string) $session?->get('user_role');
            if (!in_array($role, self::STAFF_ROLES, true)) {
                $event->setResponse(
                    new RedirectResponse(
                        $this->urlGenerator->generate('app_pages_home'),
                    ),
                );
            }

            return;
        }

        // Customer interaction: must be logged in (any role).
        if (empty($userId)) {
            $event->setResponse(
                $this->isApiRequest($request, $path)
                    ? new JsonResponse(
                        [
                            'ok' => false,
                            'error' => 'Authentication required. Please sign in.',
                            'redirect' => $this->urlGenerator->generate(
                                'app_login',
                            ),
                        ],
                        401,
                    )
                    : $this->loginRedirect(),
            );
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        // Only harden caching for authenticated responses; public pages keep their
        // normal caching behaviour.
        if (empty($request->getSession()->get('user_id'))) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private',
        );
        $response->headers->set('Pragma', 'no-cache');
    }

    private function loginRedirect(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_login'),
        );
    }

    private function isApiRequest(Request $request, string $path): bool
    {
        return str_starts_with($path, '/api/') || $request->isXmlHttpRequest();
    }

    /**
     * @param string[] $prefixes
     */
    private function matchesAnyPrefix(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
