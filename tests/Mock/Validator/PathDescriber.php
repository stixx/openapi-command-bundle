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

namespace Stixx\OpenApiCommandBundle\Tests\Mock\Validator;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Server;
use OpenApi\Context;

final class PathDescriber implements DescriberInterface
{
    public int $calls = 0;

    /**
     * @param class-string|null $loads
     */
    public function __construct(
        public string $path,
        private readonly ?string $loads = null,
        private readonly ?string $schema = null,
        private readonly bool $servers = false,
    ) {
    }

    public function describe(OpenApi $api): void
    {
        ++$this->calls;

        if ($this->loads !== null) {
            class_exists($this->loads);
        }

        $context = new Context(['version' => '3.0.0'], null);
        $api->info = new Info(['title' => 'Test', 'version' => '1.0.0', '_context' => $context]);
        $servers = fn (string $url): array => $this->servers ? ['servers' => [new Server(['url' => $url, '_context' => $context])]] : [];
        $api->paths = [
            new PathItem([
                'path' => $this->path,
                'post' => new Post([
                    'responses' => [new Response(['response' => 200, 'description' => 'ok', '_context' => $context])],
                    '_context' => $context,
                ] + $servers('https://operation.example.com/v3')),
                '_context' => $context,
            ] + $servers('https://path.example.com/v2')),
        ];
        if ($this->servers) {
            $api->servers = [new Server(['url' => 'https://api.example.com/v1', '_context' => $context])];
        }

        if ($this->schema !== null) {
            Util::getSchema($api, $this->schema)->type = 'object';
        }
    }
}
