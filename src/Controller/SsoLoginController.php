<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\Controller;

use Drenso\OidcBundle\OidcClientInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Initiates the OIDC Authorization Code flow towards the SSO.
 *
 * This controller is the ONLY page the bundle provides. It redirects the user
 * to the SSO's authorization endpoint. After authentication, the SSO redirects
 * back to the firewall's check_path (/sso/callback by default), which drenso's
 * OidcAuthenticator handles automatically — no controller needed there.
 */
final class SsoLoginController
{
    public function __construct(
        private readonly OidcClientInterface $oidcClient,
        /** @var list<string> */
        private readonly array $scopes,
    ) {
    }

    #[Route('/sso/login', name: 'pasaia_sso_client_login', methods: ['GET'])]
    public function login(Request $request): RedirectResponse
    {
        // Preserve the intended URL so drenso can redirect there after login.
        // drenso reads _target_path from the session automatically when
        // always_use_default_target_path is false (the default).
        $targetPath = $request->query->get('_target_path');
        if ($targetPath !== null && $targetPath !== '') {
            $request->getSession()->set('_security.main.target_path', $targetPath);
        }

        return $this->oidcClient->generateAuthorizationRedirect(scopes: $this->scopes);
    }
}
