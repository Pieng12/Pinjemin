<?php

namespace App\Http\Requests;

use App\ItemCondition;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('item')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => ['required', Rule::in(ItemCondition::values())],
            'daily_price' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'allow_deposit_payment' => ['sometimes', 'boolean'],
            'allow_cash_balance' => ['sometimes', 'boolean'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'city' => ['required', 'string', 'max:255'],
            'address' => [Rule::requiredIf($this->boolean('is_active')), 'nullable', 'string', 'max:1000'],
            'delivery_enabled' => ['sometimes', 'boolean'],
            'free_delivery' => ['sometimes', 'boolean'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Item $item */
                $item = $this->route('item');
                $selectedCategoryId = $this->integer('category_id');

                if ($this->boolean('allow_cash_balance') && ! $this->boolean('allow_deposit_payment')) {
                    $validator->errors()->add('allow_cash_balance', 'Pelunasan tunai memerlukan opsi pembayaran DP.');
                }
                if ($this->boolean('allow_deposit_payment') && (float) $this->input('deposit_amount', 0) <= 0) {
                    $validator->errors()->add('deposit_amount', 'Isi nominal DP lebih dari Rp 0 untuk mengizinkan pembayaran DP.');
                }

                if (
                    $selectedCategoryId !== $item->category_id
                    && ! Category::query()->whereKey($selectedCategoryId)->active()->exists()
                ) {
                    $validator->errors()->add('category_id', 'Kategori yang dipilih tidak aktif.');
                }

                $existingPhotoCount = $item->photos()->count();
                $newPhotoCount = count($this->file('photos', []));

                if (($existingPhotoCount + $newPhotoCount) > 5) {
                    $validator->errors()->add('photos', 'Total foto barang maksimal 5.');
                }

                if ($this->boolean('is_active') && ($existingPhotoCount + $newPhotoCount) === 0) {
                    $validator->errors()->add('photos', 'Barang aktif harus memiliki minimal satu foto.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'daily_price' => 'harga sewa per hari',
            'deposit_amount' => 'nominal DP',
            'photos' => 'foto barang',
            'photos.*' => 'foto barang',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.max' => 'Maksimal 5 foto barang.',
        ];
    }
}
