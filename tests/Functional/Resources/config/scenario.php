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

use Stixx\OpenApiCommandBundle\Tests\Functional\App\Handler\{CreateBookHandler, DeleteBookHandler, UpdateBookHandler};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->public();

    $services
        ->set(CreateBookHandler::class)
            ->tag('messenger.message_handler');

    $services
        ->set(UpdateBookHandler::class)
            ->tag('messenger.message_handler');

    $services
        ->set(DeleteBookHandler::class)
            ->tag('messenger.message_handler');

    $services->set(PhpDocExtractor::class);
    $services->set(ReflectionExtractor::class);
    $services->set(PropertyInfoExtractor::class)
        ->arg('$listExtractors', [service(ReflectionExtractor::class)])
        ->arg('$typeExtractors', [service(PhpDocExtractor::class), service(ReflectionExtractor::class)])
        ->arg('$accessExtractors', [service(ReflectionExtractor::class)])
        ->arg('$initializableExtractors', [service(ReflectionExtractor::class)]);

    $services->set(ObjectNormalizer::class)
        ->arg('$propertyTypeExtractor', service(PropertyInfoExtractor::class))
        ->tag('serializer.normalizer');

    $services->set(ArrayDenormalizer::class)
        ->tag('serializer.normalizer');

    $services->set(JsonEncoder::class)
        ->tag('serializer.encoder');

    $services->set('test.serializer', Serializer::class)
        ->arg('$normalizers', [
            service(ArrayDenormalizer::class),
            service(ObjectNormalizer::class),
        ])
        ->arg('$encoders', [
            service(JsonEncoder::class),
        ]);

    $services->alias('serializer', 'test.serializer');
};
