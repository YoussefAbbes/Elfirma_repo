<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Centralized authentication guard for the back-office.
 *
 * This application uses a hand-rolled, session-based auth model (a "user_id" key
 * stored on the session) instead of a Symfony firewall. As a result, back-office
 * routes were "open by default": they were only protected where an individual
 * controller happened to re-check the session. This subscriber enforces that any
 * request under a protected prefix has an authenticated session, redirecting
 * anonymous visitors to the login page.
 *
 * Fine-grained role checks (admin vs employee) intentionally remain in the
 * controllers; this guard only enforces "must be logged in".
 */
final class BackOfficeAuthSubscriber implements EventSubscriberInterface
{
    /** Path prefixes that require an authenticated session. */
    private const PROTECTED_PREFIXES = ['/elfirma'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run early, before the controller is resolved/executed.
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!$this->isProtected($path)) {
            return;
        }

        $request = $event->getRequest();
        $userId = $request->hasSession()
            ? $request->getSession()->get('user_id')
            : null;

        if (empty($userId)) {
            $event->setResponse(
                new RedirectResponse(
                    $this->urlGenerator->generate('app_login'),
                ),
            );
        }
    }

    private function isProtected(string $path): bool
    {
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
