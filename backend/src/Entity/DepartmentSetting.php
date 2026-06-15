<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DepartmentSetting - Key/Value-Einstellungen pro Department
 * 
 * Speichert konfigurierbare Werte wie:
 * - general.timezone = "Europe/Zurich"
 * - general.date_format = "dd.MM.yyyy"
 * - activity.default_time_start = "14:00"
 * - activity.default_time_end = "17:00"
 * - activity.material_lead_minutes = "60"
 * - activity.material_lag_minutes = "60"
 * - activity.camp_material_lead_days = "1"
 * - activity.camp_material_lag_days = "1"
 * - rental.amortization_* = Standardwerte für den Vermiet-Preisrechner (Abteilung)
 * - calendar.fcal_geo_id = Geo-ID (feiertagskalender.ch) für Schulferien im Aktivitäts-Kalender
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_setting')]
#[ORM\UniqueConstraint(name: 'uq_department_setting_key', columns: ['department_id', 'setting_key'])]
class DepartmentSetting
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'setting_key', type: 'string', length: 100)]
    private string $settingKey;

    #[ORM\Column(name: 'setting_value', type: 'text')]
    private string $settingValue;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    // === Getters & Setters ===

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $settingValue): self
    {
        $this->settingValue = $settingValue;
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // === Helpers ===

    /**
     * Standard-Werte für Allgemeine Einstellungen
     */
    public static function getGeneralDefaults(): array
    {
        return [
            'general.timezone' => 'Europe/Zurich',
            'general.date_format' => 'dd.MM.yyyy',
            'general.time_format' => 'HH:mm',
            'general.public_contact_email' => '',
            'general.public_contact_note' => '',
            'general.public_show_contact_form' => '1',
            'general.public_show_contact_email' => '1',
            'general.public_show_contact_note' => '1',
            // email | in_app | both — Hinweise vom QR-Kontaktformular
            'general.public_found_contact_delivery' => 'both',
        ];
    }

    /**
     * Standard-Werte für Aktivitäts-Einstellungen
     */
    public static function getActivityDefaults(): array
    {
        return [
            'activity.default_time_start' => '14:00',
            'activity.default_time_end' => '17:00',
            'activity.material_lead_minutes' => '60',
            'activity.material_lag_minutes' => '60',
            'activity.camp_material_lead_days' => '1',
            'activity.camp_material_lag_days' => '1',
        ];
    }

    /**
     * Standardwerte für Vermietung: Amortisations-/Preisrechner (pro Department)
     */
    public static function getCalendarDefaults(): array
    {
        return [
            'calendar.fcal_geo_id' => '',
        ];
    }

    public static function getRentalAmortizationDefaults(): array
    {
        // Erwartete interne Nutzung / Jahr (typisch: ~14 Sommerlager + 4 Pfingsten + 7 Herbst + ~5 Aktivitäten ≈ 30 Tage)
        return [
            'rental.amortization_price_increase_percent_per_year' => '0.2',
            'rental.amortization_years_to_replacement' => '5',
            'rental.amortization_internal_days_per_year' => '30',
            'rental.amortization_external_days_per_year' => '0',
            'rental.amortization_markup_percent' => '0',
        ];
    }

    /**
     * Standard-Werte für Werkstatt-Einstellungen (Materialwart-Workflow 2026)
     */
    public static function getWorkshopDefaults(): array
    {
        return [
            'workshop.hourly_rate_chf' => '45.00',
            'workshop.order_reminder_days' => '7',
            'workshop.order_reminder_mode' => 'days',
            'workshop.spare_parts_category_id' => '',
        ];
    }

    /**
     * Standard-Werte für J+S-Leihmaterial (Camp/Event-Bestellformular)
     */
    public static function getJsMaterialDefaults(): array
    {
        return [
            'js.default_coach_person_nr' => '',
            'js.default_coach_first_name' => '',
            'js.default_coach_last_name' => '',
            'js.default_delivery_type' => 'franko',
        ];
    }

    /**
     * Alle Department-Setting-Defaults (GET/PATCH-Fallbacks)
     *
     * @return array<string, string>
     */
    public static function getAllDefaults(): array
    {
        return array_merge(
            self::getGeneralDefaults(),
            self::getActivityDefaults(),
            self::getRentalAmortizationDefaults(),
            self::getCalendarDefaults(),
            self::getWorkshopDefaults(),
            self::getJsMaterialDefaults(),
        );
    }
}
