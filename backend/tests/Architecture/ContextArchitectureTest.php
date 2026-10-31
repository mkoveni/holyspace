<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Shared\Application\CQRS\Command;
use App\Shared\Application\CQRS\Query;
use Generator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ContextArchitectureTest extends TestCase
{
    private const SOURCE = __DIR__.'/../../src/';
    private const WRITE_METHOD = '/^(save|create|update|delete|remove|insert|expire|reserve|confirm|release|cancel|post|audit|send|drain|run|write|publish(?!ed)|submit|record|assign(?!ees)|link|change|enable|disable|settle)/i';

    public function testInnerLayersOwnTheirDependencies(): void
    {
        $violations = [];
        foreach ($this->files() as $file) {
            $path = substr($file, strlen(self::SOURCE));
            $layer = explode('/', $path)[1] ?? '';
            if (!in_array($layer, ['Domain', 'Application', 'Presentation'], true)) {
                continue;
            }
            $code = $this->code($file);
            if ('Application' === $layer) {
                preg_match_all('/App\\\\(\w+)\\\\Domain\\\\Repository\\\\\w+/', $code, $repositories);
                foreach ($repositories[1] as $owner) {
                    if ($owner !== explode('/', $path)[0]) {
                        $violations[] = $path.': use the other context application contract instead of its domain repository';
                    }
                }
            }

            if (in_array($layer, ['Domain', 'Application'], true)) {
                if (preg_match('/App\\\\\w+\\\\(?:Infrastructure|Presentation)\\\\|Doctrine\\\\|Symfony\\\\(?:Bundle|Component\\\\(?:HttpFoundation|HttpKernel|Security|DependencyInjection|RateLimiter))\\\\/', $code)) {
                    $violations[] = $path.': inner layer depends on an adapter or framework service';
                }
            }
            if ('Domain' === $layer && preg_match('/App\\\\\w+\\\\Application\\\\/', $code)) {
                $violations[] = $path.': domain depends on application';
            }
            if ('Presentation' === $layer && preg_match('/App\\\\\w+\\\\(?:Infrastructure|Domain\\\\Repository)\\\\/', $code)) {
                $violations[] = $path.': transport bypasses an application boundary';
            }
        }
        self::assertSame([], $violations);
    }

    public function testEveryUseCaseHasOneTypedEntryPointAndAnImmutableMessage(): void
    {
        $contexts = [];
        foreach ($this->files() as $file) {
            if (!preg_match('~([^/]+)/Application/(Command|Query)/.*Handler\.php$~', $file, $match)) {
                continue;
            }
            $contexts[$match[1]][$match[2]] = true;
            $handler = new ReflectionClass($this->className($file));
            $methods = array_values(array_filter($handler->getMethods(ReflectionMethod::IS_PUBLIC), static fn ($method) => !$method->isConstructor()));
            self::assertCount(1, $methods, $handler->getName());
            self::assertSame('__invoke', $methods[0]->name, $handler->getName());
            self::assertCount(1, $methods[0]->getParameters(), $handler->getName());
            $type = $methods[0]->getParameters()[0]->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type, $handler->getName());
            $message = new ReflectionClass($type->getName());
            self::assertTrue($message->isReadOnly(), $message->getName());
            self::assertTrue($message->implementsInterface('Command' === $match[2] ? Command::class : Query::class), $message->getName());
            foreach ($message->getProperties() as $property) {
                self::assertNotContains((string) $property->getType(), ['Closure', 'callable', 'mixed', 'object'], $message->getName().'::$'.$property->name.' must carry data');
            }
        }
        foreach (glob(self::SOURCE.'*/Application', GLOB_ONLYDIR) as $application) {
            $context = basename(dirname($application));
            self::assertArrayHasKey('Query', $contexts[$context] ?? [], $context.' must expose queries');
            // Shared contains technical contracts and queries; business commands belong to the owning context.
            if ('Shared' !== $context) {
                self::assertArrayHasKey('Command', $contexts[$context] ?? [], $context.' must expose commands');
            }
        }
    }

    public function testQueriesOnlyReceiveReadCapabilities(): void
    {
        $visited = [];
        foreach ($this->files() as $file) {
            if (str_contains($file, '/Application/Query/') && str_ends_with($file, 'Handler.php')) {
                $this->assertReadOnlyDependency($this->className($file), $visited);
            }
        }
    }

    private function assertReadOnlyDependency(string $class, array &$visited): void
    {
        if (isset($visited[$class]) || !str_starts_with($class, 'App\\')) {
            return;
        }
        $visited[$class] = true;
        self::assertDoesNotMatchRegularExpression('/\\\\(?:Infrastructure|Presentation|Command)\\\\|\\\\Domain\\\\Repository\\\\|WorkflowTransaction|IntegrationProcessor/', $class);
        $reflection = new ReflectionClass($class);
        if ($reflection->isInterface()) {
            foreach ($reflection->getMethods() as $method) {
                self::assertDoesNotMatchRegularExpression(self::WRITE_METHOD, $method->name, $class.' exposes writes to queries');
            }

            return;
        }
        if (str_contains($class, '\\Application\\')) {
            $code = $this->code($reflection->getFileName());
            self::assertDoesNotMatchRegularExpression('/->(?:save|insert|update|delete|expire|reserve|confirm|release|cancel|send|drainAll|run|write|publish|submit)\s*\(/', $code, $class.' mutates state');
            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $this->assertReadOnlyDependency($type->getName(), $visited);
                }
            }
        }
    }

    private function files(): Generator
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE)) as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                yield $file->getPathname();
            }
        }
    }

    private function className(string $file): string
    {
        return 'App\\'.str_replace('/', '\\', substr($file, strlen(self::SOURCE), -4));
    }

    private function code(string $file): string
    {
        $code = '';
        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }

        return $code;
    }
}
