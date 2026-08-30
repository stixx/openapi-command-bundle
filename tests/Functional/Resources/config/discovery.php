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
        'php_errors' => [
            'log' => false,
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
            'enabled' => true,
        ],
        // The test application keeps its commands in App/Command rather than the default src/.
        'command_paths' => ['%kernel.project_dir%/App/Command'],
    ]);

    $container->parameters()->set('validator.translation_domain', 'validators');
};
