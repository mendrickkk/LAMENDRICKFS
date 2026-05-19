<?php

namespace App\Security\Exception;

use App\Security\CustomerApiAccess;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Thrown when staff/admin attempt API (customer app) login.
 */
final class CustomerOnlyApiException extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct(CustomerApiAccess::NON_CUSTOMER_MESSAGE);
    }
}
