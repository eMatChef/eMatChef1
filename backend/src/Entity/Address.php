<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Wiederverwendbare Adress-Entity
 * Kann für verschiedene Zwecke verwendet werden:
 * - Rechnungsadresse (Department)
 * - Lagerplätze
 * - Kunden
 * - Eventstandorte
 * - Lieferadressen
 *
 * scope bestimmt den Besitz-Kontext (genau einer):
 * - department: department_id gesetzt (Multi-Tenant)
 * - supplier: supplier_company_id gesetzt (registrierte B2B-Firma)
 * - global: beides NULL (systemweite Stammdaten)
 */
#[ORM\Entity]
#[ORM\Table(name: 'address')]
#[ORM\Index(columns: ['department_id'], name: 'idx_address_department')]
#[ORM\Index(columns: ['department_id', 'type'], name: 'idx_address_department_type')]
#[ORM\Index(columns: ['scope'], name: 'idx_address_scope')]
#[ORM\Index(columns: ['scope', 'type'], name: 'idx_address_scope_type')]
#[ORM\Index(columns: ['postal_code'], name: 'idx_address_postal_code')]
class Address
{
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_SUPPLIER = 'supplier';
    public const SCOPE_GLOBAL = 'global';

    public const TYPE_EVENT = 'event';
    public const TYPE_EVENT_DELIVERY = 'event_delivery';
    public const TYPE_EVENT_POI = 'event_poi';
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /**
     * Besitz-Kontext: department | supplier | global
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $scope = self::SCOPE_DEPARTMENT;

    /**
     * Department-Zuordnung (scope=department)
     */
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, nullable: true)]
    private ?string $departmentId = null;

    /**
     * Supplier-Firma (scope=supplier, FK in Paket 1)
     */
    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, nullable: true)]
    private ?string $supplierCompanyId = null;

    /**
     * Typ der Adresse - bestimmt den Verwendungszweck
     */
    #[ORM\Column(type: 'string', length: 50)]
    private string $type = 'general';

    /**
     * Bezeichnung/Name der Adresse (z.B. "Hauptlager", "Büro", "Privat")
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Firmen- oder Organisationsname
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $company = null;

    /**
     * Zusatz (z.B. "c/o", "Postfach", "Abteilung")
     */
    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(name: 'street_number', type: 'string', length: 20, nullable: true)]
    private ?string $streetNumber = null;

    #[ORM\Column(name: 'postal_code', type: 'string', length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $canton = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $country = 'Schweiz';

    /**
     * GPS Koordinaten für Karten-Integration
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    /**
     * Vorname der Kontaktperson
     */
    #[ORM\Column(name: 'contact_first_name', type: 'string', length: 100, nullable: true)]
    private ?string $contactFirstName = null;

    /**
     * Nachname der Kontaktperson
     */
    #[ORM\Column(name: 'contact_last_name', type: 'string', length: 100, nullable: true)]
    private ?string $contactLastName = null;

    /**
     * E-Mail-Adresse
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * Telefonnummer (Festnetz)
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    /**
     * Mobilnummer
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $mobile = null;

    /**
     * Zusätzliche Informationen (Anfahrt, Besonderheiten)
     */
    #[ORM\Column(name: 'additional_info', type: 'text', nullable: true)]
    private ?string $additionalInfo = null;

    /**
     * Primäre Adresse dieses Typs für das Department
     */
    #[ORM\Column(name: 'is_primary', type: 'boolean')]
    private bool $isPrimary = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    #[ORM\Column(name: 'deleted_by_user_id', type: 'string', length: 12, nullable: true)]
    private ?string $deletedByUserId = null;

    /**
     * Übergeordnete Adresse (z. B. Eventstandort für Zustellpunkt / Event-POI).
     */
    #[ORM\Column(name: 'parent_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $parentId = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Address $parent = null;

    /**
     * Markerfarbe (Hex, z. B. #16a34a) — vor allem für event_poi.
     */
    #[ORM\Column(name: 'pin_color', type: 'string', length: 7, nullable: true)]
    private ?string $pinColor = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // === ID ===
    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    // === Scope ===
    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    // === Department (scope=department) ===
    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function setDepartment(Department $department): self
    {
        $this->departmentId = $department->getId();
        $this->scope = self::SCOPE_DEPARTMENT;
        return $this;
    }

    // === Supplier company (scope=supplier) ===
    public function getSupplierCompanyId(): ?string
    {
        return $this->supplierCompanyId;
    }

    public function setSupplierCompanyId(?string $supplierCompanyId): self
    {
        $this->supplierCompanyId = $supplierCompanyId;
        return $this;
    }

    // === Type ===
    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    // === Name ===
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    // === Company ===
    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;
        return $this;
    }

    // === Address Line 2 ===
    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): self
    {
        $this->addressLine2 = $addressLine2;
        return $this;
    }

    // === Street ===
    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): self
    {
        $this->street = $street;
        return $this;
    }

    // === Street Number ===
    public function getStreetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function setStreetNumber(?string $streetNumber): self
    {
        $this->streetNumber = $streetNumber;
        return $this;
    }

    // === Postal Code ===
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    // === City ===
    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    // === Canton ===
    public function getCanton(): ?string
    {
        return $this->canton;
    }

    public function setCanton(?string $canton): self
    {
        $this->canton = $canton;
        return $this;
    }

    // === Country ===
    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    // === Coordinates ===
    public function getLatitude(): ?float
    {
        return $this->latitude !== null ? (float) $this->latitude : null;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude !== null ? (string) $latitude : null;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude !== null ? (float) $this->longitude : null;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude !== null ? (string) $longitude : null;
        return $this;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // === Contact Person ===
    public function getContactFirstName(): ?string
    {
        return $this->contactFirstName;
    }

    public function setContactFirstName(?string $contactFirstName): self
    {
        $this->contactFirstName = $contactFirstName;
        return $this;
    }

    public function getContactLastName(): ?string
    {
        return $this->contactLastName;
    }

    public function setContactLastName(?string $contactLastName): self
    {
        $this->contactLastName = $contactLastName;
        return $this;
    }

    public function getContactFullName(): string
    {
        return trim(($this->contactFirstName ?? '') . ' ' . ($this->contactLastName ?? ''));
    }

    // === Email ===
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    // === Phone ===
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    // === Mobile ===
    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;
        return $this;
    }

    // === Additional Info ===
    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    // === Primary Flag ===
    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    // === Timestamps ===
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getDeletedByUserId(): ?string
    {
        return $this->deletedByUserId;
    }

    public function setDeletedByUserId(?string $deletedByUserId): self
    {
        $this->deletedByUserId = $deletedByUserId;
        return $this;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        $this->parentId = $parent?->getId();
        return $this;
    }

    public function getPinColor(): ?string
    {
        return $this->pinColor;
    }

    public function setPinColor(?string $pinColor): self
    {
        $this->pinColor = $pinColor;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Gibt die vollständige Adresse als String zurück
     */
    public function getFullAddress(): string
    {
        $parts = [];
        
        if ($this->company) {
            $parts[] = $this->company;
        }
        
        if ($this->addressLine2) {
            $parts[] = $this->addressLine2;
        }
        
        if ($this->street) {
            $streetLine = $this->street;
            if ($this->streetNumber) {
                $streetLine .= ' ' . $this->streetNumber;
            }
            $parts[] = $streetLine;
        }
        
        $cityLine = trim(($this->postalCode ?? '') . ' ' . ($this->city ?? ''));
        if ($cityLine) {
            $parts[] = $cityLine;
        }
        
        if ($this->canton) {
            $parts[] = $this->canton;
        }
        
        if ($this->country && $this->country !== 'Schweiz') {
            $parts[] = $this->country;
        }
        
        // Fallback: Name verwenden wenn keine Adressfelder
        if (empty($parts) && $this->name) {
            $parts[] = $this->name;
        }
        
        return implode(', ', $parts);
    }

    /**
     * Gibt nur die Strasse mit Nummer zurück
     */
    public function getStreetLine(): string
    {
        if (!$this->street) return '';
        $line = $this->street;
        if ($this->streetNumber) {
            $line .= ' ' . $this->streetNumber;
        }
        return $line;
    }

    /**
     * Gibt PLZ und Ort zurück
     */
    public function getCityLine(): string
    {
        return trim(($this->postalCode ?? '') . ' ' . ($this->city ?? ''));
    }

    /**
     * Konvertiert zu Array für API-Response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'department_id' => $this->departmentId,
            'supplier_company_id' => $this->supplierCompanyId,
            'type' => $this->type,
            'type_label' => self::getAvailableTypes()[$this->type] ?? $this->type,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'company' => $this->company,
            'address_line2' => $this->addressLine2,
            'street' => $this->street,
            'street_number' => $this->streetNumber,
            'street_line' => $this->getStreetLine(),
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'city_line' => $this->getCityLine(),
            'canton' => $this->canton,
            'country' => $this->country,
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
            'has_coordinates' => $this->hasCoordinates(),
            'contact_first_name' => $this->contactFirstName,
            'contact_last_name' => $this->contactLastName,
            'contact_full_name' => $this->getContactFullName() ?: null,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'additional_info' => $this->additionalInfo,
            'pin_color' => $this->pinColor,
            'is_primary' => $this->isPrimary,
            'full_address' => $this->getFullAddress(),
            'deleted_at' => $this->deletedAt?->format('c'),
            'is_deleted' => $this->isDeleted(),
        ];
    }

    /**
     * Verfügbare Adress-Typen
     */
    public static function getAvailableTypes(): array
    {
        return [
            'general' => 'Allgemein',
            'billing' => 'Rechnungsadresse',
            'delivery' => 'Lieferadresse',
            'supplier' => 'Hersteller/Lieferant',
            'storage' => 'Lagerplatz',
            'customer' => 'Kundenadresse',
            'event' => 'Eventstandort',
            'event_delivery' => 'Zustellpunkt (Event)',
            'event_poi' => 'Event-Punkt',
            'meeting' => 'Treffpunkt',
            'office' => 'Büro',
            'private' => 'Privat',
            'postal' => 'Postadresse',
            'user' => 'Benutzeradresse',
        ];
    }

    /**
     * Schweizer Kantone
     */
    public static function getSwissCantons(): array
    {
        return [
            'AG' => 'Aargau',
            'AI' => 'Appenzell Innerrhoden',
            'AR' => 'Appenzell Ausserrhoden',
            'BE' => 'Bern',
            'BL' => 'Basel-Landschaft',
            'BS' => 'Basel-Stadt',
            'FR' => 'Freiburg',
            'GE' => 'Genf',
            'GL' => 'Glarus',
            'GR' => 'Graubünden',
            'JU' => 'Jura',
            'LU' => 'Luzern',
            'NE' => 'Neuenburg',
            'NW' => 'Nidwalden',
            'OW' => 'Obwalden',
            'SG' => 'St. Gallen',
            'SH' => 'Schaffhausen',
            'SO' => 'Solothurn',
            'SZ' => 'Schwyz',
            'TG' => 'Thurgau',
            'TI' => 'Tessin',
            'UR' => 'Uri',
            'VD' => 'Waadt',
            'VS' => 'Wallis',
            'ZG' => 'Zug',
            'ZH' => 'Zürich',
        ];
    }
}
