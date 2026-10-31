<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ConfigureOnlineFunds;

use App\Giving\Application\Port\OnlineGivingLedger;
use App\Payments\Domain\Model\OnlineGivingSettings;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ConfigureOnlineFundsHandler
{
    public function __construct(
        private PaymentRepository $records,
        private OnlineGivingLedger $giving,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ConfigureOnlineFundsCommand $message): array
    {
        $fundIds = $message->fundIds;
        $version = $message->version;

        return $this->transactions->run(function () use ($fundIds, $version) {
            $settings = $this->settings();
            $this->version($settings->version, $version);
            foreach ($fundIds as $id) {
                if (!in_array($id, array_column($this->giving->funds(), 'id'), true)) {
                    throw new InvalidArgumentException('Select an active giving fund.');
                }
            }
            $this->records->save($settings->configure($fundIds));

            return get_object_vars($this->settings());
        });
    }

    private function settings(): OnlineGivingSettings
    {
        return $this->records->find('settings', OnlineGivingSettings::ID) ?? new OnlineGivingSettings();
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This payment changed. Reload before retrying.');
        }
    }
}
