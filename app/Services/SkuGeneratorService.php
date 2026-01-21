<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Settings;
use Illuminate\Support\Str;

class SkuGeneratorService
{
    /**
     * Gera um SKU baseado nas configurações do sistema.
     *
     * @param array<string, mixed> $attributes
     */
    public function generate(Product $product, array $attributes = []): ?string
    {
        $autoGeneration = Settings::get('sku_auto_generation', false);

        if (! $autoGeneration) {
            return null;
        }

        $pattern = (string) Settings::get('sku_pattern', '{CATEGORY}-{NAME}-{SEQ}');

        $categoryCode = $this->buildCategoryCode($product);
        $nameCode = $this->buildNameCode($product);
        $variantsCode = $this->buildVariantsCode($attributes);

        // Monta o prefixo sem o sequencial
        $base = $pattern;
        $base = str_replace('{CATEGORY}', $categoryCode, $base);
        $base = str_replace('{NAME}', $nameCode, $base);
        $base = str_replace('{VARIANTS}', $variantsCode, $base);

        $baseWithoutSeq = str_replace('{SEQ}', '', $base);

        $nextSeq = $this->nextSequenceForPrefix($baseWithoutSeq);
        $seqPadded = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return str_replace('{SEQ}', $seqPadded, $base);
    }

    private function buildCategoryCode(Product $product): string
    {
        $name = $product->category?->name;

        if (! is_string($name) || $name === '') {
            return 'PRO';
        }

        $clean = Str::upper(Str::slug($name, ''));

        if ($clean === '') {
            return 'PRO';
        }

        return Str::substr($clean, 0, 3);
    }

    private function buildNameCode(Product $product): string
    {
        $name = (string) $product->name;

        if ($name === '') {
            return 'PRD';
        }

        $upper = Str::upper($name);
        $words = preg_split('/\s+/', $upper) ?: [];

        if (count($words) >= 2) {
            $initials = array_slice($words, 0, 3);
            $code = implode('', array_map(
                static fn (string $word): string => Str::substr(preg_replace('/[^A-Z0-9]/', '', $word) ?? '', 0, 1),
                $initials,
            ));

            if ($code !== '') {
                return $code;
            }
        }

        $clean = Str::upper(Str::slug($name, ''));

        if ($clean === '') {
            return 'PRD';
        }

        return Str::substr($clean, 0, 3);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function buildVariantsCode(array $attributes): string
    {
        if ($attributes === []) {
            return 'UN';
        }

        $colorKeys = ['cor', 'color', 'colour'];
        $sizeKeys = ['tamanho', 'size', 'tam'];

        $colorCode = null;
        $sizeCode = null;

        foreach ($attributes as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $normalizedKey = Str::lower($key);
            $valueStr = Str::upper((string) $value);

            if ($colorCode === null && in_array($normalizedKey, $colorKeys, true)) {
                $colorCode = Str::substr(preg_replace('/[^A-Z0-9]/', '', $valueStr) ?? '', 0, 2);
            }

            if ($sizeCode === null && in_array($normalizedKey, $sizeKeys, true)) {
                $sizeCode = Str::substr(preg_replace('/[^A-Z0-9]/', '', $valueStr) ?? '', 0, 2);
            }
        }

        $parts = [];

        if ($colorCode !== null && $colorCode !== '') {
            $parts[] = $colorCode;
        }

        if ($sizeCode !== null && $sizeCode !== '') {
            $parts[] = $sizeCode;
        }

        if ($parts === []) {
            return 'UN';
        }

        return implode('-', $parts);
    }

    private function nextSequenceForPrefix(string $baseWithoutSeq): int
    {
        if ($baseWithoutSeq === '') {
            $lastVariant = ProductVariant::query()
                ->whereNotNull('sku')
                ->orderByDesc('id')
                ->first();
        } else {
            $lastVariant = ProductVariant::query()
                ->where('sku', 'like', $baseWithoutSeq . '%')
                ->orderByDesc('sku')
                ->first();
        }

        if (! $lastVariant || ! is_string($lastVariant->sku)) {
            return 1;
        }

        if (preg_match('/(\d+)$/', $lastVariant->sku, $matches) !== 1) {
            return 1;
        }

        return (int) $matches[1] + 1;
    }
}

