<?php

declare(strict_types=1);

namespace Pasaia\SsoClientBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class PasaiaSsoClientExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');

        $container->setParameter('pasaia_sso_client.scopes', $config['scopes']);
        $container->setParameter('pasaia_sso_client.post_logout_redirect_uri', $config['post_logout_redirect_uri']);
        $container->setParameter('pasaia_sso_client.login_route', $config['login_route']);
    }
}
