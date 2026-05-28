<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\Tests\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Pasaia\SsoClientBundle\Security\OidcUser;
use Pasaia\SsoClientBundle\Security\OidcUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class OidcUserProviderTest extends TestCase
{
    private OidcUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new OidcUserProvider();
    }

    // ── ensureUserExists + loadOidcUser ───────────────────────────────────────

    public function testFullLoginFlowBuildsOidcUserWithRoles(): void
    {
        $userData = $this->makeUserData([
            'name'        => 'Iker Ibargarengoitia',
            'email'       => 'iker@pasaia.eus',
            'auth_method' => 'password',
            'roles'       => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);
        $tokens = $this->makeTokens();

        $this->provider->ensureUserExists('iker', $userData, $tokens);
        $user = $this->provider->loadOidcUser('iker');

        self::assertInstanceOf(OidcUser::class, $user);
        self::assertSame('iker', $user->getUserIdentifier());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertSame('Iker Ibargarengoitia', $user->getName());
        self::assertSame('iker@pasaia.eus', $user->getEmail());
        self::assertSame('password', $user->getAuthMethod());
    }

    public function testRolesClaimIsUsedDirectlyWithoutLdap(): void
    {
        $userData = $this->makeUserData([
            'roles' => ['ROLE_GESTION', 'ROLE_REPORTS'],
        ]);

        $this->provider->ensureUserExists('txus', $userData, $this->makeTokens());
        $user = $this->provider->loadOidcUser('txus');

        self::assertInstanceOf(OidcUser::class, $user);
        self::assertContains('ROLE_GESTION', $user->getRoles());
        self::assertContains('ROLE_REPORTS', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testMissingRolesClaimYieldsOnlyRoleUser(): void
    {
        $userData = $this->makeUserData(['name' => 'Jon']);

        $this->provider->ensureUserExists('jon', $userData, $this->makeTokens());
        $user = $this->provider->loadOidcUser('jon');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testDniClaimIsPassedThrough(): void
    {
        $userData = $this->makeUserData([
            'dni'         => '12345678Z',
            'auth_method' => 'certificate',
        ]);

        $this->provider->ensureUserExists('cert-user', $userData, $this->makeTokens());
        $user = $this->provider->loadOidcUser('cert-user');

        self::assertInstanceOf(OidcUser::class, $user);
        self::assertSame('12345678Z', $user->getDni());
        self::assertSame('certificate', $user->getAuthMethod());
    }

    public function testPendingUserIsConsumedAfterLoad(): void
    {
        $userData = $this->makeUserData([]);

        $this->provider->ensureUserExists('once', $userData, $this->makeTokens());
        $this->provider->loadOidcUser('once'); // consumes it

        $this->expectException(UserNotFoundException::class);
        $this->provider->loadOidcUser('once'); // must fail now
    }

    public function testMultipleUsersCanBePendingSimultaneously(): void
    {
        $this->provider->ensureUserExists('alice', $this->makeUserData(['name' => 'Alice']), $this->makeTokens());
        $this->provider->ensureUserExists('bob', $this->makeUserData(['name' => 'Bob']), $this->makeTokens());

        $alice = $this->provider->loadOidcUser('alice');
        $bob   = $this->provider->loadOidcUser('bob');

        self::assertInstanceOf(OidcUser::class, $alice);
        self::assertInstanceOf(OidcUser::class, $bob);
        self::assertSame('Alice', $alice->getName());
        self::assertSame('Bob', $bob->getName());
    }

    // ── loadOidcUser errors ───────────────────────────────────────────────────

    public function testLoadOidcUserWithoutPriorEnsureThrows(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadOidcUser('ghost');
    }

    // ── loadUserByIdentifier ──────────────────────────────────────────────────

    public function testLoadUserByIdentifierAlwaysThrows(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('anyone');
    }

    // ── refreshUser ───────────────────────────────────────────────────────────

    public function testRefreshUserReturnsSameOidcUser(): void
    {
        $user = OidcUser::fromClaims('jdoe', ['roles' => ['ROLE_ADMIN']]);
        $refreshed = $this->provider->refreshUser($user);

        self::assertSame($user, $refreshed);
    }

    public function testRefreshUserThrowsForUnsupportedClass(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('test', null));
    }

    // ── supportsClass ─────────────────────────────────────────────────────────

    public function testSupportsOidcUser(): void
    {
        self::assertTrue($this->provider->supportsClass(OidcUser::class));
    }

    public function testDoesNotSupportOtherClasses(): void
    {
        self::assertFalse($this->provider->supportsClass(InMemoryUser::class));
        self::assertFalse($this->provider->supportsClass(\stdClass::class));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function makeUserData(array $data): OidcUserData
    {
        return new OidcUserData($data);
    }

    private function makeTokens(): OidcTokens
    {
        $stub = $this->createStub(OidcTokens::class);

        return $stub;
    }
}
