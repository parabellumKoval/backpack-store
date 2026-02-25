<?php

namespace Backpack\Store\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

use Backpack\Store\app\Http\Requests\Traits\BuildsSuppliersDataRules;
use App\Http\Requests\Traits\PriceOverridesRequest;


class ProductRequest extends FormRequest
{
    use BuildsSuppliersDataRules;

    private ?bool $shouldValidateStockFieldsCache = null;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }


    protected function prepareForValidation(): void
    {
        if ($this->has('manual_sort')) {
            $this->merge([
                'manual_sort' => $this->normalizeManualSortInput($this->input('manual_sort')),
            ]);
        }

        if ($this->shouldValidateStockFields() && $this->shouldProcessSuppliersData()) {
            $this->merge([
                'suppliersData' => $this->normalizeSuppliersData($this->input('suppliersData', [])),
            ]);
        }
    }
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
          'name' => 'required|min:1|max:255',
          'manual_sort' => 'nullable|numeric',
        ];


        if ($this->shouldValidateStockFields()) {
            $rules = array_merge(
                $rules,
                $this->buildSuppliersDataRules($this->input('suppliersData', [])),
                PriceOverridesRequest::rulesArray()
            );
        }

        return $rules;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return array_merge(
            [],
            $this->suppliersDataAttributes(),
            PriceOverridesRequest::attributesArray()
        );
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return array_merge(
            [],
            $this->suppliersDataMessages(),
            PriceOverridesRequest::messagesArray()
        );
    }

    protected function shouldValidateStockFields(): bool
    {
        if ($this->shouldValidateStockFieldsCache !== null) {
            return $this->shouldValidateStockFieldsCache;
        }

        return $this->shouldValidateStockFieldsCache = $this->resolveShouldValidateStockFields();
    }

    protected function resolveShouldValidateStockFields(): bool
    {
        $parentId = $this->input('parent_id');
        if ($parentId !== null && (int) $parentId > 0) {
            return true;
        }

        $productId = $this->resolveCurrentProductId();
        if (!$productId) {
            // Creating a new base product – show & validate fields.
            return true;
        }

        $modelClass = \Settings::get('dress.product.model_admin', \Backpack\Store\app\Models\Admin\Product::class);
        if (!class_exists($modelClass)) {
            $modelClass = \Backpack\Store\app\Models\Admin\Product::class;
        }

        if (!class_exists($modelClass)) {
            return true;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $product */
        $product = $modelClass::query()
            ->select('id', 'parent_id')
            ->withCount('children')
            ->find($productId);

        if (!$product) {
            return true;
        }

        $isBaseProduct = (int) ($product->parent_id ?? 0) === 0;
        $hasModifications = (int) ($product->children_count ?? 0) > 0;

        return !($isBaseProduct && $hasModifications);
    }

    protected function resolveCurrentProductId(): ?int
    {
        $candidates = [
            $this->route('id'),
            $this->route('product'),
            $this->input('id'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \Illuminate\Database\Eloquent\Model) {
                $key = (int) $candidate->getKey();
                if ($key > 0) {
                    return $key;
                }
                continue;
            }

            if (is_numeric($candidate)) {
                $id = (int) $candidate;
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }

    protected function normalizeManualSortInput($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim(str_replace(',', '.', $value));
            return $value === '' ? null : $value;
        }

        return $value;
    }
}
