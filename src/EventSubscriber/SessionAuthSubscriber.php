<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Centralized, role-aware session guard for the back-office, plus cache hardening.
 *
 * This application uses a hand-rolled, session-based auth model (a "user_id" and
 * "user_role" stored on the session) instead of a Symfony firewall. This subscriber
 * provides the two things a firewall would normally give you:
 *
 *  1. Authorization (kernel.request): the back-office (/elfirma) is restricted to
 *     staff (admin/employee). Anonymous users are sent to login; logged-in clients
 *     are sent back to the public home. A small allow-list of shared pages (e.g. a
 *     user's own profile) stays open to any authenticated user.
 *
 *  2. Cache hardening (kernel.response): authenticated pages are marked "no-store"
 *     so the browser cannot reveal a protected page from its cache via the Back
 *     button after the user logs out.
 *
 * Stricter, page-specific rules (e.g. the Users module being admin-only and
 * requiring 2FA) intentionally remain in their controllers.
 */
final class SessionAuthSubscriber implements EventSubscriberInterface
{
    /** Path prefixes that make up the back-office. */
    private const PROTECTED_PREFIXES = ['/elfirma'];

    /**
     * Back-office paths any authenticated user may use (including clients),
     * e.g. a user viewing their own profile.
     */
    private const SHARED_PREFIXES = ['/elfirma/profile'];

    /** Roles allowed into the back-office management screens. */
    private const STAFF_ROLES = ['admin', 'administrateur', 'employee'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run early, before the controller is resolved/executed.
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

        if (!$this->isProtected($path)) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $userId = $session?->get('user_id');

        // Not logged in at all -> go authenticate.
        if (empty($userId)) {
            $event->setResponse(
                new RedirectResponse(
                    $this->urlGenerator->generate('app_login'),
                ),
            );

            return;
        }

        // Pages any authenticated user may use (e.g. their own profile).
        if ($this->isShared($path)) {
            return;
        }

        // Back-office management screens: staff only. A logged-in client lands
        // back on the public home instead of being pointlessly asked to re-login.
        $role = (string) $session?->get('user_role');
        if (!in_array($role, self::STAFF_ROLES, true)) {
            $event->setResponse(
                new RedirectResponse(
                    $this->urlGenerator->generate('app_pages_home'),
                ),
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

        // Only harden caching for authenticated responses. Logged-out / public
        // pages keep their normal caching behaviour.
        $userId = $request->getSession()->get('user_id');
        if (empty($userId)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private',
        );
        $response->headers->set('Pragma', 'no-cache');
    }

    private function isProtected(string $path): bool
    {
        return $this->matchesAnyPrefix($path, self::PROTECTED_PREFIXES);
    }

    private function isShared(string $path): bool
    {
        return $this->matchesAnyPrefix($path, self::SHARED_PREFIXES);
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
