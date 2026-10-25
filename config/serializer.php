<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ApiProblemNormalizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services->set(ApiProblemNormalizer::class);
};
