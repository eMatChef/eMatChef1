<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_wish_response_value')]
#[ORM\UniqueConstraint(name: 'uq_wish_response_value_field', columns: ['response_id', 'field_id'])]
class ActivityGrossanlassWishResponseValue
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'response_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $responseId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassWishResponse::class)]
    #[ORM\JoinColumn(name: 'response_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassWishResponse $response;

    #[ORM\Column(name: 'field_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $fieldId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassRoundFormField::class)]
    #[ORM\JoinColumn(name: 'field_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ActivityGrossanlassRoundFormField $field;

    #[ORM\Column(name: 'value_text', type: 'text', nullable: true)]
    private ?string $valueText = null;

    #[ORM\Column(name: 'value_number', type: 'decimal', precision: 18, scale: 4, nullable: true)]
    private ?string $valueNumber = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'value_json', type: 'json', nullable: true)]
    private ?array $valueJson = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getResponseId(): string
    {
        return $this->responseId;
    }

    public function getResponse(): ActivityGrossanlassWishResponse
    {
        return $this->response;
    }

    public function setResponse(ActivityGrossanlassWishResponse $response): self
    {
        $this->response = $response;
        $this->responseId = $response->getId();

        return $this;
    }

    public function getFieldId(): string
    {
        return $this->fieldId;
    }

    public function getField(): ActivityGrossanlassRoundFormField
    {
        return $this->field;
    }

    public function setField(ActivityGrossanlassRoundFormField $field): self
    {
        $this->field = $field;
        $this->fieldId = $field->getId();

        return $this;
    }

    public function getValueText(): ?string
    {
        return $this->valueText;
    }

    public function setValueText(?string $valueText): self
    {
        $this->valueText = $valueText;

        return $this;
    }

    public function getValueNumber(): ?string
    {
        return $this->valueNumber;
    }

    public function setValueNumber(?string $valueNumber): self
    {
        $this->valueNumber = $valueNumber;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getValueJson(): ?array
    {
        return $this->valueJson;
    }

    /** @param array<string, mixed>|null $valueJson */
    public function setValueJson(?array $valueJson): self
    {
        $this->valueJson = $valueJson;

        return $this;
    }
}
