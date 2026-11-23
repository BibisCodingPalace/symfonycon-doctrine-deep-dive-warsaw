<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

#[AsController]
final readonly class LoginAction
{
    public function __construct(
        private Environment $twig,
        private AuthenticationUtils $authenticationUtils,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function __invoke(#[CurrentUser] ?User $user): Response
    {
        if (null !== $user) {
            return new RedirectResponse($this->urlGenerator->generate('tasklist_list'));
        }

        return new Response($this->twig->render('security/login.html.twig', [
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
            'last_username' => $this->authenticationUtils->getLastUsername(),
        ]));
    }
}
