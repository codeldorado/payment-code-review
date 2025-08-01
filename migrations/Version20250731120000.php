<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add subscription table for rebilling functionality
 */
final class Version20250731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription table for rebilling functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (
            id SERIAL NOT NULL, 
            uuid UUID NOT NULL, 
            customer_id VARCHAR(255) NOT NULL, 
            amount DOUBLE PRECISION NOT NULL, 
            currency VARCHAR(3) NOT NULL, 
            status VARCHAR(20) NOT NULL, 
            frequency VARCHAR(50) NOT NULL, 
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            next_billing_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            last_billing_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
            cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
            billing_cycle INT DEFAULT 0 NOT NULL, 
            metadata TEXT DEFAULT NULL, 
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE INDEX IDX_subscriptions_customer_id ON subscriptions (customer_id)');
        $this->addSql('CREATE INDEX IDX_subscriptions_status ON subscriptions (status)');
        $this->addSql('CREATE INDEX IDX_subscriptions_next_billing_date ON subscriptions (next_billing_date)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_subscriptions_uuid ON subscriptions (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
    }
}