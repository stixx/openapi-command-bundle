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

namespace Stixx\OpenApiCommandBundle\Serializer\Normalizer;

use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Reads the public properties of the problem, as Nelmio does when it documents the model.
 *
 * @internal
 */
final class ApiProblemNormalizer implements NormalizerInterface
{
    private const array MEMBERS = ['type', 'title', 'status', 'detail', 'instance'];

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ApiProblemException;
    }

    /**
     * @param ApiProblemException $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $members = get_object_vars($data->getProblem());

        $payload = [];
        foreach (array_replace(array_fill_keys(self::MEMBERS, null), $members) as $name => $value) {
            if ($value !== null) {
                $payload[(string) $name] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ApiProblemException::class => true];
    }
}
