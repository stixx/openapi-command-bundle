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
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiProblemNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(private readonly bool $debug)
    {
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ApiProblemException;
    }

    /**
     * @param ApiProblemException $data
     *
     * @return array{type:string,title:string,status:int,detail?:string,instance?:string,violations?:mixed}
     */
    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $payload = [
            'type' => $data->getType(),
            'title' => $data->getTitle(),
            'status' => $data->getStatusCode(),
        ];

        if ($data->getInstance() !== null) {
            $payload['instance'] = $data->getInstance();
        }

        if ($this->debug && $data->getDetail() !== null) {
            $payload['detail'] = $data->getDetail();
        }

        $violations = $data->getViolations();
        if ($violations !== null && $violations !== []) {
            $payload['violations'] = $this->normalizer->normalize($violations, $format, $context);
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
