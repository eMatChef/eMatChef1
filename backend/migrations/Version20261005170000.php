<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Charge: Eigentümer, Rückgabepflicht, Bedarfsposition-FK; Charge-Bewegungen mit Übernahme des bisherigen Wareneingangs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS procurement_line_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS return_required BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS owner_kind VARCHAR(16) DEFAULT 'grossanlass' NOT NULL");
        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS owner_department_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_commitment_line ON department_grossanlass_commitment (procurement_line_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_commitment_owner_dept ON department_grossanlass_commitment (owner_department_id)');
        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD CONSTRAINT fk_ga_commitment_line FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD CONSTRAINT fk_ga_commitment_owner_dept FOREIGN KEY (owner_department_id) REFERENCES department (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Bedarfsposition aus item_details.from_line_id übernehmen (JSON bleibt für Leser im Frontend).
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_commitment c
SET procurement_line_id = l.id
FROM activity_grossanlass_procurement_line l
WHERE l.id = (c.item_details->>'from_line_id')
  AND l.department_id = c.department_id
SQL);

        // Rückgabepflicht: Leihe = ja; gewählte Offerte mit Rückgabe ist verlässlicher.
        $this->addSql("UPDATE department_grossanlass_commitment SET return_required = (origin = 'loan')");
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_commitment c
SET return_required = true
FROM activity_grossanlass_procurement_quote q
WHERE q.id = (c.item_details->>'quote_id')
  AND q.return_needed = true
SQL);

        // Eigentümer: Rückgabe = fremd, sonst Grossanlass; Department aus der Gast-Freigabe.
        $this->addSql("UPDATE department_grossanlass_commitment SET owner_kind = CASE WHEN return_required THEN 'external' ELSE 'grossanlass' END");
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_commitment c
SET owner_kind = 'department', owner_department_id = s.guest_department_id
FROM department_grossanlass_guest_share s
WHERE s.commitment_id = c.id
  AND s.kind = 'offer'
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_charge_movement (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    commitment_id CHARACTER(12) NOT NULL,
    kind VARCHAR(24) NOT NULL,
    quantity INT NOT NULL,
    occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    place_id CHARACTER(12) DEFAULT NULL,
    einsatz_id CHARACTER(12) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_charge_movement_commitment ON department_grossanlass_charge_movement (commitment_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_charge_movement_dept ON department_grossanlass_charge_movement (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_charge_movement_place ON department_grossanlass_charge_movement (place_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_charge_movement_einsatz ON department_grossanlass_charge_movement (einsatz_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_charge_movement_user ON department_grossanlass_charge_movement (created_by_user_id)');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD CONSTRAINT fk_ga_charge_movement_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD CONSTRAINT fk_ga_charge_movement_commitment FOREIGN KEY (commitment_id) REFERENCES department_grossanlass_commitment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD CONSTRAINT fk_ga_charge_movement_place FOREIGN KEY (place_id) REFERENCES department_grossanlass_place (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD CONSTRAINT fk_ga_charge_movement_einsatz FOREIGN KEY (einsatz_id) REFERENCES department_grossanlass_einsatz (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD CONSTRAINT fk_ga_charge_movement_user FOREIGN KEY (created_by_user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Bisherigen Wareneingang als Bewegung übernehmen:
        // - Charge «here» (oder schon gepackt / an Firma zurück) = ganze Menge
        // - Kauf-Charge der Bestellung = erhaltene Menge der Bedarfsposition (max. Charge-Menge)
        $this->addSql(<<<'SQL'
INSERT INTO department_grossanlass_charge_movement
    (id, department_id, commitment_id, kind, quantity, occurred_at, note, created_at)
SELECT
    'cm' || substr(md5(x.id || ':legacy-received'), 1, 10),
    x.department_id,
    x.id,
    'received',
    x.qty,
    x.updated_at,
    'Übernommen aus bisherigem Wareneingang',
    NOW()
FROM (
    SELECT
        c.id,
        c.department_id,
        c.updated_at,
        CASE
            WHEN (c.item_details->>'inbound_status') = 'here' OR c.packed OR c.returned_to_firm THEN c.quantity
            WHEN c.origin = 'buy' AND l.id IS NOT NULL THEN
                CASE
                    WHEN l.status = 'erhalten' THEN c.quantity
                    ELSE LEAST(c.quantity, COALESCE((
                        SELECT SUM(w.received_quantity)
                        FROM activity_grossanlass_procurement_line_wish w
                        WHERE w.procurement_line_id = l.id
                    ), 0))
                END
            ELSE 0
        END AS qty
    FROM department_grossanlass_commitment c
    LEFT JOIN activity_grossanlass_procurement_line l ON l.id = c.procurement_line_id
) x
WHERE x.qty > 0
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_charge_movement');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP CONSTRAINT IF EXISTS fk_ga_commitment_owner_dept');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP CONSTRAINT IF EXISTS fk_ga_commitment_line');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_commitment_owner_dept');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_commitment_line');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS owner_department_id');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS owner_kind');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS return_required');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS procurement_line_id');
    }
}
