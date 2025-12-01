<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005163204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FA76ED395');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FE238517C');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75F2FAE4625');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FE238517C FOREIGN KEY (order_ref_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75F2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) DEFAULT 0 NOT NULL, ADD token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75F2FAE4625');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FA76ED395');
        $this->addSql('ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FE238517C');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75F2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FE238517C FOREIGN KEY (order_ref_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE user DROP is_active, DROP token_expires_at');
    }
}
