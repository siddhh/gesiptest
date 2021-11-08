<?php

namespace App\Utils\Tests;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

class TestBrowserToken extends AbstractToken
{
    public function __construct(array $roles = [], UserInterface $user = null)
    {
        parent::__construct($roles);

        if (null !== $user) {
            $this->setUser($user);
        }
    }

    public function getCredentials()
    {
        return null;
    }
}
