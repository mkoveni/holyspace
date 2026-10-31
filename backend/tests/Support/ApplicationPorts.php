<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final class ApplicationPorts
{
    public static function reads(ServicesConfigurator|\Symfony\Component\DependencyInjection\Loader\Configurator\DefaultsConfigurator $services): void
    {
        $services->alias(\App\Communications\Application\Port\DeliveryConfiguration::class, \App\Communications\Application\Port\MessageGateway::class)->private();
        $services->alias(\App\Payments\Application\Port\ProviderCatalogue::class, \App\Payments\Application\Port\PaymentGateway::class)->private();
        $services->set(\App\Shared\Infrastructure\Operations\Readiness::class)->private();
        $services->alias(\App\Shared\Application\ReadModel\ReadinessChecks::class, \App\Shared\Infrastructure\Operations\Readiness::class)->private();
        $services->set(\App\People\Application\Query\SearchPeople\SearchPeopleHandler::class)->private();
        $services->set(\App\IdentityAccess\Application\Query\ListPermissions\ListPermissionsHandler::class)->private();
        $services->set(\App\IdentityAccess\Application\Query\ListRoles\ListRolesHandler::class)->private();
        $services->set(\App\Shared\Application\Query\GetWorkflowAudit\GetWorkflowAuditHandler::class)->private();
        $services->set(\App\Shared\Application\Query\GetFieldHistory\GetFieldHistoryHandler::class)->private();
        $services->set(\App\Shared\Application\Query\GetReadiness\GetReadinessHandler::class)->private();

        $services->set(\App\Attendance\Infrastructure\ReadModel\RepositoryAttendanceReadStore::class)->private();
        $services->alias(\App\Attendance\Application\ReadModel\AttendanceReadStore::class, \App\Attendance\Infrastructure\ReadModel\RepositoryAttendanceReadStore::class)->private();
        $services->set(\App\ChildrenMinistry\Infrastructure\ReadModel\RepositoryChildrenMinistryReadStore::class)->private();
        $services->alias(\App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore::class, \App\ChildrenMinistry\Infrastructure\ReadModel\RepositoryChildrenMinistryReadStore::class)->private();
        $services->set(\App\Communications\Infrastructure\ReadModel\RepositoryCommunicationsReadStore::class)->private();
        $services->alias(\App\Communications\Application\ReadModel\CommunicationsReadStore::class, \App\Communications\Infrastructure\ReadModel\RepositoryCommunicationsReadStore::class)->private();
        $services->set(\App\Events\Infrastructure\ReadModel\RepositoryEventReadStore::class)->private();
        $services->alias(\App\Events\Application\ReadModel\EventReadStore::class, \App\Events\Infrastructure\ReadModel\RepositoryEventReadStore::class)->private();
        $services->set(\App\Events\Infrastructure\ReadModel\RepositoryEventRegistrationReadStore::class)->private();
        $services->alias(\App\Events\Application\ReadModel\EventRegistrationReadStore::class, \App\Events\Infrastructure\ReadModel\RepositoryEventRegistrationReadStore::class)->private();
        $services->set(\App\Forms\Infrastructure\ReadModel\RepositoryFormsReadStore::class)->private();
        $services->alias(\App\Forms\Application\ReadModel\FormsReadStore::class, \App\Forms\Infrastructure\ReadModel\RepositoryFormsReadStore::class)->private();
        $services->set(\App\Giving\Infrastructure\ReadModel\RepositoryGivingReadStore::class)->private();
        $services->alias(\App\Giving\Application\ReadModel\GivingReadStore::class, \App\Giving\Infrastructure\ReadModel\RepositoryGivingReadStore::class)->private();
        $services->set(\App\IdentityAccess\Infrastructure\ReadModel\RepositoryUserAccountReadStore::class)->private();
        $services->alias(\App\IdentityAccess\Application\ReadModel\UserAccountReadStore::class, \App\IdentityAccess\Infrastructure\ReadModel\RepositoryUserAccountReadStore::class)->private();
        $services->set(\App\LifeEvents\Infrastructure\ReadModel\RepositoryLifeEventReadStore::class)->private();
        $services->alias(\App\LifeEvents\Application\ReadModel\LifeEventReadStore::class, \App\LifeEvents\Infrastructure\ReadModel\RepositoryLifeEventReadStore::class)->private();
        $services->set(\App\Ministries\Infrastructure\ReadModel\RepositoryMinistriesReadStore::class)->private();
        $services->alias(\App\Ministries\Application\ReadModel\MinistriesReadStore::class, \App\Ministries\Infrastructure\ReadModel\RepositoryMinistriesReadStore::class)->private();
        $services->set(\App\Payments\Infrastructure\ReadModel\RepositoryPaymentReadStore::class)->private();
        $services->alias(\App\Payments\Application\ReadModel\PaymentReadStore::class, \App\Payments\Infrastructure\ReadModel\RepositoryPaymentReadStore::class)->private();
        $services->set(\App\Reporting\Infrastructure\ReadModel\RepositoryReportingReadStore::class)->private();
        $services->alias(\App\Reporting\Application\ReadModel\ReportingReadStore::class, \App\Reporting\Infrastructure\ReadModel\RepositoryReportingReadStore::class)->private();
        $services->set(\App\ServicePlanning\Infrastructure\ReadModel\RepositoryServicePlanningReadStore::class)->private();
        $services->alias(\App\ServicePlanning\Application\ReadModel\ServicePlanningReadStore::class, \App\ServicePlanning\Infrastructure\ReadModel\RepositoryServicePlanningReadStore::class)->private();
        $services->set(\App\Volunteering\Infrastructure\ReadModel\RepositoryVolunteeringReadStore::class)->private();
        $services->alias(\App\Volunteering\Application\ReadModel\VolunteeringReadStore::class, \App\Volunteering\Infrastructure\ReadModel\RepositoryVolunteeringReadStore::class)->private();
    }

    public static function access(ServicesConfigurator|\Symfony\Component\DependencyInjection\Loader\Configurator\DefaultsConfigurator $services): void
    {
        $services->set(\App\Shared\Infrastructure\Security\SymfonyAccessControl::class);
        $services->alias(\App\Shared\Application\Security\AccessControl::class, \App\Shared\Infrastructure\Security\SymfonyAccessControl::class);
    }

    public static function identity(ServicesConfigurator|\Symfony\Component\DependencyInjection\Loader\Configurator\DefaultsConfigurator $services): void
    {
        self::access($services);
        $services->set(\App\IdentityAccess\Application\Query\GetCurrentAccount\GetCurrentAccountHandler::class)->private();
        $services->set(\App\IdentityAccess\Infrastructure\Security\JwtVerifiedTokenIssuer::class)->private();
        $services->alias(\App\IdentityAccess\Application\Port\VerifiedTokenIssuer::class, \App\IdentityAccess\Infrastructure\Security\JwtVerifiedTokenIssuer::class)->private();
        $services->alias(\App\IdentityAccess\Application\Port\SecurityStateReader::class, \App\IdentityAccess\Infrastructure\Persistence\DbalSecurityStateStore::class);
        foreach ([
            \App\IdentityAccess\Application\Port\RecoveryCodeDelivery::class => \App\IdentityAccess\Infrastructure\Security\PrivateRecoveryCodeFile::class,
            \App\IdentityAccess\Application\Port\AuthenticatedAccount::class => \App\Shared\Infrastructure\Security\SymfonyAuthenticatedAccount::class,
            \App\IdentityAccess\Application\Port\SecurityStateStore::class => \App\IdentityAccess\Infrastructure\Persistence\DbalSecurityStateStore::class,
            \App\IdentityAccess\Application\Port\SecretCipher::class => \App\IdentityAccess\Infrastructure\Security\AccountSecretCipher::class,
            \App\IdentityAccess\Application\Port\Authenticator::class => \App\IdentityAccess\Infrastructure\Security\TotpAuthenticator::class,
        ] as $port => $adapter) {
            $services->set($adapter);
            $services->alias($port, $adapter)->public();
        }
    }
}
