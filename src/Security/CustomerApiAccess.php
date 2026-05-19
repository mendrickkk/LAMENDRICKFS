<?php

namespace App\Security;

final class CustomerApiAccess
{
    public const NON_CUSTOMER_MESSAGE = 'This app is for customer accounts only. Staff and admin cannot sign in here — please use the admin website.';
}
