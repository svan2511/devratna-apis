<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_label' => $this->price_label,
            'price_value' => $this->price_value,
            'half_price' => $this->half_price,
            'mid_price' => $this->mid_price,
            'full_price' => $this->full_price,
            'veg' => (bool) $this->is_veg,
            'bestseller' => (bool) $this->is_bestseller,
            'image_key' => $this->image_key,
        ];
    }
}
