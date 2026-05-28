<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Thin wrapper over drenso/symfony-oidc-bundle for the SSO Municipal Pasaia.
 *
 * Responsibilities:
 *  - Prepends drenso_oidc client config from SSO_* env vars (hides drenso internals from apps).
 *  - Registers OidcUserProvider and SsoLoginController as services.
 *  - Exposes /sso/login route (the only route the bundle owns; the callback is handled
 *    by drenso's authenticator on the firewall check_path).
 */
class PasaiaSsoClientBundle extends Bundle implements PrependExtensionInterface
{
    /**
     * Prepend drenso_oidc configuration so apps only need SSO_* env vars.
     *
     * Config is prepended (not overridden), so apps can still add extra drenso_oidc
     * settings if needed (e.g. well_known_cache_time).
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('drenso_oidc', [
            'clients' => [
                'default' => [
                    // SSO_ISSUER_URL must NOT have a trailing slash.
                    // drenso requires the full URL to /.well-known/openid-configuration.
                    'well_known_url' => '%env(SSO_ISSUER_URL)%/.well-known/openid-configuration',
                    'client_id' => '%env(SSO_CLIENT_ID)%',
                    'client_secret' => '%env(SSO_CLIENT_SECRET)%',
                    // Cache the JWKS and discovery document for 1 hour to reduce SSO load.
                    'jwks_cache_time' => 3600,
                    'well_known_cache_time' => 3600,
                ],
            ],
        ]);
    }
}
