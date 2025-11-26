<?php

declare(strict_types=1);

namespace Stixx\OpenApiCommandBundle\Serializer\Normalizer;

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ConstraintViolationListNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof ConstraintViolationListInterface;
    }

    /**
     * @param ConstraintViolationListInterface $data
     * @return array<int, array<string, mixed>>
     */
    public function normalize($data, string $format = null, array $context = []): array
    {
        $out = [];
        foreach ($data as $violation) {
            if ($violation instanceof ConstraintViolationInterface) {
                $out[] = $this->normalizer->normalize($violation, $format, $context);
            }
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ConstraintViolationListInterface::class => true];
    }
}
