<?php

declare(strict_types=1);

namespace App\Tests\Security\Signup;

use App\Factory\UserFactory;
use App\Security\Signup\RequestSignup;
use App\Security\Signup\UserSignup;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class UserSignupTest extends KernelTestCase
{
    private const string SECURE_PASSWORD = 'BF628A5B-F3F7-4E4A-8FFE-FE7475768B59';

    #[Group('stateful')]
    public function testCreatesUserFromSignupRequest(): void
    {
        $request = new RequestSignup();
        $request->email = 'jane@example.com';
        $request->plainPassword = self::SECURE_PASSWORD;

        $registeredUser = $this->userSignup()->signup($request);

        self::assertNotNull($registeredUser->getId());
        self::assertSame($request->email, $registeredUser->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $registeredUser->getRoles(), 'User should have ROLE_USER role');
        self::assertNotEmpty($registeredUser->getPassword());
        self::assertNotSame($request->plainPassword, $registeredUser->getPassword(), 'Encoded password should no longer match plain password');
    }

    #[Group('stateful')]
    public function testFailsWhenEmailIsAlreadyTaken(): void
    {
        // Tests no longer depend on each other: the existing user is created right here.
        UserFactory::createOne(['email' => 'jane@example.com']);

        $request = new RequestSignup();
        $request->email = 'jane@example.com';
        $request->plainPassword = self::SECURE_PASSWORD;

        $this->expectException(UniqueConstraintViolationException::class);

        $this->userSignup()->signup($request);
    }

    public function testFailsWithoutPassword(): void
    {
        $request = new RequestSignup();
        $request->email = 'nobody@example.com';

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Typed property App\Security\Signup\RequestSignup::$plainPassword must not be accessed before initialization');

        $this->userSignup()->signup($request);
    }

    private function userSignup(): UserSignup
    {
        return static::getContainer()->get(UserSignup::class);
    }
}
