<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'servant_id' => $this->servant_id,
            'destination_type' => $this->destination_type,
            'legislation_item_snapshot_id' => $this->legislation_item_snapshot_id,
            'destination_city' => $this->destination_city,
            'destination_state' => $this->destination_state,
            'departure_date' => $this->departure_date?->format('Y-m-d'),
            'return_date' => $this->return_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'quantity_days' => $this->quantity_days,
            'unit_value' => $this->unit_value,
            'total_value' => $this->total_value,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label() ?? '–',
            'status_color' => $this->status?->color() ?? 'gray',
            'requester_id' => $this->requester_id,
            'validator_id' => $this->validator_id,
            'authorizer_id' => $this->authorizer_id,
            'payer_id' => $this->payer_id,
            'validated_at' => $this->validated_at,
            'authorized_at' => $this->authorized_at,
            'paid_at' => $this->paid_at,
            'is_editable' => $this->isEditable(),
            'is_cancellable' => $this->isCancellable(),
            'can_generate_pdf' => $this->canGeneratePdf(),
            'servant' => new ServantResource($this->whenLoaded('servant')),
            'legislation_item_snapshot' => new LegislationItemResource($this->whenLoaded('legislationItemSnapshot')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'validator' => new UserResource($this->whenLoaded('validator')),
            'authorizer' => new UserResource($this->whenLoaded('authorizer')),
            'payer' => new UserResource($this->whenLoaded('payer')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
