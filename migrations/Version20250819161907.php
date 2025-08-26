<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819161907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, brand_id INT NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, ingredients VARCHAR(255) NOT NULL, usage_advice VARCHAR(255) NOT NULL, stock INT NOT NULL, INDEX IDX_92FB68E12469DE2 (category_id), INDEX IDX_92FB68E44F5D008 (brand_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE device_skin_type (device_id INT NOT NULL, skin_type_id INT NOT NULL, INDEX IDX_A24FF4F194A4C7D4 (device_id), INDEX IDX_A24FF4F13681431C (skin_type_id), PRIMARY KEY(device_id, skin_type_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addSql('ALTER TABLE device_skin_type ADD CONSTRAINT FK_A24FF4F194A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device_skin_type ADD CONSTRAINT FK_A24FF4F13681431C FOREIGN KEY (skin_type_id) REFERENCES skin_type (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E12469DE2');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E44F5D008');
        $this->addSql('ALTER TABLE device_skin_type DROP FOREIGN KEY FK_A24FF4F194A4C7D4');
        $this->addSql('ALTER TABLE device_skin_type DROP FOREIGN KEY FK_A24FF4F13681431C');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE device_skin_type');
    }
}
