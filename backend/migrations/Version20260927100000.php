<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Wunsch ist erst in Beschaffung, wenn die Position eine Kategorie hat';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE activity_grossanlass_wish_line AS w
            SET status = 'requested'
            WHERE w.status = 'accepted'
            AND NOT EXISTS (
                SELECT 1
                FROM activity_grossanlass_procurement_line_wish lw
                INNER JOIN activity_grossanlass_procurement_line p ON p.id = lw.procurement_line_id
                WHERE lw.wish_line_id = w.id
                  AND p.category_id IS NOT NULL
            )");
        $this->addSql("UPDATE activity_grossanlass_wish_response AS r
            SET status = 'requested'
            WHERE r.status = 'accepted'
            AND EXISTS (
                SELECT 1 FROM activity_grossanlass_wish_line w
                WHERE w.response_id = r.id AND w.status = 'requested'
            )");
    }

    public function down(Schema $schema): void
    {
    }
}
