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

use Stixx\OpenApiCommandBundle\Tests\Functional\App\RecordingLogger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('stixx_openapi_command', [
        'command_paths' => ['%kernel.project_dir%/*/Nothing'],
    ]);

    $container->services()->set('logger', RecordingLogger::class);
};
