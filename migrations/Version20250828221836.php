<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828221836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398F5B7AF75');
        $this->addSql('DROP INDEX IDX_F5299398F5B7AF75 ON `order`');
        $this->addSql('ALTER TABLE `order` ADD source_address_id INT DEFAULT NULL, ADD street VARCHAR(255) NOT NULL, ADD city VARCHAR(100) NOT NULL, ADD postal_code VARCHAR(10) NOT NULL, DROP address_id');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993988903DE51 FOREIGN KEY (source_address_id) REFERENCES address (id)');
        $this->addSql('CREATE INDEX IDX_F52993988903DE51 ON `order` (source_address_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993988903DE51');
        $this->addSql('DROP INDEX IDX_F52993988903DE51 ON `order`');
        $this->addSql('ALTER TABLE `order` ADD address_id INT NOT NULL, DROP source_address_id, DROP street, DROP city, DROP postal_code');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('CREATE INDEX IDX_F5299398F5B7AF75 ON `order` (address_id)');
    }
}
