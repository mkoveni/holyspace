<?php

declare(strict_types=1);

namespace App\Tests\Payments\Application;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Domain\IdentityException;
use App\Payments\Application\Command\ApprovePaymentReceipt\ApprovePaymentReceiptCommand;
use App\Payments\Application\Command\ApprovePaymentReceipt\ApprovePaymentReceiptHandler;
use App\Payments\Application\Command\CheckProviderConnection\CheckProviderConnectionCommand;
use App\Payments\Application\Command\CheckProviderConnection\CheckProviderConnectionHandler;
use App\Payments\Application\Command\CloseWebhookOperation\CloseWebhookOperationCommand;
use App\Payments\Application\Command\CloseWebhookOperation\CloseWebhookOperationHandler;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentCommand;
use App\Payments\Application\Command\ReconcilePayment\ReconcilePaymentHandler;
use App\Payments\Application\Command\RecordRefund\RecordRefundCommand;
use App\Payments\Application\Command\RecordRefund\RecordRefundHandler;
use App\Payments\Application\Command\RegisterYocoWebhook\RegisterYocoWebhookCommand;
use App\Payments\Application\Command\RegisterYocoWebhook\RegisterYocoWebhookHandler;
use App\Payments\Application\Command\RemoveYocoWebhook\RemoveYocoWebhookCommand;
use App\Payments\Application\Command\RemoveYocoWebhook\RemoveYocoWebhookHandler;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Command\RetryPaymentPosting\RetryPaymentPostingCommand;
use App\Payments\Application\Command\RetryPaymentPosting\RetryPaymentPostingHandler;
use App\Payments\Application\Command\RetryPaymentReceipt\RetryPaymentReceiptCommand;
use App\Payments\Application\Command\RetryPaymentReceipt\RetryPaymentReceiptHandler;
use App\Payments\Application\Command\SaveProviderSettings\SaveProviderSettingsCommand;
use App\Payments\Application\Command\SaveProviderSettings\SaveProviderSettingsHandler;
use App\Payments\Application\Command\StartPayment\StartPaymentCommand;
use App\Payments\Application\Command\StartPayment\StartPaymentHandler;
use App\Payments\Application\Query\GetPayment\GetPaymentHandler;
use App\Payments\Application\Query\GetPayment\GetPaymentQuery;
use App\Payments\Application\Query\GetPaymentReceipt\GetPaymentReceiptHandler;
use App\Payments\Application\Query\GetPaymentReceipt\GetPaymentReceiptQuery;
use App\Payments\Application\Query\GetPaymentSettings\GetPaymentSettingsHandler;
use App\Payments\Application\Query\GetPaymentSettings\GetPaymentSettingsQuery;
use App\Payments\Application\Query\ListPaymentProviders\ListPaymentProvidersHandler;
use App\Payments\Application\Query\ListPaymentProviders\ListPaymentProvidersQuery;
use App\Payments\Application\Query\ListPayments\ListPaymentsHandler;
use App\Payments\Application\Query\ListPayments\ListPaymentsQuery;
use App\Payments\Application\Query\ListYocoWebhooks\ListYocoWebhooksHandler;
use App\Payments\Application\Query\ListYocoWebhooks\ListYocoWebhooksQuery;
use App\Shared\Domain\CalendarDate;
use App\Tests\Giving\Application\RecordingPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Java's `*HandlerSecurityTest`s: every staff handler refuses a spoofed actor (or a caller without the permission)
 * before it touches a repository, reader, provider, vault, clock or transaction. Collaborators are built by reflection;
 * every interface is a mock that fails the test if called.
 */
final class PaymentHandlersSecurityTest extends TestCase
{
    private const string CALLER = '11111111-1111-4111-8111-111111111111';
    private const string SPOOF = '99999999-9999-4999-8999-999999999999';
    private const string ID = '22222222-2222-4222-8222-222222222222';

    /** @return iterable<string, array{class-string, object}> */
    public static function handlers(): iterable
    {
        $s = self::SPOOF;
        $id = self::ID;
        yield 'start' => [StartPaymentHandler::class, new StartPaymentCommand($s, $id, $id, $id, 10000, 'yoco', false)];
        yield 'resume' => [ResumeCheckoutHandler::class, new ResumeCheckoutCommand($s, $id)];
        yield 'refund' => [RecordRefundHandler::class, new RecordRefundCommand($s, $id, $id, 'r', 100, CalendarDate::of(2026, 1, 1), 'reason')];
        yield 'reconcile' => [ReconcilePaymentHandler::class, new ReconcilePaymentCommand($s, $id, $id, $id, 0, 'BANK', 0, 100, 'note')];
        yield 'posting' => [RetryPaymentPostingHandler::class, new RetryPaymentPostingCommand($s, $id, 0)];
        yield 'settings' => [SaveProviderSettingsHandler::class, new SaveProviderSettingsCommand($s, 'yoco', 'test', 0, false, false, null, null, 'r', null)];
        yield 'check' => [CheckProviderConnectionHandler::class, new CheckProviderConnectionCommand($s, 'yoco', 'test', 0)];
        yield 'approve' => [ApprovePaymentReceiptHandler::class, new ApprovePaymentReceiptCommand($s, $id, $id, $id, $id, 'h', 'evidence')];
        yield 'retry receipt' => [RetryPaymentReceiptHandler::class, new RetryPaymentReceiptCommand($s, $id, $id, 0, 'reason')];
        yield 'register' => [RegisterYocoWebhookHandler::class, new RegisterYocoWebhookCommand($s, $id, 'test', 0, 'reason')];
        yield 'remove' => [RemoveYocoWebhookHandler::class, new RemoveYocoWebhookCommand($s, $id, 'test', 0, 'reason', 'sub_x')];
        yield 'close' => [CloseWebhookOperationHandler::class, new CloseWebhookOperationCommand($s, $id, 0, 'reason')];
        yield 'list webhooks' => [ListYocoWebhooksHandler::class, new ListYocoWebhooksQuery($s, 'test')];
        yield 'list' => [ListPaymentsHandler::class, new ListPaymentsQuery(25, 0, false)];
        yield 'get' => [GetPaymentHandler::class, new GetPaymentQuery($id)];
        yield 'providers' => [ListPaymentProvidersHandler::class, new ListPaymentProvidersQuery()];
        yield 'receipt' => [GetPaymentReceiptHandler::class, new GetPaymentReceiptQuery($id, $id, null)];
        yield 'read settings' => [GetPaymentSettingsHandler::class, new GetPaymentSettingsQuery()];
    }

    /** @param class-string $handler */
    #[DataProvider('handlers')]
    public function testRefusesBeforeTouchingAnyCollaborator(string $handler, object $input): void
    {
        $instance = $this->build($handler);
        $this->expectException(IdentityException::class);
        \assert(method_exists($instance, 'handle'));
        $instance->handle($input);
    }

    private function build(string $class): object
    {
        \assert(class_exists($class));
        $reflection = new ReflectionClass($class);
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            \assert($type instanceof ReflectionNamedType);
            $name = $type->getName();
            $arguments[] = match (true) {
                PermissionChecker::class === $name => new RecordingPermissions(self::CALLER, []),
                'string' === $name => 'UTC',
                'iterable' === $name => [],
                interface_exists($name) => $this->untouched($name),
                default => $this->build($name),
            };
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /** @param class-string $interface */
    private function untouched(string $interface): object
    {
        $mock = $this->createMock($interface);
        $mock->expects(self::never())->method(self::anything());

        return $mock;
    }
}
