<?php

namespace Shoptimised\AiVisibility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create_batches');
    }

    public function rules(): array
    {
        $limits = config('ai_visibility.limits');
        $platforms = array_keys(config('ai_visibility.providers'));

        return [
            'feed_id' => ['required', 'integer', 'exists:feeds,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in($platforms)],
            'item_group_ids' => ['required', 'array', 'min:1', "max:{$limits['max_item_groups_per_batch']}"],
            'item_group_ids.*' => ['string'],
            'runs_per_prompt' => ['nullable', 'integer', 'min:1', "max:{$limits['max_runs_per_prompt']}"],
            'prompts_per_item_group' => ['nullable', 'integer', 'min:1', 'max:25'],
            'country' => ['nullable', 'string', 'size:2'],
            'language' => ['nullable', 'string', 'max:8'],
            'selected_filters' => ['nullable', 'array'],
        ];
    }
}
