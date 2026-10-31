<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261021000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostgreSQL baseline with native UUIDs, JSONB documents, and UTC timestamps.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Use the MySQL migration path for other platforms.');
        foreach (explode(';', file_get_contents(__DIR__.'/schema.sql')) as $sql) {
            if ('' !== trim($sql)) {
                $this->addSql($sql);
            }
        }
        foreach ([
            'attendance.manage' => 'Manage attendance workflows',
            'children.manage' => 'Manage children workflows',
            'children.station' => 'children.station',
            'children.support' => 'children.support',
            'communications.manage' => 'Manage communications workflows',
            'events.manage' => 'Manage events',
            'events.read' => 'Read events',
            'families.manage' => 'Manage families',
            'families.read' => 'Read families',
            'followups.manage' => 'followups.manage',
            'followups.work' => 'followups.work',
            'forms.manage' => 'Manage church forms',
            'forms.read' => 'Read church forms',
            'giving.manage' => 'Manage giving workflows',
            'identity.manage' => 'Manage accounts, roles and permissions',
            'life_events.manage' => 'Manage life_events',
            'life_events.read' => 'Read life_events',
            'member.portal' => 'Own-record member portal with mandatory authenticator verification',
            'ministries.lead' => 'Access own active leadership groups and meeting attendance',
            'ministries.manage' => 'Manage ministries workflows',
            'payments.manage' => 'Manage hosted payments and reconciliation',
            'people.manage' => 'Manage people',
            'people.read' => 'Read people',
            'reports.manage' => 'reports.manage',
            'reports.read' => 'reports.read',
            'service_planning.manage' => 'service_planning.manage',
            'service_planning.read' => 'service_planning.read',
            'volunteering.manage' => 'Manage volunteering workflows',
            'volunteering.serve' => 'volunteering.serve',
        ] as $code => $description) {
            $this->addSql('INSERT INTO permissions(id, code, description, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [Uuid::v7()->toRfc4122(), $code, $description]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restore a verified backup instead of dropping business data.');
    }
}
