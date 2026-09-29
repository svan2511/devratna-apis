<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New printed menu card (new-menu.png): dishes + prices sync.
 *
 * IDs are STABLE (mobile app hardcodes them) — only values change,
 * removed dishes are hidden via is_available, new dishes appended.
 * Admin ke baaki availability toggles untouched rehte hain.
 */
return new class extends Migration
{
    /**
     * [id, name, price_label, price_value, half, mid, full, is_available]
     * null = no change for that column.
     *
     * @return list<array{int, ?string, ?string, ?int, ?int, ?int, ?int, ?bool}>
     */
    private function newCard(): array
    {
        return [
            [7, null, null, null, null, null, null, false],
            [10, 'Puri Bhaji (5 Pieces)', '₹70', 70, 70, null, null, null],
            [21, 'Paneer Pakoda (8 Pcs)', '₹150', 150, 150, null, null, null],
            [22, null, '₹100', 100, 100, null, null, null],
            [32, null, '₹200 / 320', 200, 200, null, 320, null],
            [33, null, '₹190 / 300', 190, 190, null, 300, null],
            [34, 'Paneer Burji', '₹180 / 340', 180, 180, null, 340, null],
            [35, null, '₹220 / 340', 220, 220, null, 340, null],
            [37, null, '₹70 / 120', 70, 70, null, 120, null],
            [41, null, '₹70 / 120', 70, 70, null, 120, null],
            [42, 'Shev Bhaji', null, null, null, null, null, null],
            [50, null, '₹40 / 70', 40, 40, null, 70, null],
            [51, null, '₹100', 100, 100, null, null, null],
            [52, null, '₹110', 110, 110, null, null, null],
            [53, null, '₹140', 140, 140, null, null, null],
            [57, null, '₹140', 140, 140, null, null, null],
            [58, null, '₹120', 120, 120, null, null, true],
            [59, null, '₹170', 170, 170, null, null, null],
            [60, null, '₹140', 140, 140, null, null, null],
            [61, null, '₹140', 140, 140, null, null, null],
            [63, 'French Fry', '₹120', 120, 120, null, null, null],
            [64, 'Peri Peri', '₹140', 140, 140, null, null, null],
            [65, 'White Sos Pasta', '₹200', 200, 200, null, null, null],
            [66, 'Red Sos Pasta', '₹180', 180, 180, null, null, null],
            [67, 'Mix Sos Pasta', '₹160', 160, 160, null, null, null],
            [68, null, '₹220', 220, 220, null, null, null],
            [70, null, '₹130', 130, 130, null, null, null],
            [71, null, '₹150', 150, 150, null, null, null],
            [72, null, '₹140', 140, 140, null, null, null],
            [73, null, '₹70 / 120', 70, 70, null, 120, null],
            [74, null, '₹50 / 90', 50, 50, null, 90, null],
            [76, 'Bundi Raita', null, null, null, null, null, null],
            [78, 'Plain Maggie', null, null, null, null, null, null],
            [79, 'Veg Maggie', '₹70', 70, 70, null, null, null],
            [80, 'Paneer Maggie', '₹90', 90, 90, null, null, null],
            [81, 'Cheese Maggie', '₹110', 110, 110, null, null, null],
            [85, null, '₹30', 30, 30, null, null, null],
            [91, null, '₹90', 90, 90, null, null, null],
            [92, null, '₹90', 90, 90, null, null, null],
            [93, null, '₹120', 120, 120, null, null, null],
            [94, 'Orio Shake', '₹120', 120, 120, null, null, null],
            [95, 'Vanila Shake', '₹120', 120, 120, null, null, null],
            [98, null, '₹110', 110, 110, null, null, null],
            [100, null, '₹60', 60, 60, null, null, null],
            [102, 'Masala Chach', null, null, null, null, null, null],
            [103, null, '₹80', 80, 80, null, null, null],
            [104, null, null, null, null, null, null, false],
        ];
    }

    private function oldCard(): array
    {
        return [
            [7, null, null, null, null, null, null, true],
            [10, 'Puri Bhaji (4 Piece)', '₹60', 60, 60, null, null, null],
            [21, 'Paneer Pakoda (10 Pcs)', '₹100', 100, 100, null, null, null],
            [22, null, '₹80', 80, 80, null, null, null],
            [32, null, '₹180 / 290', 180, 180, null, 290, null],
            [33, null, '₹180 / 300', 180, 180, null, 300, null],
            [34, 'Paneer Bhurji', '₹180 / 300', 180, 180, null, 300, null],
            [35, null, '₹210 / 320', 210, 210, null, 320, null],
            [37, null, '₹80 / 150', 80, 80, null, 150, null],
            [41, null, '₹60 / 100', 60, 60, null, 100, null],
            [42, 'Sev Bhaji', null, null, null, null, null, null],
            [50, null, '₹60', 60, 60, null, null, null],
            [51, null, '₹80', 80, 80, null, null, null],
            [52, null, '₹90', 90, 90, null, null, null],
            [53, null, '₹120', 120, 120, null, null, null],
            [57, null, '₹120', 120, 120, null, null, null],
            [58, null, '₹140', 140, 140, null, null, false],
            [59, null, '₹140', 140, 140, null, null, null],
            [60, null, '₹120', 120, 120, null, null, null],
            [61, null, '₹120', 120, 120, null, null, null],
            [63, 'French Fries', '₹140', 140, 140, null, null, null],
            [64, 'Peri Peri Fries', '₹120', 120, 120, null, null, null],
            [65, 'White Sauce Pasta', '₹180', 180, 180, null, null, null],
            [66, 'Red Sauce Pasta', '₹160', 160, 160, null, null, null],
            [67, 'Mix Sauce Pasta', '₹150', 150, 150, null, null, null],
            [68, null, '₹160', 160, 160, null, null, null],
            [70, null, '₹120', 120, 120, null, null, null],
            [71, null, '₹140', 140, 140, null, null, null],
            [72, null, '₹120', 120, 120, null, null, null],
            [73, null, '₹120', 120, 120, null, null, null],
            [74, null, '₹100', 100, 100, null, null, null],
            [76, 'Boondi Raita', null, null, null, null, null, null],
            [78, 'Plain Maggi', null, null, null, null, null, null],
            [79, 'Veg Maggi', '₹60', 60, 60, null, null, null],
            [80, 'Paneer Maggi', '₹80', 80, 80, null, null, null],
            [81, 'Cheese Maggi', '₹70', 70, 70, null, null, null],
            [85, null, '₹40', 40, 40, null, null, null],
            [91, null, '₹80', 80, 80, null, null, null],
            [92, null, '₹80', 80, 80, null, null, null],
            [93, null, '₹100', 100, 100, null, null, null],
            [94, 'Oreo Shake', '₹100', 100, 100, null, null, null],
            [95, 'Vanilla Shake', '₹100', 100, 100, null, null, null],
            [98, null, '₹100', 100, 100, null, null, null],
            [100, null, '₹50', 50, 50, null, null, null],
            [102, 'Masala Chaas', null, null, null, null, null, null],
            [103, null, '₹70', 70, 70, null, null, null],
            [104, null, null, null, null, null, null, true],
        ];
    }

    /**
     * @param list<array{int, ?string, ?string, ?int, ?int, ?int, ?int, ?bool}> $rows
     */
    private function apply(array $rows): void
    {
        $cols = ['name', 'price_label', 'price_value', 'half_price', 'mid_price', 'full_price', 'is_available'];
        foreach ($rows as [$id, $name, $label, $value, $half, $mid, $full, $avail]) {
            $update = [];
            foreach ([$name, $label, $value, $half, $mid, $full, $avail] as $i => $v) {
                if ($v !== null) {
                    $update[$cols[$i]] = $v;
                }
            }
            if ($update !== []) {
                $update['updated_at'] = now();
                DB::table('menu_items')->where('id', $id)->update($update);
            }
        }
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->apply($this->newCard());

            // New dish — appended with the next free id (stable forever).
            // Guard: test DBs me full menu nahi hota, waha skip karo.
            if (DB::table('menu_items')->where('id', 106)->doesntExist()) {
                $snacksId = DB::table('categories')->where('slug', 'snacks')->value('id');
                if ($snacksId !== null) {
                    $sort = (int) DB::table('menu_items')->where('category_id', $snacksId)->max('sort_order') + 1;
                    DB::table('menu_items')->insert([
                        'id' => 106,
                        'category_id' => $snacksId,
                        'name' => 'Bread Cutlet (2 Pcs)',
                        'description' => 'Crisp bread cutlets with chutney.',
                        'price_label' => '₹50',
                        'price_value' => 50,
                        'half_price' => 50,
                        'mid_price' => null,
                        'full_price' => null,
                        'is_veg' => true,
                        'is_bestseller' => false,
                        'image_key' => null,
                        'is_available' => true,
                        'sort_order' => $sort,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->apply($this->oldCard());
            DB::table('menu_items')->where('id', 106)->delete();
        });
    }
};
