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
    $container->extension('nelmio_api_doc', [
        'documentation' => [
            'components' => [
                'responses' => [
                    'InvalidRequestProblemDetailsResponse' => [
                        'description' => 'Overridden RFC7807 Problem Details',
                    ],
                ],
            ],
        ],
    ]);
};
