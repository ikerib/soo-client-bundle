<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pasaia_sso_client');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('scopes')
                    ->info('OIDC scopes to request. Must include "openid" and "roles" to get Symfony roles from the SSO.')
                    ->defaultValue(['openid', 'profile', 'email', 'roles'])
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('post_logout_redirect_uri')
                    ->info('Full URL to redirect to after the SSO end_session_endpoint logs the user out. Defaults to the app root.')
                    ->defaultNull()
                ->end()
                ->scalarNode('login_route')
                    ->info('Route name of the login page shown to unauthenticated users. Defaults to the bundle login route.')
                    ->defaultValue('pasaia_sso_client_login')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
