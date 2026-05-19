<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Security\Exception\CustomerOnlyApiException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly AuthenticationFailureHandlerInterface $lexikFailureHandler,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof CustomerOnlyApiException) {
                return new JsonResponse(['message' => CustomerApiAccess::NON_CUSTOMER_MESSAGE], 403);
            }
        }

        return $this->lexikFailureHandler->onAuthenticationFailure($request, $exception);
    }
}
