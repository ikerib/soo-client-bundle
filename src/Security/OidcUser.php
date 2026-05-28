<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony Security principal for a user authenticated via the SSO Municipal Pasaia.
 *
 * Built from the ID token / UserInfo claims returned by the SSO. Stored fully in
 * the Symfony session token — the app never queries LDAP or any external service.
 * Roles come from the "roles" claim emitted by the SSO for this specific client.
 */
final class OidcUser implements UserInterface
{
    /**
     * @param non-empty-string $userIdentifier
     * @param list<string>     $roles           Symfony roles from the SSO "roles" claim + ROLE_USER.
     */
    public function __construct(
        private readonly string $userIdentifier,
        private readonly array $roles,
        private readonly ?string $name,
        private readonly ?string $email,
        private readonly ?string $dni,
        private readonly string $authMethod,
    ) {
    }

    /**
     * Constructs an OidcUser from a flat map of OIDC claims.
     *
     * Expected claim keys (all optional except the identifier):
     *   name, email, dni, auth_method, roles (array of strings).
     *
     * @param non-empty-string     $userIdentifier
     * @param array<string, mixed> $claims
     */
    public static function fromClaims(string $userIdentifier, array $claims): self
    {
        /** @var list<string> $roles */
        $roles = [];
        if (is_array($claims['roles'] ?? null)) {
            foreach ($claims['roles'] as $role) {
                if (is_string($role) && $role !== '') {
                    $roles[] = $role;
                }
            }
        }

        if (!\in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return new self(
            userIdentifier: $userIdentifier,
            roles: $roles,
            name: \is_string($claims['name'] ?? null) ? $claims['name'] : null,
            email: \is_string($claims['email'] ?? null) ? $claims['email'] : null,
            dni: \is_string($claims['dni'] ?? null) ? $claims['dni'] : null,
            authMethod: \is_string($claims['auth_method'] ?? null) ? $claims['auth_method'] : 'unknown',
        );
    }

    // ── UserInterface ─────────────────────────────────────────────────────────

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // No credentials stored.
    }

    // ── SSO-specific getters ──────────────────────────────────────────────────

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * DNI of the authenticated user, present only when the "dni" scope was requested
     * and the user authenticated via Giltza (certificate).
     */
    public function getDni(): ?string
    {
        return $this->dni;
    }

    /**
     * Authentication method used at the SSO: "password" (LDAP) or "certificate" (Giltza).
     */
    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }
}
