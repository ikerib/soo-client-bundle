<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\Tests\Security;

use Pasaia\SsoClientBundle\Security\OidcUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OidcUserTest extends TestCase
{
    // ── fromClaims: roles claim ───────────────────────────────────────────────

    public function testRolesFromClaimsArePreservedAndRoleUserAdded(): void
    {
        $user = OidcUser::fromClaims('jdoe', [
            'roles' => ['ROLE_ADMIN', 'ROLE_EDITOR'],
        ]);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_EDITOR', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRoleUserIsAddedWhenClaimsHaveNoRoles(): void
    {
        $user = OidcUser::fromClaims('jdoe', []);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testRoleUserIsNotDuplicatedWhenAlreadyPresent(): void
    {
        $user = OidcUser::fromClaims('jdoe', [
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        ]);

        self::assertCount(1, array_filter($user->getRoles(), fn(string $r) => $r === 'ROLE_USER'));
    }

    public function testNonStringEntriesInRolesClaimAreIgnored(): void
    {
        $user = OidcUser::fromClaims('jdoe', [
            'roles' => ['ROLE_ADMIN', 123, null, true, ''],
        ]);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertCount(2, $user->getRoles());
    }

    public function testEmptyRolesClaimResultsInOnlyRoleUser(): void
    {
        $user = OidcUser::fromClaims('jdoe', ['roles' => []]);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    // ── fromClaims: scalar claims ─────────────────────────────────────────────

    public function testClaimsAreMappedCorrectly(): void
    {
        $user = OidcUser::fromClaims('jdoe', [
            'name'        => 'Jon Doe',
            'email'       => 'jon@pasaia.eus',
            'dni'         => '12345678Z',
            'auth_method' => 'certificate',
        ]);

        self::assertSame('jdoe', $user->getUserIdentifier());
        self::assertSame('Jon Doe', $user->getName());
        self::assertSame('jon@pasaia.eus', $user->getEmail());
        self::assertSame('12345678Z', $user->getDni());
        self::assertSame('certificate', $user->getAuthMethod());
    }

    public function testOptionalClaimsDefaultToNull(): void
    {
        $user = OidcUser::fromClaims('jdoe', []);

        self::assertNull($user->getName());
        self::assertNull($user->getEmail());
        self::assertNull($user->getDni());
        self::assertSame('unknown', $user->getAuthMethod());
    }

    public function testNonStringScalarClaimsAreIgnored(): void
    {
        $user = OidcUser::fromClaims('jdoe', [
            'name'  => 42,
            'email' => false,
            'dni'   => null,
        ]);

        self::assertNull($user->getName());
        self::assertNull($user->getEmail());
        self::assertNull($user->getDni());
    }

    // ── UserInterface contract ────────────────────────────────────────────────

    public function testGetUserIdentifierReturnsSubClaim(): void
    {
        $user = OidcUser::fromClaims('user123', []);

        self::assertSame('user123', $user->getUserIdentifier());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = OidcUser::fromClaims('jdoe', ['name' => 'Jon']);
        $user->eraseCredentials();

        // Name should still be accessible after eraseCredentials (it's not a credential).
        self::assertSame('Jon', $user->getName());
    }

    // ── Serialization ─────────────────────────────────────────────────────────

    public function testUserSurvivesSerializationRoundtrip(): void
    {
        $original = OidcUser::fromClaims('jdoe', [
            'name'        => 'Jon Doe',
            'email'       => 'jon@pasaia.eus',
            'dni'         => '12345678Z',
            'auth_method' => 'password',
            'roles'       => ['ROLE_ADMIN'],
        ]);

        /** @var OidcUser $restored */
        $restored = unserialize(serialize($original));

        self::assertSame($original->getUserIdentifier(), $restored->getUserIdentifier());
        self::assertSame($original->getRoles(), $restored->getRoles());
        self::assertSame($original->getName(), $restored->getName());
        self::assertSame($original->getEmail(), $restored->getEmail());
        self::assertSame($original->getDni(), $restored->getDni());
        self::assertSame($original->getAuthMethod(), $restored->getAuthMethod());
    }
}
