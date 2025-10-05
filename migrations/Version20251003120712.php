<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003120712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE promo_code_usage (id INT AUTO_INCREMENT NOT NULL, promo_code_id INT NOT NULL, user_id INT DEFAULT NULL, order_ref_id INT NOT NULL, used_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', discount_applied DOUBLE PRECISION NOT NULL, INDEX IDX_6025E75F2FAE4625 (promo_code_id), INDEX IDX_6025E75FA76ED395 (user_id), INDEX IDX_6025E75FE238517C (order_ref_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75F2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FE238517C FOREIGN KEY (order_ref_id) REFERENCES `order` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75F2FAE4625');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FA76ED395');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FE238517C');
        $this->addSql('DROP TABLE promo_code_usage');
    }
}
