<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001083047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, brand_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value DOUBLE PRECISION NOT NULL, end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_3D8C939E77153098 (code), INDEX IDX_3D8C939E12469DE2 (category_id), INDEX IDX_3D8C939E44F5D008 (brand_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT FK_3D8C939E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT FK_3D8C939E44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_3D8C939E12469DE2');
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_3D8C939E44F5D008');
        $this->addSql('DROP TABLE promo_code');
    }
}
