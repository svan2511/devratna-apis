<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full catalogue transcribed from the printed menu card (menu.jpg).
 * Prices stored as printed label + first numeric rupees value.
 *
 * IDs are STABLE and must match the mobile app (1–105 in list order):
 * the app hardcodes dish ids for bestsellers, sections and cart lines.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $order = 0;
            foreach ($this->categories() as [$name, $slug]) {
                Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'sort_order' => $order++],
                );
            }

            $ids = Category::query()->pluck('id', 'slug');

            // Truncate (not delete) so auto-increment restarts and ids
            // stay 1–105 on every re-seed. Never insert/delete rows here —
            // only append at the END of items() with the next free id.
            Schema::disableForeignKeyConstraints();
            DB::table('menu_items')->truncate();
            Schema::enableForeignKeyConstraints();

            $order = 0;
            $id = 0;
            foreach ($this->items() as [$slug, $name, $label, $value, $desc, $best, $img]) {
                $id++;
                [$half, $mid, $full] = self::splitPrices($label);
                DB::table('menu_items')->insert([
                    'id' => $id,
                    'category_id' => $ids[$slug],
                    'name' => $name,
                    'description' => $desc,
                    'price_label' => $label,
                    'price_value' => $value,
                    'half_price' => $half,
                    'mid_price' => $mid,
                    'full_price' => $full,
                    'is_veg' => true,
                    'is_bestseller' => $best,
                    'image_key' => $img,
                    'is_available' => true,
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return list<array{string, string}>
     */
    private function categories(): array
    {
        return [
            ['Breakfast', 'breakfast'],
            ['Snacks', 'snacks'],
            ['Thali', 'thali'],
            ['Main Course', 'maincourse'],
            ['Paneer Dishes', 'paneer'],
            ['Vegetables', 'veg'],
            ['Dal / Rajma / Chole', 'dal'],
            ['Chinese & Momos', 'chinese'],
            ['Raita & Dahi', 'raita'],
            ['Maggi', 'maggi'],
            ['Beverages', 'beverages'],
        ];
    }

    /**
     * Split a printed label ("₹210 / 320", "₹20 / 40 / 80", "₹150", "MRP")
     * into [half, mid, full]. 3 prices → all three; 2 → half + full;
     * single price → half only; MRP → [null, null, null].
     *
     * @return array{int|null, int|null, int|null}
     */
    private static function splitPrices(string $label): array
    {
        preg_match_all('/\d+/', $label, $m);
        $nums = array_map('intval', $m[0]);
        if (count($nums) >= 3) {
            return [$nums[0], $nums[1], $nums[count($nums) - 1]];
        }
        if (count($nums) === 2) {
            return [$nums[0], null, $nums[1]];
        }
        if (count($nums) === 1) {
            return [$nums[0], null, null];
        }

        return [null, null, null];
    }

    /**
     * [slug, name, price label, price value, description, bestseller, image key]
     *
     * @return list<array{string, string, string, int, string, bool, ?string}>
     */
    private function items(): array
    {
        return [
            ['breakfast', 'Aloo Paratha', '₹40', 40, 'Tawa-fresh, served with butter, curd & pickle.', false, 'aallo-paratha'],
            ['breakfast', 'Aloo Pyaj Paratha', '₹50', 50, 'Stuffed with spiced potato & onion.', false, 'aallo-pyaz'],
            ['breakfast', 'Gobhi Paratha', '₹60', 60, 'Stuffed cauliflower paratha with white butter.', false, 'gobhi-paratha'],
            ['breakfast', 'Mix Paratha', '₹70', 70, 'Mixed veg stuffing, tandoor-finished.', false, null],
            ['breakfast', 'Paneer Paratha', '₹80', 80, 'Stuffed with spiced paneer.', false, 'paneer-paratha'],
            ['breakfast', 'Plain Paratha', '₹20', 20, 'Simple layered whole-wheat paratha.', false, 'plain-paratha'],
            ['breakfast', 'Pyaz Paratha', '₹50', 50, 'Onion-stuffed crisp paratha.', false, null],
            ['breakfast', 'Plain Roti', '₹10', 10, 'Tandoor-fresh whole wheat roti.', false, 'plain-roti'],
            ['breakfast', 'Butter Roti', '₹15', 15, 'Tandoori roti brushed with butter.', false, 'butter-roti'],
            ['breakfast', 'Puri Bhaji (4 Piece)', '₹60', 60, 'Fluffy puris with spiced aloo bhaji.', false, 'puri-bhazi'],
            ['breakfast', 'Chole Bhature', '₹80', 80, 'Delhi-style chole with fluffy bhature.', true, 'chole-bhature'],
            ['breakfast', 'Veg Sandwich', '₹50', 50, 'Grilled sandwich with mint chutney.', false, null],
            ['breakfast', 'Cheese Sandwich', '₹70', 70, 'Grilled sandwich with molten cheese.', false, null],
            ['breakfast', 'Paneer Sandwich', '₹80', 80, 'Grilled sandwich with spiced paneer filling.', false, null],
            ['breakfast', 'Butter Toast', '₹50', 50, 'Crisp toast with butter.', false, null],
            ['breakfast', 'Plain Burger', '₹50', 50, 'Crispy veg patty burger.', false, null],
            ['breakfast', 'Cheese Burger', '₹60', 60, 'Veg patty burger with cheese slice.', false, null],
            ['breakfast', 'Paneer Burger', '₹70', 70, 'Crispy paneer patty burger.', false, null],

            ['snacks', 'Samosa', '₹20', 20, 'Crisp punjabi samosa with imli chutney.', false, null],
            ['snacks', 'Bread Pakoda', '₹20', 20, 'Stuffed bread fritters, chai-time favourite.', false, 'bread-pakoda'],
            ['snacks', 'Paneer Pakoda (10 Pcs)', '₹100', 100, 'Crisp batter-fried paneer bites.', false, null],
            ['snacks', 'Mix Pakoda (250 gm)', '₹80', 80, 'Assorted monsoon fritters.', false, null],
            ['snacks', 'Chole Samosa', '₹40', 40, 'Samosa topped with spicy chole.', false, null],
            ['snacks', 'Dahi Samosa', '₹50', 50, 'Samosa chaat with curd & chutneys.', false, null],

            ['thali', 'Veg Thali', '₹80', 80, 'Sabji + Dal + Roti + Salad + Rice.', false, 'special-veg-thali'],
            ['thali', 'Special Thali', '₹120', 120, 'Paneer + Dal + 4 Roti + Raita + Rice + Salad.', true, 'special-veg-thali'],

            ['maincourse', 'Rajma Chawal', '₹40 / 70', 40, 'Half / Full. Homestyle rajma over steamed rice.', false, 'rajma-chawal'],
            ['maincourse', 'Kadhi Chawal', '₹40 / 70', 40, 'Half / Full. Tangy kadhi with rice.', false, 'kadhi-chawal'],
            ['maincourse', 'Chole Chawal', '₹40 / 70', 40, 'Half / Full. Amritsari chole with rice.', false, 'chole-chawal'],
            ['maincourse', 'Dal Chawal', '₹40 / 70', 40, 'Half / Full. Comforting dal with rice.', false, 'kadhi'],

            ['paneer', 'Mutter Paneer', '₹150 / 260', 150, 'Half / Full. Peas & paneer in homestyle gravy.', false, 'paneer'],
            ['paneer', 'Kadhai Paneer', '₹180 / 290', 180, 'Half / Full. Wok-tossed with capsicum & kadhai masala.', true, 'kadhai-paneer'],
            ['paneer', 'Paneer Butter Masala', '₹180 / 300', 180, 'Half / Full. Rich makhani gravy, best with naan.', false, 'paneer-butter-masala'],
            ['paneer', 'Paneer Bhurji', '₹180 / 300', 180, 'Half / Full. Scrambled paneer with onion-tomato masala.', false, 'paneer-bhurji'],
            ['paneer', 'Paneer Do Pyaza', '₹210 / 320', 210, 'Half / Full. Paneer tossed with double onions & masala.', true, 'paneer-do-pyaza'],

            ['veg', 'Mix Veg', '₹90 / 160', 90, 'Half / Full. Seasonal garden vegetables.', false, 'mix-veg'],
            ['veg', 'Aloo Matar', '₹80 / 150', 80, 'Half / Full. Potato & peas homestyle curry.', false, null],
            ['veg', 'Aloo Shimla', '₹80 / 150', 80, 'Half / Full. Potato with crunchy capsicum.', false, null],
            ['veg', 'Aloo Gobhi', '₹80 / 150', 80, 'Half / Full. Classic potato-cauliflower sabji.', false, null],
            ['veg', 'Gobhi Masala', '₹80 / 150', 80, 'Half / Full. Cauliflower in spiced masala.', false, null],
            ['veg', 'Aloo Jeera', '₹60 / 100', 60, 'Half / Full. Tempered with roasted cumin.', false, null],
            ['veg', 'Sev Bhaji', '₹150', 150, 'Spicy Kolhapuri-style sev curry.', false, null],

            ['dal', 'Dal Fry', '₹70 / 130', 70, 'Half / Full. Ghee-garlic tempered arhar dal.', false, null],
            ['dal', 'Dal Tadka', '₹70 / 130', 70, 'Half / Full. Smoky tadka dal.', false, null],
            ['dal', 'Dal Makhani', '₹100 / 180', 100, 'Half / Full. Slow-cooked black urad, butter & cream.', true, 'dal-makhni'],
            ['dal', 'Rajma', '₹80 / 140', 80, 'Half / Full. Jammu-style red kidney beans.', false, null],
            ['dal', 'Chole', '₹80 / 140', 80, 'Half / Full. Amritsari chickpea curry.', false, null],
            ['dal', 'Chana Masala', '₹80 / 140', 80, 'Half / Full. Dry-spiced kala chana.', false, null],
            ['dal', 'Kadhi Pakoda', '₹60 / 100', 60, 'Half / Full. Besan kadhi with soft pakodas.', false, null],

            ['chinese', 'Veg Noodles', '₹60', 60, 'Street-style wok-tossed noodles.', false, 'veg-noodles'],
            ['chinese', 'Hakka Noodles', '₹80', 80, 'Smoky hakka-style noodles.', false, 'veg-noodles'],
            ['chinese', 'Schezwan Noodles', '₹90', 90, 'Fiery schezwan sauce noodles.', false, 'veg-noodles'],
            ['chinese', 'Paneer Noodles', '₹120', 120, 'Noodles tossed with paneer strips.', false, 'veg-noodles'],
            ['chinese', 'Garlic Noodles', '₹80', 80, 'Burnt-garlic noodles.', false, 'veg-noodles'],
            ['chinese', 'Veg Momos (8 Pcs)', '₹70', 70, 'Steamed, with spicy red chutney.', true, 'veg-momos'],
            ['chinese', 'Fried Momos (8 Pcs)', '₹100', 100, 'Golden crisp-fried momos.', false, 'veg-momos'],
            ['chinese', 'Kurkure Momos (8 Pcs)', '₹120', 120, 'Crunchy kurkure-coated momos.', false, 'veg-momos'],
            ['chinese', 'Tandoori Momos (8 Pcs)', '₹140', 140, 'Charred in clay tandoor, smoky & juicy.', false, 'veg-momos'],
            ['chinese', 'Manchurian (Dry)', '₹140', 140, 'Crisp veg dumplings, dry tossed.', false, null],
            ['chinese', 'Manchurian (Gravy)', '₹120', 120, 'Veg dumplings in garlic-soy gravy.', false, null],
            ['chinese', 'Chilli Potato', '₹120', 120, 'Crisp fingers in chilli-garlic glaze.', false, null],
            ['chinese', 'Honey Chilli Potato', '₹180', 180, 'Sweet-heat honey chilli glaze.', false, null],
            ['chinese', 'French Fries', '₹140', 140, 'Golden salted fries.', false, 'french-fries'],
            ['chinese', 'Peri Peri Fries', '₹120', 120, 'Dusted with peri peri masala.', false, null],
            ['chinese', 'White Sauce Pasta', '₹180', 180, 'Creamy alfredo-style pasta.', false, null],
            ['chinese', 'Red Sauce Pasta', '₹160', 160, 'Tangy tomato-basil pasta.', false, null],
            ['chinese', 'Mix Sauce Pasta', '₹150', 150, 'Pink sauce, best of both.', false, null],
            ['chinese', 'Chilli Paneer (Gravy)', '₹160', 160, 'Paneer cubes in spicy gravy.', false, null],
            ['chinese', 'Chilli Paneer (Dry)', '₹180', 180, 'Dry-tossed starter style.', false, null],
            ['chinese', 'Veg Fried Rice', '₹120', 120, 'Smoky wok rice with crunchy veg.', false, 'fried-rice'],
            ['chinese', 'Paneer Fried Rice', '₹140', 140, 'Fried rice with paneer cubes.', true, 'fried-rice'],
            ['chinese', 'Veg Schezwan Rice', '₹120', 120, 'Spicy schezwan fried rice.', false, 'fried-rice'],
            ['chinese', 'Jeera Rice', '₹120', 120, 'Basmati tempered with ghee-roasted cumin.', false, null],
            ['chinese', 'Steam Rice', '₹100', 100, 'Plain steamed basmati.', false, null],

            ['raita', 'Vegetable Raita', '₹70 / 120', 70, 'Half / Full. Cucumber-onion raita.', false, 'vegetable-raita'],
            ['raita', 'Boondi Raita', '₹60 / 100', 60, 'Half / Full. Crisp boondi in curd.', false, 'boondi-raita'],
            ['raita', 'Plain Dahi (Curd)', '₹20 / 40 / 80', 20, 'Fresh set curd, three serving sizes.', false, 'dahi'],

            ['maggi', 'Plain Maggi', '₹50', 50, 'Classic masala maggi.', false, 'maggie'],
            ['maggi', 'Veg Maggi', '₹60', 60, 'Loaded with garden vegetables.', false, 'maggie'],
            ['maggi', 'Paneer Maggi', '₹80', 80, 'With soft paneer cubes.', false, 'maggie'],
            ['maggi', 'Cheese Maggi', '₹70', 70, 'Topped with molten cheese.', false, 'maggie'],

            ['beverages', 'Tea', '₹20', 20, 'Kadak doodh chai.', false, null],
            ['beverages', 'Masala Tea', '₹30', 30, 'Brewed with crushed spices.', false, null],
            ['beverages', 'Lemon Tea', '₹40', 40, 'Light & refreshing.', false, null],
            ['beverages', 'Black Tea', '₹40', 40, 'No-milk brew.', false, null],
            ['beverages', 'Green Tea', '₹40', 40, 'Light detox brew.', false, null],
            ['beverages', 'Ice Tea', '₹70', 70, 'Chilled lemon ice tea.', false, null],
            ['beverages', 'Hot Coffee', '₹60', 60, 'Steaming filter-style coffee.', false, null],
            ['beverages', 'Cold Coffee', '₹130', 130, 'Thick blended frappe.', false, 'cold-coffee'],
            ['beverages', 'Black Coffee', '₹40', 40, 'Bold & bitter brew.', false, null],
            ['beverages', 'Banana Shake', '₹80', 80, 'Thick milk shake.', false, null],
            ['beverages', 'Mango Shake', '₹80', 80, 'Seasonal alphonso-style shake.', false, null],
            ['beverages', 'KitKat Shake', '₹100', 100, 'Chocolate wafer shake.', false, 'kitkat-shake'],
            ['beverages', 'Oreo Shake', '₹100', 100, 'Cookies & cream shake.', false, null],
            ['beverages', 'Vanilla Shake', '₹100', 100, 'Classic vanilla bean shake.', false, null],
            ['beverages', 'Badam Shake', '₹120', 120, 'Kesar-badam milk shake.', false, null],
            ['beverages', 'Fresh Lemon Soda', '₹70', 70, 'Sweet / salted / mixed.', false, null],
            ['beverages', 'Mint Mojito', '₹100', 100, 'Virgin mint-lime cooler.', false, null],
            ['beverages', 'Blue Lagoon', '₹100', 100, 'Electric-blue citrus cooler.', false, null],
            ['beverages', 'Shikanji', '₹50', 50, 'Old Delhi-style nimbu masala.', false, null],
            ['beverages', 'Lemon Water', '₹50', 50, 'Simple nimbu pani.', false, null],
            ['beverages', 'Masala Chaas', '₹40', 40, 'Spiced buttermilk.', false, null],
            ['beverages', 'Sweet Lassi', '₹70', 70, 'Thick curd lassi with malai.', false, 'sweet-lassi'],
            ['beverages', 'Mineral Water', 'MRP', 0, 'Sealed bottle.', false, null],
            ['beverages', 'Cold Drink', 'MRP', 0, 'Chilled soft drink.', false, null],
        ];
    }
}
