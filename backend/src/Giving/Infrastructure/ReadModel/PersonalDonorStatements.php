<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\ReadModel;

use App\Giving\Application\Query\GetGivingReport\GetGivingReportHandler;
use App\Giving\Application\Query\GetGivingReport\GetGivingReportQuery;
use App\Giving\Application\ReadModel\DonorStatements;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DonorStatements::class)]
final readonly class PersonalDonorStatements implements DonorStatements
{
    public function __construct(private GetGivingReportHandler $getGivingReportHandler)
    {
    }

    public function statement(string $donorId, string $from, string $to): array
    {
        return ($this->getGivingReportHandler)(new GetGivingReportQuery($from, $to, $donorId));
    }
}
