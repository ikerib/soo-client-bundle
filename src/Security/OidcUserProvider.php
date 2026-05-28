<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Stateless OIDC user provider for SSO Municipal Pasaia client apps.
 *
 * Strategy (no database required):
 *  1. On OIDC callback, drenso calls ensureUserExists() with the verified claims.
 *     We build an OidcUser and cache it in $pendingUsers for the duration of the request.
 *  2. drenso immediately calls loadOidcUser() to retrieve the user for the security token.
 *     We pop it from $pendingUsers.
 *  3. Symfony serializes the security token (including the full OidcUser) to the session.
 *  4. On subsequent requests, Symfony deserializes the token and calls refreshUser() —
 *     we return the deserialized user unchanged (trust the session, no re-auth needed).
 *
 * If your app requires re-validating roles on every request (e.g. for privilege changes
 * to take effect immediately), override refreshUser() and call the SSO UserInfo endpoint.
 *
 * @implements OidcUserProviderInterface<OidcUser>
 */
final class OidcUserProvider implements OidcUserProviderInterface
{
    /** @var array<string, OidcUser> Temporary storage between ensureUserExists() and loadOidcUser(). */
    private array $pendingUsers = [];

    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    // ── OidcUserProviderInterface ─────────────────────────────────────────────

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        if ($userIdentifier === '') {
            throw new \InvalidArgumentException('OIDC user identifier (sub claim) must not be empty.');
        }

        $claims = [
            'name'        => $this->extractString($userData, 'name'),
            'email'       => $this->extractString($userData, 'email'),
            'dni'         => $this->extractString($userData, 'dni'),
            'auth_method' => $this->extractString($userData, 'auth_method') ?? 'password',
            'roles'       => $this->extractArray($userData, 'roles'),
        ];

        $this->logger?->info('SSO client: user authenticated', [
            'user'        => $userIdentifier,
            'auth_method' => $claims['auth_method'],
            'roles'       => $claims['roles'],
        ]);

        /** @var non-empty-string $userIdentifier */
        $this->pendingUsers[$userIdentifier] = OidcUser::fromClaims($userIdentifier, $claims);
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        if (!isset($this->pendingUsers[$userIdentifier])) {
            throw new UserNotFoundException(
                sprintf('OIDC user "%s" could not be loaded after authentication.', $userIdentifier)
            );
        }

        $user = $this->pendingUsers[$userIdentifier];
        unset($this->pendingUsers[$userIdentifier]);

        return $user;
    }

    // ── UserProviderInterface ─────────────────────────────────────────────────

    /**
     * Not used in stateless OIDC mode. Symfony calls loadOidcUser() instead.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException(
            sprintf('Direct load of "%s" is not supported. Use the OIDC authentication flow.', $identifier)
        );
    }

    /**
     * Called on every request after the first. Returns the deserialized session user as-is.
     * Override this in your app to re-validate the user against the SSO UserInfo endpoint
     * if you need immediate propagation of role changes.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof OidcUser) {
            throw new UnsupportedUserException(
                sprintf('Expected %s, got %s.', OidcUser::class, $user::class)
            );
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === OidcUser::class;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function extractString(OidcUserData $userData, string $key): ?string
    {
        $value = $userData->getUserData($key);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private function extractArray(OidcUserData $userData, string $key): array
    {
        $value = $userData->getUserData($key);
        if (!\is_array($value)) {
            return [];
        }

        /** @var list<string> $filtered */
        $filtered = array_values(array_filter($value, 'is_string'));

        return $filtered;
    }
}
