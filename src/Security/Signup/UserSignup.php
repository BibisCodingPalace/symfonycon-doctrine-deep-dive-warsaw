<?php

declare(strict_types=1);

namespace App\Security\Signup;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserSignup
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function signup(RequestSignup $signupRequest): User
    {
        $user = new User($signupRequest->email);
        $user->replaceEncodedPassword($this->passwordHasher->hashPassword($user, $signupRequest->plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
