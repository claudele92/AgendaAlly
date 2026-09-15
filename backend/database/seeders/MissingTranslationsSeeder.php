<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * A handful of keys the original template's translations.php/translations_en.sql
 * dump never included, found by auditing real screens rather than the dump
 * itself: three admin-panel Shop-edit step titles (confirmed via a live
 * click-through of Settings > Shop edit - they rendered as their raw keys)
 * and one storefront hero subheading (confirmed the same way on /home-2).
 * Both apps read from this one shared `translations` table (see the
 * existing 'shop'/'shop.edit'/'delivery' rows, group=web, which already
 * back admin-panel strings), so all four keys use the same group.
 */
class MissingTranslationsSeeder extends Seeder
{
    private const TRANSLATIONS = [
        // admin: seller-views/my-shop/edit.jsx step titles
        'product.locations' => 'Product locations',
        'service.locations' => 'Service locations',
        'shop.social'       => 'Social links',
        // web: (with-footer)/(home-2)/home-2/page.tsx hero subheading
        'home-2.hero.description' => 'Find trusted salons and book your next appointment in minutes.',
    ];

    public function run(): void
    {
        foreach (self::TRANSLATIONS as $key => $value) {
            Translation::firstOrCreate(
                ['locale' => 'en', 'group' => 'web', 'key' => $key],
                ['value' => $value]
            );
        }
    }
}
