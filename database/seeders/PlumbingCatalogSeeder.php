<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue for the Plumbing template: pipes, fittings, bathroom, taps,
 * valves, pumps, sanitaryware, tanks, drainage and tools. Every record is tagged
 * `template = plumbing`, so it never appears in the other storefronts. Idempotent.
 */
class PlumbingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 900, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $categories = [
            ['slug' => 'pipes-fittings', 'name' => 'Pipes & Fittings', 'tagline' => 'PVC, CPVC & UPVC pipes with matching fittings', 'image' => $img('1718347791747-35fac02b585e')],
            ['slug' => 'bathroom', 'name' => 'Bathroom Fittings', 'tagline' => 'Showers, health faucets, concealed bodies & accessories', 'image' => $img('1652662700928-5a4685e87d64')],
            ['slug' => 'taps-faucets', 'name' => 'Taps & Faucets', 'tagline' => 'Basin mixers, wall mixers, sink taps & cocks', 'image' => $img('1542855368-ca6ea825bca2')],
            ['slug' => 'valves', 'name' => 'Valves', 'tagline' => 'Ball, gate, check & float valves in brass and PVC', 'image' => $img('1759148414485-5f624fe9d1ea')],
            ['slug' => 'water-pumps', 'name' => 'Water Pumps', 'tagline' => 'Self-priming, submersible, openwell & booster pumps', 'image' => $img('1534641614095-6222aed9bdd6')],
            ['slug' => 'sanitaryware', 'name' => 'Sanitaryware', 'tagline' => 'Wash basins, water closets, urinals & seat covers', 'image' => $img('1629079447777-1e605162dc8d')],
            ['slug' => 'water-tanks', 'name' => 'Water Tanks', 'tagline' => 'Overhead, loft & underground storage tanks', 'image' => $img('1562185412-99dacaad0c9a')],
            ['slug' => 'drainage', 'name' => 'Drainage', 'tagline' => 'SWR pipes, floor traps, gratings & drain covers', 'image' => $img('1545193329-4a052e14eb8f')],
            ['slug' => 'tools-hardware', 'name' => 'Tools & Hardware', 'tagline' => 'Wrenches, cutters, sealants, clamps & fasteners', 'image' => $img('1503789146722-cf137a3c0fea')],
        ];
        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug']], $c + [
                'sort_order' => $i,
                'description' => $c['tagline'],
                'template' => 'plumbing',
                'is_active' => true,
                'show_in_menu' => true,
            ]);
        }

        $subs = [
            'pipes-fittings' => ['PVC Pipes' => 'pvc-pipes', 'CPVC Pipes & Fittings' => 'cpvc-pipes-fittings', 'UPVC Pipes & Fittings' => 'upvc-pipes-fittings', 'PVC Fittings' => 'pvc-fittings', 'Flexible Hoses & Connectors' => 'hoses-connectors'],
            'bathroom' => ['Showers' => 'showers', 'Health Faucets' => 'health-faucets', 'Concealed Fittings' => 'concealed-fittings', 'Bathroom Accessories' => 'bathroom-accessories'],
            'taps-faucets' => ['Basin Mixers' => 'basin-mixers', 'Wall Mixers' => 'wall-mixers', 'Kitchen Sink Taps' => 'kitchen-sink-taps', 'Pillar & Bib Cocks' => 'pillar-bib-cocks'],
            'valves' => ['Ball Valves' => 'ball-valves', 'Gate Valves' => 'gate-valves', 'Check Valves' => 'check-valves', 'Float Valves' => 'float-valves'],
            'water-pumps' => ['Self-priming Pumps' => 'self-priming-pumps', 'Submersible Pumps' => 'submersible-pumps', 'Openwell Pumps' => 'openwell-pumps', 'Pressure Booster Pumps' => 'booster-pumps'],
            'sanitaryware' => ['Wash Basins' => 'wash-basins', 'Water Closets' => 'water-closets', 'Urinals' => 'urinals', 'Seat Covers' => 'seat-covers'],
            'water-tanks' => ['Overhead Tanks' => 'overhead-tanks', 'Loft Tanks' => 'loft-tanks', 'Underground Tanks' => 'underground-tanks'],
            'drainage' => ['SWR Pipes' => 'swr-pipes', 'Floor Traps & Gratings' => 'floor-traps', 'Drain Covers' => 'drain-covers'],
            'tools-hardware' => ['Wrenches & Cutters' => 'wrenches-cutters', 'Sealants & Adhesives' => 'sealants-adhesives', 'Clamps & Hangers' => 'clamps-hangers', 'Fasteners' => 'fasteners'],
        ];
        foreach ($subs as $parentSlug => $children) {
            $j = 0;
            foreach ($children as $name => $slug) {
                $cat[$slug] = Category::updateOrCreate(['slug' => $slug], [
                    'name' => $name,
                    'parent_id' => $cat[$parentSlug]->id,
                    'sort_order' => $j++,
                    'template' => 'plumbing',
                    'is_active' => true,
                    'show_in_menu' => true,
                ]);
            }
        }

        $spec = fn (array $pairs) => collect($pairs)->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all();
        $pipe = [$img('1718347791747-35fac02b585e'), $img('1646009445351-b8192e095f3a')];
        $pvc = [$img('1545193329-4a052e14eb8f'), $img('1545193329-4a052e14eb8f')];
        $fit = [$img('1642797735471-3e90055c5ff9'), $img('1718347791747-35fac02b585e')];
        $valveImg = [$img('1759148414485-5f624fe9d1ea'), $img('1761758674188-2b8e4c89c5e2')];

        $products = [
            // ---- Pipes & fittings ----------------------------------------------------------
            ['slug' => 'astral-cpvc-pipe-sdr11', 'name' => 'Astral CPVC Pro Pipe SDR 11 (3 m)', 'category' => 'cpvc-pipes-fittings', 'brand' => 'Astral', 'material' => 'CPVC', 'price' => 385, 'compare_at_price' => 460, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch', '1.25 inch'], 'images' => $pipe, 'rating' => 4.7, 'review_count' => 1240, 'is_best_seller' => true,
                'description' => 'Hot and cold water CPVC pipe for concealed and exposed plumbing. Lead-free, corrosion-proof and rated for 93 °C.',
                'specifications' => $spec(['Material' => 'CPVC (ASTM D2846)', 'Pressure class' => 'SDR 11', 'Length' => '3 m', 'Colour' => 'Ivory', 'Max temperature' => '93 °C', 'Standard' => 'IS 15778']),
                'applications' => "Hot and cold water distribution\nConcealed bathroom and kitchen lines\nSolar water heater connections", 'installation_notes' => 'Cut square, chamfer the edge, apply one-step CPVC solvent cement to pipe and fitting, insert with a quarter turn and hold for 30 seconds. Allow 24 hours before pressure testing.'],
            ['slug' => 'astral-cpvc-elbow-90', 'name' => 'Astral CPVC Elbow 90° (Pack of 10)', 'category' => 'cpvc-pipes-fittings', 'brand' => 'Astral', 'material' => 'CPVC', 'price' => 210, 'compare_at_price' => 260, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch'], 'images' => $fit, 'rating' => 4.6, 'review_count' => 860, 'is_best_seller' => true,
                'description' => 'Solvent-weld 90° elbow for CPVC hot & cold water lines. Sold as a contractor pack of 10.',
                'specifications' => $spec(['Material' => 'CPVC', 'Type' => 'Elbow 90°', 'Joint' => 'Solvent weld', 'Pack size' => '10 pcs', 'Standard' => 'ASTM D2846']),
                'applications' => "Direction change in hot & cold water lines\nBathroom and kitchen concealed piping", 'installation_notes' => 'Dry-fit first, mark insertion depth, apply CPVC cement and push home with a quarter turn.'],
            ['slug' => 'astral-cpvc-tee', 'name' => 'Astral CPVC Equal Tee (Pack of 10)', 'category' => 'cpvc-pipes-fittings', 'brand' => 'Astral', 'material' => 'CPVC', 'price' => 265, 'compare_at_price' => 320, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch'], 'images' => [$fit[1], $fit[0]], 'rating' => 4.6, 'review_count' => 540,
                'description' => 'Equal tee for branching CPVC water lines. Contractor pack of 10.',
                'specifications' => $spec(['Material' => 'CPVC', 'Type' => 'Equal tee', 'Joint' => 'Solvent weld', 'Pack size' => '10 pcs']),
                'applications' => "Branch connections in water supply lines", 'installation_notes' => 'Use CPVC-specific solvent cement only. Do not use PVC cement.'],
            ['slug' => 'astral-cpvc-brass-fta', 'name' => 'Astral CPVC Female Threaded Adapter — Brass', 'category' => 'cpvc-pipes-fittings', 'brand' => 'Astral', 'material' => 'CPVC + Brass', 'price' => 145, 'compare_at_price' => 175, 'sizes' => ['1/2 inch', '3/4 inch'], 'images' => [$fit[0]], 'rating' => 4.5, 'review_count' => 390,
                'description' => 'Brass-insert female adapter for connecting CPVC pipe to taps, geysers and metal fittings.',
                'specifications' => $spec(['Material' => 'CPVC body, brass insert', 'Thread' => 'BSP female', 'Joint' => 'Solvent weld × thread']),
                'applications' => "Tap and geyser connections\nTransition from CPVC to GI or brass", 'installation_notes' => 'Wrap 8–10 turns of PTFE tape on the mating male thread; do not over-tighten.'],
            ['slug' => 'supreme-upvc-pipe-sch40', 'name' => 'Supreme Aqua Gold UPVC Pipe Sch 40 (3 m)', 'category' => 'upvc-pipes-fittings', 'brand' => 'Supreme', 'material' => 'UPVC', 'price' => 420, 'compare_at_price' => 495, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch', '1.5 inch', '2 inch'], 'images' => [$pipe[1], $pvc[1]], 'rating' => 4.6, 'review_count' => 720,
                'description' => 'Schedule 40 UPVC pipe for cold water supply. Lead-free, UV-stabilised for exposed lines.',
                'specifications' => $spec(['Material' => 'UPVC', 'Schedule' => 'Sch 40', 'Length' => '3 m', 'Standard' => 'ASTM D1785', 'Colour' => 'White']),
                'applications' => "Cold water supply\nOverhead tank to bathroom lines\nGarden and utility lines", 'installation_notes' => 'Solvent-weld with UPVC cement; support every 1 m on horizontal runs.'],
            ['slug' => 'supreme-upvc-elbow', 'name' => 'Supreme UPVC Elbow 90° Sch 40 (Pack of 10)', 'category' => 'upvc-pipes-fittings', 'brand' => 'Supreme', 'material' => 'UPVC', 'price' => 180, 'compare_at_price' => 220, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch'], 'images' => $fit, 'rating' => 4.5, 'review_count' => 310,
                'description' => 'Schedule 40 UPVC elbow. Pack of 10.', 'specifications' => $spec(['Material' => 'UPVC', 'Type' => 'Elbow 90°', 'Pack size' => '10 pcs']), 'applications' => "Cold water supply lines"],
            ['slug' => 'finolex-pvc-pipe-6kg', 'name' => 'Finolex PVC Pipe 6 kg/cm² (6 m)', 'category' => 'pvc-pipes', 'brand' => 'Finolex', 'material' => 'PVC', 'price' => 690, 'compare_at_price' => 790, 'sizes' => ['1 inch', '1.5 inch', '2 inch', '3 inch', '4 inch'], 'images' => $pvc, 'rating' => 4.6, 'review_count' => 980, 'is_best_seller' => true,
                'description' => 'Agricultural and plumbing grade PVC pressure pipe, 6 kg/cm² class, 6 metre length.',
                'specifications' => $spec(['Material' => 'PVC', 'Pressure rating' => '6 kg/cm²', 'Length' => '6 m', 'Standard' => 'IS 4985', 'Joint' => 'Solvent / ring fit']),
                'applications' => "Borewell delivery lines\nGarden and agriculture\nCold water mains", 'installation_notes' => 'Bed in fine sand when buried; avoid sharp bends and support at 1.5 m intervals.'],
            ['slug' => 'finolex-pvc-coupler', 'name' => 'Finolex PVC Coupler (Pack of 10)', 'category' => 'pvc-fittings', 'brand' => 'Finolex', 'material' => 'PVC', 'price' => 160, 'sizes' => ['1 inch', '1.5 inch', '2 inch'], 'images' => [$fit[1]], 'rating' => 4.4, 'review_count' => 220,
                'description' => 'Straight coupler for joining PVC pressure pipes. Pack of 10.', 'specifications' => $spec(['Material' => 'PVC', 'Type' => 'Coupler', 'Pack size' => '10 pcs'])],
            ['slug' => 'ashirvad-pvc-tee', 'name' => 'Ashirvad PVC Tee 6 kg (Pack of 5)', 'category' => 'pvc-fittings', 'brand' => 'Ashirvad', 'material' => 'PVC', 'price' => 245, 'compare_at_price' => 290, 'sizes' => ['1 inch', '1.5 inch', '2 inch'], 'images' => [$fit[0], $pvc[0]], 'rating' => 4.5, 'review_count' => 175,
                'description' => 'Equal tee for PVC pressure lines. Pack of 5.', 'specifications' => $spec(['Material' => 'PVC', 'Type' => 'Equal tee', 'Pack size' => '5 pcs', 'Pressure' => '6 kg/cm²'])],
            ['slug' => 'ashirvad-cpvc-pipe', 'name' => 'Ashirvad FlowGuard Plus CPVC Pipe (3 m)', 'category' => 'cpvc-pipes-fittings', 'brand' => 'Ashirvad', 'material' => 'CPVC', 'price' => 399, 'compare_at_price' => 470, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch'], 'images' => [$pipe[0]], 'rating' => 4.7, 'review_count' => 640, 'is_new' => true,
                'description' => 'FlowGuard Plus CPVC for hot & cold water. Smooth bore for higher flow and no scaling.',
                'specifications' => $spec(['Material' => 'CPVC', 'SDR' => '11', 'Length' => '3 m', 'Max temperature' => '93 °C']), 'applications' => "Hot & cold water lines\nGeyser connections"],
            ['slug' => 'ss-flexible-connection-pipe', 'name' => 'Stainless Steel Flexible Connection Pipe', 'category' => 'hoses-connectors', 'brand' => 'Jaquar', 'material' => 'Stainless steel', 'price' => 349, 'compare_at_price' => 420, 'sizes' => ['12 inch', '18 inch', '24 inch'], 'images' => [$img('1777713174987-b0c30ccc7dcf'), $img('1588501985911-6932ea666801')], 'rating' => 4.5, 'review_count' => 1100, 'is_best_seller' => true,
                'description' => 'Braided SS-304 flexible hose for connecting taps, geysers and cisterns. 1/2" × 1/2" nuts.',
                'specifications' => $spec(['Material' => 'SS-304 braid, EPDM inner tube', 'Connection' => '1/2" BSP female × female', 'Working pressure' => '10 bar', 'Temperature' => 'Up to 90 °C']),
                'applications' => "Basin mixer and pillar cock connections\nGeyser inlet/outlet\nFlush tank inlet", 'installation_notes' => 'Hand-tighten, then a quarter turn with a spanner. Do not twist the hose while tightening.'],

            // ---- Bathroom -------------------------------------------------------------------
            ['slug' => 'jaquar-overhead-shower-200', 'name' => 'Jaquar Maze Overhead Shower 200 mm', 'category' => 'showers', 'brand' => 'Jaquar', 'material' => 'Stainless steel', 'price' => 2890, 'compare_at_price' => 3450, 'sizes' => ['200 mm'], 'images' => [$img('1627008952576-80b761eb6655'), $img('1576678433413-202829a1ab98')], 'rating' => 4.7, 'review_count' => 430, 'is_best_seller' => true,
                'description' => 'Ultra-slim square rain shower with silicone anti-lime nozzles. Includes ceiling arm.',
                'specifications' => $spec(['Material' => 'Stainless steel', 'Size' => '200 × 200 mm', 'Finish' => 'Chrome', 'Nozzles' => 'Silicone, easy-clean', 'Connection' => '1/2" BSP', 'Warranty' => '10 years']),
                'applications' => "Master and guest bathrooms\nHotel and villa projects", 'installation_notes' => 'Mount on a 1/2" ceiling arm; use PTFE tape on the thread. Minimum 0.5 bar pressure for full spread.'],
            ['slug' => 'hindware-health-faucet', 'name' => 'Hindware Health Faucet with 1.2 m Hose & Hook', 'category' => 'health-faucets', 'brand' => 'Hindware', 'material' => 'ABS, chrome', 'price' => 799, 'compare_at_price' => 1050, 'sizes' => ['Standard'], 'images' => [$img('1698724624855-e9dbc5a0bddb')], 'rating' => 4.5, 'review_count' => 2900, 'is_best_seller' => true,
                'description' => 'ABS health faucet with chrome finish, 1.2 m SS hose and wall hook. Complete set.',
                'specifications' => $spec(['Material' => 'ABS body, chrome plated', 'Hose' => '1.2 m stainless steel', 'Includes' => 'Faucet, hose, wall hook', 'Connection' => '1/2" BSP', 'Warranty' => '5 years']),
                'applications' => "Toilets and bathrooms", 'installation_notes' => 'Connect the hose to a 1/2" angle cock; fix the hook with the supplied screw and rawl plug.'],
            ['slug' => 'jaquar-concealed-body', 'name' => 'Jaquar Single Lever Concealed Body (High Flow)', 'category' => 'concealed-fittings', 'brand' => 'Jaquar', 'material' => 'Brass', 'price' => 3150, 'compare_at_price' => 3690, 'sizes' => ['Standard'], 'images' => [$img('1567102109796-90071d28cb38'), $img('1623111771733-d3ab4d26ce41')], 'rating' => 4.6, 'review_count' => 260,
                'description' => 'Concealed single-lever body for shower/bath with 3-way diverter compatibility. Exposed part sold separately.',
                'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Single lever concealed body', 'Flow' => 'High flow', 'Inlet/outlet' => '3/4" BSP', 'Warranty' => '10 years']),
                'applications' => "Concealed shower and bath installations", 'installation_notes' => 'Install before tiling with the protective cap on; keep the body level and 60–90 mm inside the finished wall.'],
            ['slug' => 'cera-towel-rail-600', 'name' => 'Cera Stainless Steel Towel Rail 600 mm', 'category' => 'bathroom-accessories', 'brand' => 'Cera', 'material' => 'Stainless steel', 'price' => 890, 'compare_at_price' => 1100, 'sizes' => ['600 mm'], 'images' => [$img('1685084844860-5d94e6c82939'), $img('1747227830516-b9643cc377fb')], 'rating' => 4.4, 'review_count' => 410,
                'description' => 'SS-304 single towel rail with concealed fixing, rust-proof for humid bathrooms.',
                'specifications' => $spec(['Material' => 'SS-304', 'Length' => '600 mm', 'Finish' => 'Glossy', 'Fixing' => 'Concealed screws (included)'])],
            ['slug' => 'bathroom-accessory-set-6', 'name' => 'Bathroom Accessory Set — 6 pieces', 'category' => 'bathroom-accessories', 'brand' => 'Cera', 'material' => 'Stainless steel', 'price' => 2190, 'compare_at_price' => 2890, 'sizes' => ['6 pcs'], 'images' => [$img('1747227830516-b9643cc377fb'), $img('1685084844860-5d94e6c82939')], 'rating' => 4.5, 'review_count' => 190, 'is_new' => true,
                'description' => 'Towel rail, towel ring, soap dish, tumbler holder, robe hook and paper holder in matching SS-304.',
                'specifications' => $spec(['Material' => 'SS-304', 'Pieces' => '6', 'Finish' => 'Glossy chrome'])],

            // ---- Taps & faucets ------------------------------------------------------------
            ['slug' => 'jaquar-basin-mixer-single-lever', 'name' => 'Jaquar Kubix Prime Single Lever Basin Mixer', 'category' => 'basin-mixers', 'brand' => 'Jaquar', 'material' => 'Brass', 'price' => 4290, 'compare_at_price' => 5200, 'sizes' => ['Standard'], 'images' => [$img('1542855368-ca6ea825bca2'), $img('1587093430416-0dc23a0c62c1')], 'rating' => 4.8, 'review_count' => 780, 'is_best_seller' => true,
                'description' => 'Single-lever basin mixer with 450 mm braided hoses and ceramic disc cartridge.',
                'specifications' => $spec(['Material' => 'Brass', 'Cartridge' => '35 mm ceramic disc', 'Finish' => 'Chrome', 'Includes' => '2 × 450 mm braided hoses', 'Warranty' => '10 years']),
                'applications' => "Wash basins with hot & cold supply", 'installation_notes' => 'Requires 1/2" hot and cold angle cocks below the basin; tighten the base nut with the supplied tool.'],
            ['slug' => 'hindware-wall-mixer', 'name' => 'Hindware Contessa Plus Wall Mixer with Bend', 'category' => 'wall-mixers', 'brand' => 'Hindware', 'material' => 'Brass', 'price' => 3690, 'compare_at_price' => 4500, 'sizes' => ['Standard'], 'images' => [$img('1567102109796-90071d28cb38')], 'rating' => 4.6, 'review_count' => 520,
                'description' => 'Two-in-one wall mixer with bath spout and overhead shower bend arrangement.',
                'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Wall mixer 2-in-1', 'Finish' => 'Chrome', 'Connection' => '3/4" BSP', 'Warranty' => '10 years']),
                'applications' => "Shower area with overhead shower and bath spout"],
            ['slug' => 'cera-kitchen-sink-mixer', 'name' => 'Cera Swivel Spout Kitchen Sink Mixer (Table Mounted)', 'category' => 'kitchen-sink-taps', 'brand' => 'Cera', 'material' => 'Brass', 'price' => 2490, 'compare_at_price' => 2950, 'sizes' => ['Standard'], 'images' => [$img('1542855368-ca6ea825bca2'), $img('1661045327753-3f2a047d00a4')], 'rating' => 4.5, 'review_count' => 340,
                'description' => 'Table-mounted single-lever sink mixer with 360° swivel spout and aerator.',
                'specifications' => $spec(['Material' => 'Brass', 'Mounting' => 'Table / deck', 'Spout' => '360° swivel', 'Aerator' => 'Yes', 'Warranty' => '7 years']),
                'applications' => "Kitchen sinks with hot & cold lines"],
            ['slug' => 'jaquar-sink-cock-wall', 'name' => 'Jaquar Continental Sink Cock (Wall Mounted)', 'category' => 'kitchen-sink-taps', 'brand' => 'Jaquar', 'material' => 'Brass', 'price' => 1590, 'compare_at_price' => 1890, 'sizes' => ['Standard'], 'images' => [$img('1629078692818-c5a0443f4ae3')], 'rating' => 4.6, 'review_count' => 610,
                'description' => 'Quarter-turn wall-mounted sink cock with swinging spout and ceramic disc.', 'specifications' => $spec(['Material' => 'Brass', 'Mounting' => 'Wall', 'Cartridge' => 'Quarter-turn ceramic', 'Finish' => 'Chrome'])],
            ['slug' => 'pillar-cock-quarter-turn', 'name' => 'Parryware Quarter-turn Pillar Cock', 'category' => 'pillar-bib-cocks', 'brand' => 'Parryware', 'material' => 'Brass', 'price' => 990, 'compare_at_price' => 1250, 'sizes' => ['Standard'], 'images' => [$img('1587093430416-0dc23a0c62c1')], 'rating' => 4.4, 'review_count' => 890, 'is_best_seller' => true,
                'description' => 'Basin pillar cock with quarter-turn ceramic disc and foam-flow aerator.', 'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Pillar cock', 'Cartridge' => 'Quarter-turn ceramic', 'Connection' => '1/2" BSP'])],
            ['slug' => 'bib-cock-long-body', 'name' => 'Jaquar Long-body Bib Cock with Wall Flange', 'category' => 'pillar-bib-cocks', 'brand' => 'Jaquar', 'material' => 'Brass', 'price' => 1290, 'compare_at_price' => 1550, 'sizes' => ['Standard'], 'images' => [$img('1642014586226-6de3da27a974')], 'rating' => 4.5, 'review_count' => 470,
                'description' => 'Long-body bib cock for bathrooms, balconies and utility areas.', 'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Bib cock, long body', 'Connection' => '1/2" BSP', 'Includes' => 'Wall flange'])],
            ['slug' => 'angle-cock-quarter-turn', 'name' => 'Hindware Angle Cock (Quarter-turn)', 'category' => 'pillar-bib-cocks', 'brand' => 'Hindware', 'material' => 'Brass', 'price' => 690, 'compare_at_price' => 850, 'sizes' => ['Standard'], 'images' => [$img('1583676404098-20b58c09609b')], 'rating' => 4.5, 'review_count' => 1300, 'is_best_seller' => true,
                'description' => 'Angle valve for basin, cistern and geyser connections.', 'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Angle cock', 'Connection' => '1/2" × 1/2" BSP'])],

            // ---- Valves ---------------------------------------------------------------------
            ['slug' => 'zoloto-brass-ball-valve', 'name' => 'Zoloto Forged Brass Ball Valve (Full Bore)', 'category' => 'ball-valves', 'brand' => 'Zoloto', 'material' => 'Brass', 'price' => 425, 'compare_at_price' => 520, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch', '1.5 inch', '2 inch'], 'images' => $valveImg, 'rating' => 4.7, 'review_count' => 960, 'is_best_seller' => true,
                'description' => 'Full-bore forged brass ball valve with lever handle, screwed ends. PN 25.',
                'specifications' => $spec(['Material' => 'Forged brass', 'Bore' => 'Full bore', 'Ends' => 'Screwed BSP', 'Pressure rating' => 'PN 25', 'Handle' => 'Steel lever', 'Standard' => 'IS 778']),
                'applications' => "Main water shut-off\nTank inlet/outlet control\nPump lines", 'installation_notes' => 'Use PTFE tape on threads; keep the handle accessible for a full quarter-turn.'],
            ['slug' => 'pvc-ball-valve', 'name' => 'Supreme PVC Ball Valve (Solvent)', 'category' => 'ball-valves', 'brand' => 'Supreme', 'material' => 'PVC', 'price' => 165, 'compare_at_price' => 199, 'sizes' => ['1 inch', '1.5 inch', '2 inch', '3 inch'], 'images' => [$img('1784881940734-58f8b8bce631')], 'rating' => 4.4, 'review_count' => 380,
                'description' => 'Solvent-weld PVC ball valve for cold water and irrigation lines.', 'specifications' => $spec(['Material' => 'PVC', 'Ends' => 'Solvent weld', 'Pressure' => '10 kg/cm²'])],
            ['slug' => 'gate-valve-brass', 'name' => 'Leader Brass Gate Valve (Screwed)', 'category' => 'gate-valves', 'brand' => 'Leader', 'material' => 'Brass', 'price' => 690, 'compare_at_price' => 790, 'sizes' => ['1 inch', '1.5 inch', '2 inch'], 'images' => [$img('1786294112341-b86211513b92'), $valveImg[0]], 'rating' => 4.5, 'review_count' => 240,
                'description' => 'Rising-stem brass gate valve for isolating water mains.', 'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Gate valve, rising stem', 'Ends' => 'Screwed BSP', 'Standard' => 'IS 778'])],
            ['slug' => 'check-valve-brass', 'name' => 'Zoloto Non-return (Check) Valve — Brass', 'category' => 'check-valves', 'brand' => 'Zoloto', 'material' => 'Brass', 'price' => 560, 'compare_at_price' => 650, 'sizes' => ['1 inch', '1.5 inch', '2 inch'], 'images' => [$valveImg[1]], 'rating' => 4.5, 'review_count' => 310,
                'description' => 'Horizontal swing check valve to prevent backflow on pump delivery lines.', 'specifications' => $spec(['Material' => 'Brass', 'Type' => 'Swing check', 'Ends' => 'Screwed BSP']), 'applications' => "Pump delivery lines\nOverhead tank inlet"],
            ['slug' => 'float-valve-brass', 'name' => 'Brass Float Valve with Copper Ball', 'category' => 'float-valves', 'brand' => 'Leader', 'material' => 'Brass', 'price' => 480, 'compare_at_price' => 560, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch'], 'images' => [$img('1556995378-e0a5c979ec57')], 'rating' => 4.3, 'review_count' => 420,
                'description' => 'Automatic tank float valve with copper ball — stops overflow in overhead tanks.', 'specifications' => $spec(['Material' => 'Brass body, copper ball', 'Type' => 'Float / ball cock']), 'applications' => "Overhead and loft tank inlets"],

            // ---- Water pumps ----------------------------------------------------------------
            ['slug' => 'kirloskar-self-priming-1hp', 'name' => 'Kirloskar Chhotu 1 HP Self-priming Monoblock Pump', 'category' => 'self-priming-pumps', 'brand' => 'Kirloskar', 'material' => 'Cast iron', 'price' => 5490, 'compare_at_price' => 6800, 'sizes' => ['1 HP'], 'images' => [$img('1534641614095-6222aed9bdd6'), $img('1700318092011-6e4666e94ab5')], 'rating' => 4.6, 'review_count' => 2100, 'is_best_seller' => true,
                'description' => 'Compact self-priming monoblock for domestic overhead tank filling. Suction up to 8 m, head up to 30 m.',
                'specifications' => $spec(['Power' => '1 HP (0.75 kW)', 'Supply' => '230 V single phase', 'Suction' => '8 m', 'Head' => 'Up to 30 m', 'Discharge' => 'Up to 2,400 LPH', 'Inlet/outlet' => '25 × 25 mm', 'Warranty' => '1 year']),
                'applications' => "Ground tank to overhead tank\nApartments and independent houses", 'installation_notes' => 'Install a foot valve on the suction line, prime the pump before first start and protect with a suitable MCB.'],
            ['slug' => 'crompton-submersible-1hp', 'name' => 'Crompton 1 HP Borewell Submersible Pump (4")', 'category' => 'submersible-pumps', 'brand' => 'Crompton', 'material' => 'Stainless steel', 'price' => 11990, 'compare_at_price' => 14500, 'sizes' => ['1 HP'], 'images' => [$img('1696371269814-ae41fc67cf03')], 'rating' => 4.5, 'review_count' => 640,
                'description' => '4-inch oil-filled borewell submersible with 10 stages, control panel included.',
                'specifications' => $spec(['Power' => '1 HP', 'Bore size' => '100 mm (4")', 'Stages' => '10', 'Head' => 'Up to 60 m', 'Outlet' => '25 mm', 'Includes' => 'Control panel', 'Warranty' => '1 year']),
                'applications' => "Borewells up to 60 m\nFarm and domestic supply", 'installation_notes' => 'Lower with a nylon rope, never by the cable. Use the supplied control panel for dry-run protection.'],
            ['slug' => 'cri-openwell-pump', 'name' => 'CRI 1 HP Openwell Submersible Pump', 'category' => 'openwell-pumps', 'brand' => 'CRI', 'material' => 'Cast iron', 'price' => 6990, 'compare_at_price' => 8200, 'sizes' => ['1 HP'], 'images' => [$img('1638294835410-1f238127dfd8')], 'rating' => 4.4, 'review_count' => 330,
                'description' => 'Horizontal openwell submersible for sumps and open wells; no priming needed.', 'specifications' => $spec(['Power' => '1 HP', 'Type' => 'Openwell submersible', 'Head' => 'Up to 25 m', 'Outlet' => '32 mm'])],
            ['slug' => 'pressure-booster-pump', 'name' => 'Kirloskar Pressure Booster Pump 0.5 HP with Tank', 'category' => 'booster-pumps', 'brand' => 'Kirloskar', 'material' => 'Stainless steel', 'price' => 9490, 'compare_at_price' => 11200, 'sizes' => ['0.5 HP'], 'images' => [$img('1623986854615-85baba27dfb6')], 'rating' => 4.5, 'review_count' => 210, 'is_new' => true,
                'description' => 'Automatic pressure booster with 24 L tank for rain showers and multi-bathroom homes.',
                'specifications' => $spec(['Power' => '0.5 HP', 'Tank' => '24 L', 'Pressure' => 'Up to 3 bar', 'Control' => 'Automatic pressure switch']), 'applications' => "Rain showers and body jets\nTop-floor bathrooms with low pressure"],

            // ---- Sanitaryware ---------------------------------------------------------------
            ['slug' => 'hindware-wall-hung-basin', 'name' => 'Hindware Wall-hung Wash Basin 550 mm', 'category' => 'wash-basins', 'brand' => 'Hindware', 'material' => 'Vitreous china', 'price' => 2990, 'compare_at_price' => 3600, 'sizes' => ['550 × 430 mm'], 'images' => [$img('1629079447777-1e605162dc8d'), $img('1576698483491-8c43f0862543')], 'rating' => 4.5, 'review_count' => 560, 'is_best_seller' => true,
                'description' => 'Vitreous china wall-hung basin with single tap hole and overflow.',
                'specifications' => $spec(['Material' => 'Vitreous china', 'Size' => '550 × 430 mm', 'Tap holes' => '1', 'Colour' => 'White', 'Warranty' => '10 years']), 'installation_notes' => 'Fix with M8 rag bolts at 700–750 mm from finished floor; seal the wall joint with silicone.'],
            ['slug' => 'cera-table-top-basin', 'name' => 'Cera Table-top Round Basin', 'category' => 'wash-basins', 'brand' => 'Cera', 'material' => 'Vitreous china', 'price' => 3490, 'compare_at_price' => 4200, 'sizes' => ['410 mm'], 'images' => [$img('1747227830516-b9643cc377fb')], 'rating' => 4.6, 'review_count' => 280, 'is_new' => true,
                'description' => 'Countertop round basin for vanity units, no tap hole (use a tall basin mixer).', 'specifications' => $spec(['Material' => 'Vitreous china', 'Diameter' => '410 mm', 'Mounting' => 'Table top'])],
            ['slug' => 'parryware-one-piece-wc', 'name' => 'Parryware One-piece Water Closet (S-trap)', 'category' => 'water-closets', 'brand' => 'Parryware', 'material' => 'Vitreous china', 'price' => 8990, 'compare_at_price' => 11500, 'sizes' => ['S-trap 220 mm'], 'images' => [$img('1589824783837-6169889fa20f'), $img('1563204719-44395a035bb6')], 'rating' => 4.6, 'review_count' => 720, 'is_best_seller' => true,
                'description' => 'Rimless one-piece WC with dual flush (3/6 L) and soft-close seat cover.',
                'specifications' => $spec(['Material' => 'Vitreous china', 'Trap' => 'S-trap, 220 mm', 'Flush' => 'Dual 3/6 L', 'Seat' => 'Soft-close (included)', 'Warranty' => '10 years']),
                'applications' => "Homes, hotels and offices", 'installation_notes' => 'Set the floor outlet at 220 mm from the finished wall; connect the inlet with a 1/2" angle cock and flexible hose.'],
            ['slug' => 'hindware-wall-hung-wc', 'name' => 'Hindware Wall-hung WC with Concealed Cistern', 'category' => 'water-closets', 'brand' => 'Hindware', 'material' => 'Vitreous china', 'price' => 13490, 'compare_at_price' => 16900, 'sizes' => ['P-trap'], 'images' => [$img('1587527901949-ab0341697c1e')], 'rating' => 4.7, 'review_count' => 190,
                'description' => 'Wall-hung WC with slim concealed cistern, frame and dual-flush plate.', 'specifications' => $spec(['Material' => 'Vitreous china', 'Trap' => 'P-trap', 'Cistern' => 'Concealed, 3/6 L', 'Includes' => 'Frame, cistern, flush plate, seat'])],
            ['slug' => 'urinal-half-stall', 'name' => 'Cera Half-stall Urinal', 'category' => 'urinals', 'brand' => 'Cera', 'material' => 'Vitreous china', 'price' => 4290, 'compare_at_price' => 5100, 'sizes' => ['Standard'], 'images' => [$img('1738427456320-d35a31cf19f5')], 'rating' => 4.3, 'review_count' => 95,
                'description' => 'Wall-hung half-stall urinal for offices and commercial washrooms.', 'specifications' => $spec(['Material' => 'Vitreous china', 'Type' => 'Half stall', 'Inlet' => 'Top, 1/2"']), 'applications' => "Commercial and institutional washrooms"],
            ['slug' => 'soft-close-seat-cover', 'name' => 'Soft-close Toilet Seat Cover (Universal)', 'category' => 'seat-covers', 'brand' => 'Parryware', 'material' => 'Polypropylene', 'price' => 1190, 'compare_at_price' => 1450, 'sizes' => ['Standard'], 'images' => [$img('1563204719-44395a035bb6')], 'rating' => 4.4, 'review_count' => 640,
                'description' => 'Universal-fit slow-closing seat cover with quick-release hinges for cleaning.', 'specifications' => $spec(['Material' => 'Polypropylene', 'Hinges' => 'Quick release, soft close', 'Fit' => 'Universal'])],

            // ---- Water tanks ----------------------------------------------------------------
            ['slug' => 'sintex-triple-layer-tank-1000', 'name' => 'Sintex Titus Triple-layer Overhead Tank', 'category' => 'overhead-tanks', 'brand' => 'Sintex', 'material' => 'LLDPE', 'price' => 6990, 'compare_at_price' => 8400, 'sizes' => ['500 L', '1000 L', '1500 L', '2000 L'], 'images' => [$img('1562185412-99dacaad0c9a'), $img('1497990136619-d0c2de2b5b4b')], 'rating' => 4.6, 'review_count' => 1500, 'is_best_seller' => true,
                'description' => 'Triple-layer, UV-stabilised overhead tank with a white inner layer that keeps water cooler and cleaner.',
                'specifications' => $spec(['Material' => 'LLDPE, 3 layers', 'UV stabilised' => 'Yes', 'Colour' => 'Black / white inner', 'Lid' => 'Threaded', 'Standard' => 'IS 12701', 'Warranty' => '5 years']),
                'applications' => "Terrace overhead storage\nApartments and houses", 'installation_notes' => 'Place on a flat, fully supported platform; fit a float valve on the inlet and an overflow pipe.'],
            ['slug' => 'loft-tank-300', 'name' => 'Sintex Loft Tank 300 L', 'category' => 'loft-tanks', 'brand' => 'Sintex', 'material' => 'LLDPE', 'price' => 2890, 'compare_at_price' => 3300, 'sizes' => ['200 L', '300 L', '500 L'], 'images' => [$img('1579908195863-2a7b4307a8cf')], 'rating' => 4.4, 'review_count' => 380,
                'description' => 'Low-height rectangular loft tank for bathroom and kitchen lofts.', 'specifications' => $spec(['Material' => 'LLDPE', 'Shape' => 'Rectangular, low height', 'Colour' => 'White'])],
            ['slug' => 'underground-tank-2000', 'name' => 'Sintex Underground Water Tank 2000 L', 'category' => 'underground-tanks', 'brand' => 'Sintex', 'material' => 'LLDPE', 'price' => 16900, 'compare_at_price' => 19800, 'sizes' => ['2000 L', '3000 L'], 'images' => [$img('1646488993053-8c182b628696')], 'rating' => 4.3, 'review_count' => 120,
                'description' => 'Rib-reinforced underground sump tank with manhole cover.', 'specifications' => $spec(['Material' => 'LLDPE, reinforced', 'Capacity' => '2000 L', 'Includes' => 'Manhole cover']), 'applications' => "Sumps for houses and villas"],

            // ---- Drainage -------------------------------------------------------------------
            ['slug' => 'prince-swr-pipe-type-b', 'name' => 'Prince SWR Pipe Type B (3 m)', 'category' => 'swr-pipes', 'brand' => 'Prince', 'material' => 'PVC', 'price' => 560, 'compare_at_price' => 640, 'sizes' => ['75 mm', '110 mm', '160 mm'], 'images' => [$pvc[0], $img('1693907986952-3cd372e4c9d8')], 'rating' => 4.5, 'review_count' => 430, 'is_best_seller' => true,
                'description' => 'Soil, waste and rainwater pipe, Type B (heavy), 3 m length, ring-fit socket.',
                'specifications' => $spec(['Material' => 'UPVC', 'Type' => 'B (heavy duty)', 'Length' => '3 m', 'Joint' => 'Ring fit / solvent', 'Standard' => 'IS 13592']),
                'applications' => "Soil and waste stacks\nRainwater downtake pipes"],
            ['slug' => 'swr-door-tee', 'name' => 'Prince SWR Door Tee 110 mm', 'category' => 'swr-pipes', 'brand' => 'Prince', 'material' => 'PVC', 'price' => 245, 'sizes' => ['75 mm', '110 mm'], 'images' => [$fit[1]], 'rating' => 4.3, 'review_count' => 160,
                'description' => 'Single tee with inspection door for SWR stacks.', 'specifications' => $spec(['Material' => 'UPVC', 'Type' => 'Door tee', 'Standard' => 'IS 14735'])],
            ['slug' => 'nahni-trap-floor', 'name' => 'PVC Nahni Trap with SS Grating', 'category' => 'floor-traps', 'brand' => 'Supreme', 'material' => 'PVC + SS', 'price' => 320, 'compare_at_price' => 380, 'sizes' => ['75 mm', '110 mm'], 'images' => [$img('1441802763029-b621005a04a5')], 'rating' => 4.4, 'review_count' => 510,
                'description' => 'Floor trap with water seal and stainless steel grating cover.', 'specifications' => $spec(['Material' => 'PVC body, SS-304 grating', 'Water seal' => '50 mm', 'Outlet' => '75 mm']), 'applications' => "Bathroom and balcony floor drains"],
            ['slug' => 'ss-drain-cover-square', 'name' => 'Stainless Steel Square Drain Cover 5"', 'category' => 'drain-covers', 'brand' => 'Jaquar', 'material' => 'Stainless steel', 'price' => 390, 'compare_at_price' => 460, 'sizes' => ['4 inch', '5 inch', '6 inch'], 'images' => [$img('1685084844860-5d94e6c82939')], 'rating' => 4.5, 'review_count' => 880,
                'description' => 'Anti-cockroach square drain cover with removable grating.', 'specifications' => $spec(['Material' => 'SS-304', 'Shape' => 'Square', 'Feature' => 'Anti-cockroach'])],

            // ---- Tools & hardware -----------------------------------------------------------
            ['slug' => 'taparia-pipe-wrench-14', 'name' => 'Taparia Heavy-duty Pipe Wrench 14"', 'category' => 'wrenches-cutters', 'brand' => 'Taparia', 'material' => 'Drop-forged steel', 'price' => 690, 'compare_at_price' => 820, 'sizes' => ['10 inch', '14 inch', '18 inch'], 'images' => [$img('1503789146722-cf137a3c0fea'), $img('1568868053799-0728ed208ce4')], 'rating' => 4.7, 'review_count' => 1900, 'is_best_seller' => true,
                'description' => 'Stillson-pattern pipe wrench with hardened jaws, made in India.', 'specifications' => $spec(['Material' => 'Drop-forged steel', 'Size' => '14 inch (350 mm)', 'Capacity' => 'Pipes up to 2 inch'])],
            ['slug' => 'pvc-pipe-cutter-42', 'name' => 'Ratchet PVC/CPVC Pipe Cutter (up to 42 mm)', 'category' => 'wrenches-cutters', 'brand' => 'Taparia', 'material' => 'Steel, ABS', 'price' => 549, 'compare_at_price' => 700, 'sizes' => ['42 mm'], 'images' => [$img('1643730169312-7a68bedfc067')], 'rating' => 4.5, 'review_count' => 720,
                'description' => 'Ratcheting cutter for clean, square cuts on PVC and CPVC pipes up to 42 mm.', 'specifications' => $spec(['Capacity' => 'Up to 42 mm OD', 'Blade' => 'SK5 steel, replaceable'])],
            ['slug' => 'astral-cpvc-solvent-cement', 'name' => 'Astral CPVC Solvent Cement 118 ml', 'category' => 'sealants-adhesives', 'brand' => 'Astral', 'material' => 'CPVC cement', 'price' => 165, 'compare_at_price' => 190, 'sizes' => ['59 ml', '118 ml', '237 ml'], 'images' => [$img('1626577822077-2e3ae10e743b')], 'rating' => 4.6, 'review_count' => 2400, 'is_best_seller' => true,
                'description' => 'One-step, medium-bodied CPVC solvent cement for hot & cold water pipes.', 'specifications' => $spec(['Type' => 'One-step CPVC cement', 'Volume' => '118 ml', 'Cure time' => '24 hours before pressure test']), 'installation_notes' => 'Apply to both pipe and fitting; work in a ventilated area and close the tin immediately.'],
            ['slug' => 'ptfe-tape-pack', 'name' => 'PTFE Thread Seal Tape (Pack of 10)', 'category' => 'sealants-adhesives', 'brand' => 'Pidilite', 'material' => 'PTFE', 'price' => 120, 'compare_at_price' => 150, 'sizes' => ['12 mm × 10 m'], 'images' => [$img('1614424428282-b2b1e72c6a4e')], 'rating' => 4.5, 'review_count' => 3100,
                'description' => 'Thread seal tape for leak-proof threaded joints. Pack of 10 rolls.', 'specifications' => $spec(['Material' => 'PTFE', 'Size' => '12 mm × 10 m', 'Pack size' => '10 rolls'])],
            ['slug' => 'm-seal-epoxy', 'name' => 'M-Seal Epoxy Compound 100 g', 'category' => 'sealants-adhesives', 'brand' => 'Pidilite', 'material' => 'Epoxy', 'price' => 85, 'sizes' => ['100 g'], 'images' => [$img('1626577821424-678dc497a300')], 'rating' => 4.6, 'review_count' => 5200, 'is_best_seller' => true,
                'description' => 'Two-part epoxy putty for sealing leaks in pipes, tanks and joints.', 'specifications' => $spec(['Type' => 'Two-part epoxy putty', 'Weight' => '100 g', 'Set time' => '30 minutes'])],
            ['slug' => 'pipe-clamp-ss', 'name' => 'Stainless Steel Pipe Clamp with Rubber (Pack of 20)', 'category' => 'clamps-hangers', 'brand' => 'Supreme', 'material' => 'Stainless steel', 'price' => 340, 'compare_at_price' => 400, 'sizes' => ['1/2 inch', '3/4 inch', '1 inch', '1.5 inch'], 'images' => [$img('1605701249987-f0bb9b505d06')], 'rating' => 4.4, 'review_count' => 280,
                'description' => 'Rubber-lined SS pipe clamps for exposed pipe runs. Pack of 20.', 'specifications' => $spec(['Material' => 'SS-304, EPDM lining', 'Pack size' => '20 pcs'])],
            ['slug' => 'rawl-plug-screw-kit', 'name' => 'Wall Plug & Screw Kit (200 pcs)', 'category' => 'fasteners', 'brand' => 'Taparia', 'material' => 'Nylon, steel', 'price' => 260, 'compare_at_price' => 320, 'sizes' => ['200 pcs'], 'images' => [$img('1613945831677-383c19ad7721'), $img('1641937725629-2adda0f55251')], 'rating' => 4.3, 'review_count' => 460,
                'description' => 'Assorted nylon wall plugs with matching zinc-plated screws in a case.', 'specifications' => $spec(['Contents' => '100 plugs + 100 screws', 'Sizes' => '6, 8, 10 mm'])],
        ];

        $models = [];
        foreach ($products as $i => $data) {
            $categorySlug = $data['category'];
            unset($data['category']);
            $models[$data['slug']] = Product::updateOrCreate(['slug' => $data['slug']], $data + [
                'category_id' => $cat[$categorySlug]->id,
                'sort_order' => $i,
                'sku' => 'PK-'.str_pad((string) ($i + 3001), 4, '0', STR_PAD_LEFT),
                'colors' => [],
                'stock' => 80,
                'is_active' => true,
                'template' => 'plumbing',
            ]);
        }

        $collections = [
            ['slug' => 'bathroom-starter-kit', 'name' => 'Bathroom starter kit', 'description' => 'Everything a new bathroom needs: mixer, shower, health faucet, angle cocks and hoses.', 'products' => ['jaquar-basin-mixer-single-lever', 'jaquar-overhead-shower-200', 'hindware-health-faucet', 'angle-cock-quarter-turn', 'ss-flexible-connection-pipe', 'hindware-wall-hung-basin', 'parryware-one-piece-wc', 'nahni-trap-floor']],
            ['slug' => 'cpvc-hot-water-line', 'name' => 'CPVC hot-water line kit', 'description' => 'Pipe, elbows, tees, brass adapters and cement for a complete hot & cold line.', 'products' => ['astral-cpvc-pipe-sdr11', 'astral-cpvc-elbow-90', 'astral-cpvc-tee', 'astral-cpvc-brass-fta', 'astral-cpvc-solvent-cement', 'pvc-pipe-cutter-42']],
            ['slug' => 'overhead-tank-setup', 'name' => 'Overhead tank setup', 'description' => 'Tank, float valve, ball valves, pump and check valve for a rooftop water system.', 'products' => ['sintex-triple-layer-tank-1000', 'float-valve-brass', 'zoloto-brass-ball-valve', 'kirloskar-self-priming-1hp', 'check-valve-brass', 'supreme-upvc-pipe-sch40']],
            ['slug' => 'plumbers-toolkit', 'name' => 'Plumber’s toolkit', 'description' => 'The tools and consumables every plumber carries.', 'products' => ['taparia-pipe-wrench-14', 'pvc-pipe-cutter-42', 'ptfe-tape-pack', 'm-seal-epoxy', 'pipe-clamp-ss', 'rawl-plug-screw-kit']],
        ];
        foreach ($collections as $i => $data) {
            $slugs = $data['products'];
            unset($data['products']);
            $collection = Collection::updateOrCreate(['slug' => $data['slug']], $data + ['sort_order' => 200 + $i, 'is_active' => true, 'template' => 'plumbing']);
            $sync = [];
            foreach ($slugs as $k => $slug) {
                $sync[$models[$slug]->id] = ['sort_order' => $k];
            }
            $collection->products()->sync($sync);
        }
    }
}
