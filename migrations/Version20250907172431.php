<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907172431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_products ADD products_data JSON DEFAULT NULL, ADD devices_data JSON DEFAULT NULL, ADD products_count INT DEFAULT NULL, ADD devices_count INT DEFAULT NULL, ADD products_subtotal DOUBLE PRECISION DEFAULT NULL, ADD devices_subtotal DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_products DROP products_data, DROP devices_data, DROP products_count, DROP devices_count, DROP products_subtotal, DROP devices_subtotal');
    }
}
