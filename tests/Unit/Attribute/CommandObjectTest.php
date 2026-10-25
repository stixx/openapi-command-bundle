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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Controller\CommandController;

final class CommandObjectTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        // Act
        $attribute = new CommandObject();

        // Assert
        self::assertNull($attribute->class);
        self::assertNull($attribute->controller);
    }

    public function testConstructorCustomValues(): void
    {
        // Act
        $attribute = new CommandObject(class: stdClass::class, controller: CommandController::class);

        // Assert
        self::assertSame(stdClass::class, $attribute->class);
        self::assertSame(CommandController::class, $attribute->controller);
    }
}
