<?php

declare(strict_types=1);

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'test' => true,
        'messenger' => [
            'enabled' => true,
        ],
        'serializer' => [
            'enabled' => true,
        ],
        'validation' => [
            'enabled' => true,
        ],
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'php_errors' => [
            'log' => true,
        ],
    ]);

    $container->extension('nelmio_api_doc', [
        'areas' => [
            'default' => [
                'path_patterns' => ['^/api'],
            ],
        ],
    ]);

    $container->extension('stixx_openapi_command', [
        'validation' => [
            'enabled' => false,
            'groups' => ['Custom'],
        ],
    ]);

    $container->parameters()->set('validator.translation_domain', 'validators');
};
