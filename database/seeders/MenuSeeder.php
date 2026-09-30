<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full catalogue transcribed from the printed menu card (new-menu.png).
 * Prices stored as printed label + first numeric rupees value.
 *
 * IDs are STABLE and must match the mobile app:
 * removed dishes stay as rows (hidden via unavailable()),
 * new dishes are appended ONLY at the END of items().
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
                    'is_available' => ! in_array($name, self::unavailable(), true),
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
     * Dishes no longer served — kept with stable ids, hidden via is_available
     * (menu API filters them, order API rejects them).
     *
     * @return list<string>
     */
    private static function unavailable(): array
    {
        return [
            'Veg Sandwich',
            'Cheese Sandwich',
            'Paneer Sandwich',
            'Butter Toast',
            'Plain Burger',
            'Cheese Burger',
            'Paneer Burger',
            'Samosa',
            'Chole Samosa',
            'Dahi Samosa',
            'Pyaz Paratha',
            'Mineral Water',
            'Badam Shake',
            'Cold Drink',
        ];
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
            ['breakfast', 'Mix Paratha', '₹70', 70, 'Mixed veg stuffing, tandoor-finished.', false, 'mix-paratha'],
            ['breakfast', 'Paneer Paratha', '₹80', 80, 'Stuffed with spiced paneer.', false, 'paneer-paratha'],
            ['breakfast', 'Plain Paratha', '₹20', 20, 'Simple layered whole-wheat paratha.', false, 'plain-paratha'],
            ['breakfast', 'Pyaz Paratha', '₹50', 50, 'Onion-stuffed crisp paratha.', false, 'pyaz-paratha'],
            ['breakfast', 'Plain Roti', '₹10', 10, 'Tandoor-fresh whole wheat roti.', false, 'plain-roti'],
            ['breakfast', 'Butter Roti', '₹15', 15, 'Tandoori roti brushed with butter.', false, 'butter-roti'],
            ['breakfast', 'Puri Bhaji (5 Pieces)', '₹70', 70, 'Fluffy puris with spiced aloo bhaji.', false, 'puri-bhazi'],
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
            ['snacks', 'Paneer Pakoda (8 Pcs)', '₹150', 150, 'Crisp batter-fried paneer bites.', false, 'paneer-pakoda'],
            ['snacks', 'Mix Pakoda (250 gm)', '₹100', 100, 'Assorted monsoon fritters.', false, 'mix-pakoda'],
            ['snacks', 'Chole Samosa', '₹40', 40, 'Samosa topped with spicy chole.', false, null],
            ['snacks', 'Dahi Samosa', '₹50', 50, 'Samosa chaat with curd & chutneys.', false, null],
            ['snacks', 'Bread Cutlet (2 Pcs)', '₹50', 50, 'Crisp bread cutlets with chutney.', false, 'bread-cutlet'],

            ['thali', 'Veg Thali', '₹80', 80, 'Sabji + Dal + Roti + Salad + Rice.', false, 'veg-thali'],
            ['thali', 'Special Thali', '₹120', 120, 'Paneer + Dal + 4 Roti + Raita + Rice + Salad.', true, 'special-veg-thali'],

            ['maincourse', 'Rajma Chawal', '₹40 / 70', 40, 'Half / Full. Homestyle rajma over steamed rice.', false, 'rajma-chawal'],
            ['maincourse', 'Kadhi Chawal', '₹40 / 70', 40, 'Half / Full. Tangy kadhi with rice.', false, 'kadhi-chawal'],
            ['maincourse', 'Chole Chawal', '₹40 / 70', 40, 'Half / Full. Amritsari chole with rice.', false, 'chole-chawal'],
            ['maincourse', 'Dal Chawal', '₹40 / 70', 40, 'Half / Full. Comforting dal with rice.', false, 'daal-chawal'],

            ['paneer', 'Mutter Paneer', '₹150 / 260', 150, 'Half / Full. Peas & paneer in homestyle gravy.', false, 'matar-paneer'],
            ['paneer', 'Kadhai Paneer', '₹200 / 320', 200, 'Half / Full. Wok-tossed with capsicum & kadhai masala.', true, 'kadhai-paneer'],
            ['paneer', 'Paneer Butter Masala', '₹190 / 300', 190, 'Half / Full. Rich makhani gravy, best with naan.', false, 'paneer-butter-masala'],
            ['paneer', 'Paneer Burji', '₹180 / 340', 180, 'Half / Full. Scrambled paneer with onion-tomato masala.', false, 'paneer-bhurji'],
            ['paneer', 'Paneer Do Pyaza', '₹220 / 340', 220, 'Half / Full. Paneer tossed with double onions & masala.', true, 'paneer-do-pyaza'],

            ['veg', 'Mix Veg', '₹90 / 160', 90, 'Half / Full. Seasonal garden vegetables.', false, 'mix-veg'],
            ['veg', 'Aloo Matar', '₹70 / 120', 70, 'Half / Full. Potato & peas homestyle curry.', false, 'alu-matar'],
            ['veg', 'Aloo Shimla', '₹80 / 150', 80, 'Half / Full. Potato with crunchy capsicum.', false, 'alu-shimla-mirch'],
            ['veg', 'Aloo Gobhi', '₹80 / 150', 80, 'Half / Full. Classic potato-cauliflower sabji.', false, 'alu-gobhi'],
            ['veg', 'Gobhi Masala', '₹80 / 150', 80, 'Half / Full. Cauliflower in spiced masala.', false, 'gobhi-masala'],
            ['veg', 'Aloo Jeera', '₹70 / 120', 70, 'Half / Full. Tempered with roasted cumin.', false, 'alu-zeera'],
            ['veg', 'Shev Bhaji', '₹150', 150, 'Spicy Kolhapuri-style sev curry.', false, 'sev-bhaji'],

            ['dal', 'Dal Fry', '₹70 / 130', 70, 'Half / Full. Ghee-garlic tempered arhar dal.', false, 'dal-fry'],
            ['dal', 'Dal Tadka', '₹70 / 130', 70, 'Half / Full. Smoky tadka dal.', false, 'dal-tadka'],
            ['dal', 'Dal Makhani', '₹100 / 180', 100, 'Half / Full. Slow-cooked black urad, butter & cream.', true, 'dal-makhni'],
            ['dal', 'Rajma', '₹80 / 140', 80, 'Half / Full. Jammu-style red kidney beans.', false, 'rajma'],
            ['dal', 'Chole', '₹80 / 140', 80, 'Half / Full. Amritsari chickpea curry.', false, 'chole'],
            ['dal', 'Chana Masala', '₹80 / 140', 80, 'Half / Full. Dry-spiced kala chana.', false, 'chana-masala'],
            ['dal', 'Kadhi Pakoda', '₹60 / 100', 60, 'Half / Full. Besan kadhi with soft pakodas.', false, 'kadhi'],

            ['chinese', 'Veg Noodles', '₹40 / 70', 40, 'Half / Full. Street-style wok-tossed noodles.', false, 'veg-noodles'],
            ['chinese', 'Hakka Noodles', '₹100', 100, 'Smoky hakka-style noodles.', false, 'hakka-noodles'],
            ['chinese', 'Schezwan Noodles', '₹110', 110, 'Fiery schezwan sauce noodles.', false, 'shezwan-noodles'],
            ['chinese', 'Paneer Noodles', '₹140', 140, 'Noodles tossed with paneer strips.', false, 'paneer-noodles'],
            ['chinese', 'Garlic Noodles', '₹80', 80, 'Burnt-garlic noodles.', false, 'garlic-noodles'],
            ['chinese', 'Veg Momos (8 Pcs)', '₹70', 70, 'Steamed, with spicy red chutney.', true, 'veg-momos'],
            ['chinese', 'Fried Momos (8 Pcs)', '₹100', 100, 'Golden crisp-fried momos.', false, 'fried-momos'],
            ['chinese', 'Kurkure Momos (8 Pcs)', '₹140', 140, 'Crunchy kurkure-coated momos.', false, 'kurkure-momos'],
            ['chinese', 'Tandoori Momos (8 Pcs)', '₹120', 120, 'Charred in clay tandoor, smoky & juicy.', false, 'veg-momos'],
            ['chinese', 'Manchurian (Dry)', '₹170', 170, 'Crisp veg dumplings, dry tossed.', false, 'manchuriyan-dry'],
            ['chinese', 'Manchurian (Gravy)', '₹140', 140, 'Veg dumplings in garlic-soy gravy.', false, 'manchuriyan-gravy'],
            ['chinese', 'Chilli Potato', '₹140', 140, 'Crisp fingers in chilli-garlic glaze.', false, 'chilli-patato'],
            ['chinese', 'Honey Chilli Potato', '₹180', 180, 'Sweet-heat honey chilli glaze.', false, 'honey-chilli-potato'],
            ['chinese', 'French Fry', '₹120', 120, 'Golden salted fries.', false, 'french-fries'],
            ['chinese', 'Peri Peri', '₹140', 140, 'Dusted with peri peri masala.', false, 'peri-peri-fries'],
            ['chinese', 'White Sos Pasta', '₹200', 200, 'Creamy alfredo-style pasta.', false, 'white-sauce-pasta'],
            ['chinese', 'Red Sos Pasta', '₹180', 180, 'Tangy tomato-basil pasta.', false, 'red-sauce-pasta'],
            ['chinese', 'Mix Sos Pasta', '₹160', 160, 'Pink sauce, best of both.', false, 'mix-sauce-pasta'],
            ['chinese', 'Chilli Paneer (Gravy)', '₹220', 220, 'Paneer cubes in spicy gravy.', false, 'chilli-panner-gravy'],
            ['chinese', 'Chilli Paneer (Dry)', '₹180', 180, 'Dry-tossed starter style.', false, 'chilli-panner-dry'],
            ['chinese', 'Veg Fried Rice', '₹130', 130, 'Smoky wok rice with crunchy veg.', false, 'fried-rice'],
            ['chinese', 'Paneer Fried Rice', '₹150', 150, 'Fried rice with paneer cubes.', true, 'paneer-fried-rice'],
            ['chinese', 'Veg Schezwan Rice', '₹140', 140, 'Spicy schezwan fried rice.', false, 'shezwan-rice'],
            ['chinese', 'Jeera Rice', '₹70 / 120', 70, 'Half / Full. Basmati tempered with ghee-roasted cumin.', false, 'jeera-rice'],
            ['chinese', 'Steam Rice', '₹50 / 90', 50, 'Half / Full. Plain steamed basmati.', false, 'steam-rice'],

            ['raita', 'Vegetable Raita', '₹70 / 120', 70, 'Half / Full. Cucumber-onion raita.', false, 'vegetable-raita'],
            ['raita', 'Bundi Raita', '₹60 / 100', 60, 'Half / Full. Crisp boondi in curd.', false, 'boondi-raita'],
            ['raita', 'Plain Dahi (Curd)', '₹20 / 40 / 80', 20, 'Fresh set curd, three serving sizes.', false, 'dahi'],

            ['maggi', 'Plain Maggie', '₹50', 50, 'Classic masala maggi.', false, 'plane-maggie'],
            ['maggi', 'Veg Maggie', '₹70', 70, 'Loaded with garden vegetables.', false, 'vegitable-maggie'],
            ['maggi', 'Paneer Maggie', '₹90', 90, 'With soft paneer cubes.', false, 'panner-maggie'],
            ['maggi', 'Cheese Maggie', '₹110', 110, 'Topped with molten cheese.', false, 'cheez-maggie'],

            ['beverages', 'Tea', '₹20', 20, 'Kadak doodh chai.', false, 'tea'],
            ['beverages', 'Masala Tea', '₹30', 30, 'Brewed with crushed spices.', false, 'tea'],
            ['beverages', 'Lemon Tea', '₹40', 40, 'Light & refreshing.', false, 'lemon-tea'],
            ['beverages', 'Black Tea', '₹30', 30, 'No-milk brew.', false, 'black-tea'],
            ['beverages', 'Green Tea', '₹40', 40, 'Light detox brew.', false, 'green-tea'],
            ['beverages', 'Ice Tea', '₹70', 70, 'Chilled lemon ice tea.', false, 'ice-tea'],
            ['beverages', 'Hot Coffee', '₹60', 60, 'Steaming filter-style coffee.', false, 'hot-coffee'],
            ['beverages', 'Cold Coffee', '₹130', 130, 'Thick blended frappe.', false, 'cold-coffee'],
            ['beverages', 'Black Coffee', '₹40', 40, 'Bold & bitter brew.', false, 'black-coffee'],
            ['beverages', 'Banana Shake', '₹90', 90, 'Thick milk shake.', false, 'banana-shake'],
            ['beverages', 'Mango Shake', '₹90', 90, 'Seasonal alphonso-style shake.', false, 'mango-shake'],
            ['beverages', 'KitKat Shake', '₹120', 120, 'Chocolate wafer shake.', false, 'kitkat-shake'],
            ['beverages', 'Orio Shake', '₹120', 120, 'Cookies & cream shake.', false, 'oreo-shake'],
            ['beverages', 'Vanila Shake', '₹120', 120, 'Classic vanilla bean shake.', false, 'vanilla-shake'],
            ['beverages', 'Badam Shake', '₹120', 120, 'Kesar-badam milk shake.', false, null],
            ['beverages', 'Fresh Lemon Soda', '₹70', 70, 'Sweet / salted / mixed.', false, 'lemon-soda'],
            ['beverages', 'Mint Mojito', '₹110', 110, 'Virgin mint-lime cooler.', false, 'mint-mojito'],
            ['beverages', 'Blue Lagoon', '₹100', 100, 'Electric-blue citrus cooler.', false, 'blue-lagoon'],
            ['beverages', 'Shikanji', '₹60', 60, 'Old Delhi-style nimbu masala.', false, 'shikanji'],
            ['beverages', 'Lemon Water', '₹50', 50, 'Simple nimbu pani.', false, 'lemon-water'],
            ['beverages', 'Masala Chach', '₹40', 40, 'Spiced buttermilk.', false, 'masala-chach'],
            ['beverages', 'Sweet Lassi', '₹80', 80, 'Thick curd lassi with malai.', false, 'sweet-lassi'],
            ['beverages', 'Mineral Water', '₹20', 20, 'Sealed bottle.', false, 'mineral-water'],
            ['beverages', 'Cold Drink', 'MRP', 0, 'Chilled soft drink.', false, null],
        ];
    }
}
