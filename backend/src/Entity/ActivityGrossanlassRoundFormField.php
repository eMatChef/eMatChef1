<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_round_form_field')]
#[ORM\Index(name: 'idx_grossanlass_form_field_form', columns: ['form_id', 'sort_order'])]
class ActivityGrossanlassRoundFormField
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'form_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $formId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassRoundForm::class)]
    #[ORM\JoinColumn(name: 'form_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassRoundForm $form;

    #[ORM\Column(name: 'sort_order', type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 10)]
    private string $role;

    #[ORM\Column(name: 'system_key', type: 'string', length: 32, nullable: true)]
    private ?string $systemKey = null;

    #[ORM\Column(name: 'custom_type', type: 'string', length: 20, nullable: true)]
    private ?string $customType = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(name: 'help_text', type: 'text', nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $required = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'options_json', type: 'json', nullable: true)]
    private ?array $optionsJson = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'config_json', type: 'json', nullable: true)]
    private ?array $configJson = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getForm(): ActivityGrossanlassRoundForm
    {
        return $this->form;
    }

    public function setForm(ActivityGrossanlassRoundForm $form): self
    {
        $this->form = $form;
        $this->formId = $form->getId();

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getSystemKey(): ?string
    {
        return $this->systemKey;
    }

    public function setSystemKey(?string $systemKey): self
    {
        $this->systemKey = $systemKey;

        return $this;
    }

    public function getCustomType(): ?string
    {
        return $this->customType;
    }

    public function setCustomType(?string $customType): self
    {
        $this->customType = $customType;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getOptionsJson(): ?array
    {
        return $this->optionsJson;
    }

    /** @param array<string, mixed>|null $optionsJson */
    public function setOptionsJson(?array $optionsJson): self
    {
        $this->optionsJson = $optionsJson;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getConfigJson(): ?array
    {
        return $this->configJson;
    }

    /** @param array<string, mixed>|null $configJson */
    public function setConfigJson(?array $configJson): self
    {
        $this->configJson = $configJson;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
