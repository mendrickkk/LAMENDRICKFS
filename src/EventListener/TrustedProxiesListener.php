<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures Symfony trusts ngrok proxy headers so HTTPS, cookies, and CSRF work.
 */
class TrustedProxiesListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 1000], // Run very early
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Trust ngrok proxy headers so HTTPS is detected correctly
        Request::setTrustedHosts(['^.*$']);
        Request::setTrustedProxies(
            ['0.0.0.0/0', '::/0'], // trust all proxies (ngrok)
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}

