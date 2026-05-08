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

namespace Stixx\OpenApiCommandBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Kernel;
use Symfony\Component\HttpFoundation\Request;

final class ScenarioTest extends AbstractKernelTestCase
{
    #[WithoutErrorHandler]
    public function testCreateBookSuccess(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        });

        $payload = [
            'title' => 'Domain-Driven Design',
            'author' => 'Eric Evans',
        ];

        $request = Request::create(
            uri: '/api/books',
            method: 'POST',
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent() ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('Domain-Driven Design', $data['title'] ?? null);
        self::assertSame('Eric Evans', $data['author'] ?? null);
        self::assertArrayHasKey('id', $data);
    }

    #[WithoutErrorHandler]
    public function testUpdateBookSuccess(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        });

        $payload = [
            'title' => 'Domain-Driven Design - Revised',
            'author' => 'Eric Evans',
        ];

        $request = Request::create(
            uri: '/api/books/1',
            method: 'PUT',
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent() ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('1', $data['id'] ?? null);
        self::assertSame('Domain-Driven Design - Revised', $data['title'] ?? null);
    }

    #[WithoutErrorHandler]
    public function testDeleteBookSuccess(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        });

        $request = Request::create(
            uri: '/api/books/1',
            method: 'DELETE'
        );

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(204, $response->getStatusCode());
        self::assertEmpty($response->getContent());
    }

    #[WithoutErrorHandler]
    public function testCreateBookValidationError(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        });

        $payload = [
            'title' => '', // NotBlank
        ];

        $request = Request::create(
            uri: '/api/books',
            method: 'POST',
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent() ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(422, $data['status'] ?? null);
        self::assertSame('Validation failed', $data['detail'] ?? null);
        self::assertArrayHasKey('violations', $data);
    }

    #[WithoutErrorHandler]
    public function testUpdateBookValidationError(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        });

        $payload = [
            'title' => '', // NotBlank
        ];

        $request = Request::create(
            uri: '/api/books/1',
            method: 'PUT',
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent() ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(422, $data['status'] ?? null);
        self::assertSame('Validation failed', $data['detail'] ?? null);
        self::assertArrayHasKey('violations', $data);
    }
}
