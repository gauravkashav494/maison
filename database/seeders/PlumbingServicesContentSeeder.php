<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Testimonial;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Demo content for the Plumbing Services template: services, service areas,
 * testimonials, before/after projects, FAQs, pages and blog posts. Everything is
 * tagged with the template id so the other storefronts' content is untouched.
 * Idempotent: services/areas/projects/pages/posts are matched by slug.
 */
class PlumbingServicesContentSeeder extends Seeder
{
    private const T = 'plumbing-services';

    public function run(): void
    {
        $this->services();
        $this->areas();
        $this->testimonials();
        $this->projects();
        $this->faqs();
        $this->pages();
        $this->posts();
    }

    private function img(string $id, int $w = 1200, int $h = 800): string
    {
        return Media::unsplash($id, $w, "&h={$h}");
    }

    private function services(): void
    {
        $rows = [
            ['emergency-plumbing', 'Emergency Plumbing', 'alert', '1526898943670-92bfa9f94c12', '24×7 response for burst pipes, major leaks, overflowing drains and no-water situations.', true, true,
                ['Burst or cracked pipe', 'Water flooding the floor', 'Overflowing drain or toilet', 'Main line leak', 'No water supply at all'],
                ['Priority dispatch, day or night', 'Temporary fix to stop the damage', 'Permanent repair with genuine parts', 'Pressure test before we leave'],
                [['question' => 'How fast can you reach?', 'answer' => 'In most service areas an emergency plumber is at your door within 60–90 minutes. Call us and we will guide you on the phone meanwhile.'], ['question' => 'Are emergency charges higher?', 'answer' => 'Night and holiday visits carry a modest emergency visit charge which we tell you on the call before dispatching.']],
                '<p>A plumbing emergency does not wait for office hours. Our emergency team is on call round the clock for burst pipes, major leakages, overflowing drains and sudden loss of water supply. We stop the damage first, then fix the cause properly.</p><p>While the plumber is on the way, our support team stays on the line to help you shut the main valve, isolate the geyser and contain the water.</p>'],
            ['leak-repair', 'Leak Repair', 'droplet', '1676210134188-4c05dd172f89', 'Leak detection and repair for taps, joints, concealed pipes, ceilings and walls.', false, true,
                ['Dripping tap or shower', 'Damp patch on the wall or ceiling', 'Water under the sink or basin', 'Leak from a concealed pipe', 'Rising water bill with no visible leak'],
                ['Leak detection with pressure testing', 'Joint, washer and seal replacement', 'Concealed pipe repair with minimal breaking', 'Wall/ceiling patch coordination on request'],
                [['question' => 'Can you find a hidden leak without breaking tiles?', 'answer' => 'Usually yes — we pressure-test each line and use moisture readings to narrow down the spot, so we open only the area that needs repair.']],
                '<p>Leaks waste water, damage walls and invite seepage in the flat below. Our plumbers locate the source — a worn washer, a failed joint or a cracked concealed pipe — and repair it with genuine parts, testing the line under pressure before leaving.</p>'],
            ['pipe-repair', 'Pipe Repair', 'pipe', '1607472586893-edb57bdc0e39', 'Repair of cracked, corroded, leaking or noisy GI, CPVC, UPVC and PVC pipes.', false, true,
                ['Cracked or leaking pipe', 'Rusted GI pipe', 'Banging or vibrating pipes', 'Loose or leaking joint', 'Frozen or blocked section'],
                ['Inspection of the affected run', 'Section replacement or joint repair', 'Correct solvent cement and fittings', 'Clamping and support where needed'],
                [],
                '<p>Whether it is an old GI line that has rusted through or a CPVC joint that has started to weep, we repair the affected section with the right material and fittings, and secure the run so the problem does not return.</p>'],
            ['pipe-replacement', 'Pipe Replacement', 'layers', '1545193329-4a052e14eb8f', 'Full or partial replacement of old water-supply and drainage lines with CPVC/UPVC/PVC.', false, false,
                ['Repeated leaks on an old line', 'Rusty or discoloured water', 'Low pressure from clogged GI pipes', 'Renovation of an old house'],
                ['Line survey and material estimate', 'Removal of old pipes', 'New CPVC/UPVC/PVC line with ISI-marked fittings', 'Pressure testing and clean-up'],
                [['question' => 'How long does replacing a bathroom line take?', 'answer' => 'A single bathroom is usually done in one working day; a full house takes two to four days depending on the number of lines and civil work.']],
                '<p>When repairs stop making sense, we replace the line. We survey the run, give you a written estimate for material and labour, and install a new line with ISI-marked pipes and fittings — tested under pressure and cleaned up before handover.</p>'],
            ['drain-cleaning', 'Drain Cleaning', 'drain', '1745265796934-c34e78554872', 'Machine and manual cleaning of slow or blocked bathroom, kitchen and floor drains.', false, true,
                ['Slow-draining sink or basin', 'Water standing in the bathroom', 'Foul smell from the drain', 'Gurgling sounds', 'Repeated blockages'],
                ['Drain rodding or machine cleaning', 'Trap cleaning and refitting', 'Grease and hair removal', 'Flow test after cleaning'],
                [],
                '<p>Hair, grease, soap and scale build up until the drain stops. We clear the blockage with the right tool — rods, augers or a drain machine — clean the trap and test the flow so the line runs freely again.</p>'],
            ['blocked-drain-repair', 'Blocked Drain Repair', 'drain', '1654440122140-f1fc995ddb34', 'Repair of damaged, collapsed or root-blocked drain lines and traps.', false, false,
                ['Blockage that keeps coming back', 'Drain line collapsed or broken', 'Sewage backing up', 'Roots in the outdoor line'],
                ['Line inspection', 'Section repair or replacement', 'Trap and gully replacement', 'Flow test and clean-up'],
                [],
                '<p>When cleaning alone does not hold, the pipe itself is the problem. We inspect the line, repair or replace the damaged section and refit traps and gullies so waste flows the way it should.</p>'],
            ['bathroom-plumbing', 'Bathroom Plumbing', 'bath', '1584622650111-993a426fbf0a', 'Complete bathroom plumbing — new fittings, repairs, leak-proofing and renovations.', false, true,
                ['Leaking shower or mixer', 'Basin or WC leak', 'Poor drainage on the floor', 'New bathroom fit-out', 'Seepage to the flat below'],
                ['Concealed and exposed line work', 'Mixer, shower, basin and WC installation', 'Floor trap and drainage correction', 'Waterproofing coordination'],
                [],
                '<p>From a dripping mixer to a full renovation, our bathroom plumbers handle supply lines, drainage, fittings and fixtures. We work with your tiler and electrician so the bathroom comes together on schedule.</p>'],
            ['kitchen-plumbing', 'Kitchen Plumbing', 'kitchen', '1676210133055-eab6ef033ce3', 'Sink, tap, drain, RO and dishwasher plumbing for modular and traditional kitchens.', false, true,
                ['Leaking sink trap', 'Blocked kitchen drain', 'Low flow at the kitchen tap', 'RO / water purifier connection', 'Dishwasher or washing machine inlet'],
                ['Sink and tap installation or repair', 'Bottle trap and drain line work', 'RO, dishwasher and washing machine connections', 'Leak-proof sealing'],
                [],
                '<p>Kitchen plumbing takes daily abuse — grease, hot water and heavy use. We fix leaking traps, blocked drains and weak flow, and connect purifiers, dishwashers and washing machines properly.</p>'],
            ['tap-faucet-repair', 'Tap & Faucet Repair', 'tap', '1542013936693-884638332954', 'Repair and replacement of dripping, stiff, noisy or broken taps and mixers.', false, true,
                ['Dripping tap', 'Stiff or loose handle', 'Low flow from one tap', 'Broken or corroded spout', 'Leak at the base'],
                ['Cartridge, washer and spindle replacement', 'Aerator cleaning', 'Tap or mixer replacement', 'Angle valve check'],
                [['question' => 'Do I need to buy the tap myself?', 'answer' => 'You can, or our plumber can bring options from trusted brands. You pay for parts only after approving the price.']],
                '<p>A dripping tap wastes thousands of litres a year. Our plumbers replace cartridges, washers and spindles or fit a new tap or mixer — and check the angle valves behind it while they are there.</p>'],
            ['toilet-repair', 'Toilet Repair', 'toilet', '1589824783837-6169889fa20f', 'Flush tank, seat, leak and blockage repairs for all WC types.', false, true,
                ['Flush not working or running continuously', 'Water leaking around the base', 'Blocked WC', 'Loose seat or broken hinge', 'Weak flush'],
                ['Flush valve, float and fill valve replacement', 'Blockage removal', 'Wax ring and base sealing', 'Seat and cover replacement'],
                [],
                '<p>Running flushes, weak flushes, leaks and blockages — we repair every type of WC, from floor-mounted to concealed cisterns, with genuine spares.</p>'],
            ['water-tank-services', 'Water Tank Services', 'tank', '1562185412-99dacaad0c9a', 'Overhead and underground tank cleaning, installation, float valve and overflow fixes.', false, true,
                ['Dirty or smelly tank water', 'Tank overflowing', 'Float valve not closing', 'Leak from the tank outlet', 'New tank installation'],
                ['Tank draining and mechanised cleaning', 'Float valve and ball cock replacement', 'Inlet, outlet and overflow pipe work', 'New tank installation on stand'],
                [['question' => 'How often should a tank be cleaned?', 'answer' => 'Every six months for an overhead tank and at least once a year for an underground sump.']],
                '<p>Clean tanks mean clean water. We drain, scrub, disinfect and refill overhead and underground tanks, replace worn float valves and fix overflow and outlet leaks. New tank installation includes the stand, inlet, outlet and overflow lines.</p>'],
            ['water-pump-services', 'Water Pump Services', 'pump', '1534641614095-6222aed9bdd6', 'Installation, repair and replacement of monoblock, submersible and booster pumps.', false, false,
                ['Pump not lifting water', 'Pump running but low flow', 'Noisy or tripping pump', 'Automatic controller not working', 'New pump installation'],
                ['Pump inspection and priming', 'Foot valve and suction line check', 'Pump repair or replacement', 'Auto-controller and float switch installation'],
                [],
                '<p>No water in the tank usually means a pump or suction problem. We diagnose the pump, foot valve and line, repair or replace what is needed, and can add an automatic controller so the tank fills on its own.</p>'],
            ['geyser-water-heater-plumbing', 'Geyser & Water Heater Plumbing', 'flame', '1676210134190-3f2c0d5cf58d', 'Geyser installation, inlet/outlet connections, pressure valve and leak repair.', false, false,
                ['Geyser leaking', 'No hot water at the tap', 'Pressure release valve dripping', 'New geyser installation', 'Hot water line for a new bathroom'],
                ['Geyser mounting and connection', 'Inlet, outlet and safety valve fitting', 'CPVC hot-water line work', 'Leak repair and testing'],
                [],
                '<p>We install and connect geysers of every brand with the right safety valve and CPVC hot-water lines, and repair leaks and pressure-valve problems on existing units. Electrical connections are handled by a licensed electrician on request.</p>'],
            ['sewer-line-services', 'Sewer Line Services', 'layers', '1697497709686-c433fce09de4', 'Sewer line cleaning, repair and new connections for homes and buildings.', false, false,
                ['Sewage backing up', 'Foul smell around the building', 'Manhole overflowing', 'Broken sewer pipe', 'New sewer connection'],
                ['Sewer line jetting and rodding', 'Chamber and manhole cleaning', 'Pipe repair or replacement', 'New connection to the municipal line'],
                [],
                '<p>Sewer problems affect the whole building. We clear and repair sewer lines, clean chambers and manholes, and lay new connections — with proper gradient and inspection chambers.</p>'],
            ['water-pressure-problems', 'Water Pressure Problems', 'gauge', '1669920282730-ab422e592f97', 'Diagnosis and fixing of low or uneven water pressure across the house.', false, true,
                ['Weak shower on the top floor', 'Pressure drops when two taps run', 'One tap much weaker than others', 'Air in the line'],
                ['Pressure check at every outlet', 'Aerator, valve and line cleaning', 'Booster pump recommendation and installation', 'Tank height and line-size advice'],
                [],
                '<p>Low pressure has many causes — clogged aerators, half-closed valves, scaled pipes or simply not enough tank height. We check each outlet, clear what is blocked and, if needed, install a pressure booster pump.</p>'],
            ['new-construction-plumbing', 'New Construction Plumbing', 'building', '1747192904662-e03e8da1e0ab', 'Complete plumbing for new homes, floors and buildings — design to handover.', false, false,
                ['Plumbing for a new house or floor', 'Concealed line layout', 'Drainage and sewer design', 'Tank and pump sizing'],
                ['Plumbing layout and material list', 'Concealed supply and drainage lines', 'Sanitaryware and fixture installation', 'Testing and handover documentation'],
                [],
                '<p>For new construction we plan the supply and drainage layout, lay concealed lines, install tanks, pumps and sanitaryware, and test everything before handover. We coordinate with your contractor and site engineer throughout.</p>'],
            ['commercial-plumbing', 'Commercial Plumbing', 'building', '1639600993675-2281b2c939f0', 'Plumbing for shops, offices, restaurants, hospitals, hotels and housing societies.', false, false,
                ['Repairs across multiple floors', 'Restaurant kitchen drainage', 'Society tank and pump issues', 'Annual maintenance requirement'],
                ['Site survey and scheduled visits', 'Repairs with minimal downtime', 'Society-level tank, pump and line work', 'Maintenance contracts'],
                [],
                '<p>Businesses and housing societies need plumbers who show up on schedule and work around operating hours. We handle repairs, upgrades and annual maintenance contracts for commercial premises and societies.</p>'],
            ['plumbing-maintenance', 'Plumbing Maintenance', 'clipboard', '1620653713380-7a34b773fef8', 'Scheduled inspections and preventive maintenance for homes and societies.', false, false,
                ['Recurring small leaks', 'Ageing pipes and fittings', 'Society-level upkeep', 'Peace of mind before monsoon'],
                ['Full-house plumbing inspection', 'Tank cleaning and valve checks', 'Minor repairs on the spot', 'Written report with recommendations'],
                [],
                '<p>A quarterly or half-yearly inspection catches small problems before they become emergencies. We check every line, tap, valve, tank and pump, fix minor issues on the spot and give you a written report.</p>'],
            ['sanitary-fitting-installation', 'Sanitary Fitting Installation', 'wrench', '1617850687405-a18454436d77', 'Installation of basins, WCs, showers, mixers, health faucets and accessories.', false, false,
                ['New basin, WC or shower', 'Replacing old fittings', 'Health faucet or jet spray', 'Bathroom accessories'],
                ['Removal of old fittings', 'Installation with correct sealing', 'Angle valves and connectors', 'Leak test and clean-up'],
                [],
                '<p>Bought new sanitaryware? We install basins, WCs, showers, mixers, health faucets and accessories with the right connectors and sealing, and test every joint before we leave.</p>'],
        ];

        foreach ($rows as $i => [$slug, $name, $icon, $img, $excerpt, $emergency, $popular, $problems, $included, $faqs, $body]) {
            Service::updateOrCreate(['slug' => $slug, 'template' => self::T], [
                'name' => $name,
                'icon' => $icon,
                'image' => $this->img($img),
                'excerpt' => $excerpt,
                'description' => $body,
                'problems' => $problems,
                'included' => $included,
                'faqs' => $faqs,
                'is_emergency' => $emergency,
                'is_popular' => $popular,
                'is_active' => true,
                'sort_order' => $i,
                'meta_description' => $excerpt,
            ]);
        }
    }

    private function areas(): void
    {
        $rows = [
            ['ludhiana', 'Ludhiana', 'Punjab', '1632201147654-f6f54427e538', '60–90 min', ['Model Town', 'Sarabha Nagar', 'BRS Nagar', 'Civil Lines', 'Dugri', 'Pakhowal Road', 'Ferozepur Road', 'Haibowal', 'Jamalpur', 'Focal Point']],
            ['chandigarh', 'Chandigarh', 'Chandigarh', '1630061712710-2539eb457c55', '60–90 min', ['Sector 15', 'Sector 22', 'Sector 35', 'Sector 44', 'Manimajra', 'Industrial Area', 'Sector 8', 'Sector 49']],
            ['mohali', 'Mohali', 'Punjab', '1689574666551-52eb11c37bcd', '60–90 min', ['Phase 3B2', 'Phase 7', 'Sector 70', 'Sector 79', 'Kharar', 'Airport Road', 'Sunny Enclave']],
            ['jalandhar', 'Jalandhar', 'Punjab', '1632398535774-b95738ddff68', '90–120 min', ['Model Town', 'Urban Estate', 'Rama Mandi', 'Nakodar Road', 'Cantt', 'GT Road']],
            ['amritsar', 'Amritsar', 'Punjab', '1577199001468-44c049e7603f', '90–120 min', ['Ranjit Avenue', 'Green Avenue', 'Majitha Road', 'Lawrence Road', 'Batala Road']],
            ['patiala', 'Patiala', 'Punjab', '1630309904321-4f320230bced', '90–120 min', ['Urban Estate', 'Leela Bhawan', 'Tripuri', 'Sirhind Road', 'Rajpura Road']],
            ['panchkula', 'Panchkula', 'Haryana', '1632398461363-741a64493ab6', '60–90 min', ['Sector 4', 'Sector 12', 'Sector 20', 'MDC', 'Pinjore']],
            ['zirakpur', 'Zirakpur', 'Punjab', '1646009445351-b8192e095f3a', '60–90 min', ['VIP Road', 'Patiala Road', 'Dhakoli', 'Peer Muchalla', 'Baltana']],
        ];

        foreach ($rows as $i => [$slug, $name, $state, $img, $response, $localities]) {
            ServiceArea::updateOrCreate(['slug' => $slug, 'template' => self::T], [
                'name' => $name,
                'state' => $state,
                'image' => $this->img($img, 1200, 800),
                'excerpt' => "Same-day and emergency plumbers across {$name} — leak repair, pipes, drains, bathroom and kitchen plumbing, tanks and pumps.",
                'description' => "<p>Our {$name} team covers ".implode(', ', array_slice($localities, 0, -1)).' and '.end($localities).". Book online, call or WhatsApp and a verified plumber reaches you in about {$response} for emergencies, or at the slot you choose for scheduled work.</p><p>All jobs come with transparent pricing approved before work starts, genuine parts and our 30-day work warranty.</p>",
                'localities' => $localities,
                'response_time' => $response,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    private function testimonials(): void
    {
        Testimonial::where('template', self::T)->delete();
        $svc = fn (string $slug) => Service::where('template', self::T)->where('slug', $slug)->value('id');

        $rows = [
            ['Harpreet Kaur', 5, 'leak-repair', 'Ludhiana', 'Water was seeping into the flat below and two plumbers could not find it. PipeCare found the concealed leak in an hour and opened only two tiles. Very professional.'],
            ['Rajesh Verma', 5, 'emergency-plumbing', 'Chandigarh', 'Pipe burst at 11 pm. The plumber was at my door in 50 minutes, stopped the flooding and came back next morning for the proper repair. Lifesaver.'],
            ['Simran Gill', 5, 'bathroom-plumbing', 'Mohali', 'Got both bathrooms redone. The estimate was clear, the work was clean and they coordinated with my tile guy. Zero leaks after six months.'],
            ['Amit Bansal', 4, 'drain-cleaning', 'Ludhiana', 'Kitchen drain was blocked every month. They cleaned it with a machine and explained what to avoid. Has not blocked since.'],
            ['Neha Sharma', 5, 'tap-faucet-repair', 'Panchkula', 'Booked online for a dripping mixer. Plumber came on time, replaced the cartridge and even cleaned the aerators of the other taps.'],
            ['Gurpreet Singh', 5, 'water-tank-services', 'Jalandhar', 'Tank cleaning done properly — drained, scrubbed, disinfected. They also replaced the float valve that was causing the overflow.'],
            ['Pooja Mehta', 5, 'toilet-repair', 'Zirakpur', 'Flush was running all night. Fixed in 30 minutes with a genuine part and the charge was exactly what they quoted on the phone.'],
            ['Vikram Chaudhary', 4, 'water-pump-services', 'Patiala', 'Pump was not lifting water. They found the foot valve was gone, replaced it and installed an auto-controller. No more running to the terrace.'],
            ['Manpreet Dhillon', 5, 'commercial-plumbing', 'Amritsar', 'We use PipeCare for our restaurant. They come after closing hours and the kitchen drain problems are gone. Reliable team.'],
            ['Sunita Rani', 5, 'geyser-water-heater-plumbing', 'Chandigarh', 'New geyser installed with proper safety valve and CPVC line. Neat work and they took away the old unit.'],
        ];

        foreach ($rows as $i => [$name, $rating, $slug, $location, $body]) {
            Testimonial::create([
                'template' => self::T,
                'name' => $name,
                'rating' => $rating,
                'service_id' => $svc($slug),
                'location' => $location,
                'body' => $body,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    private function projects(): void
    {
        $svc = fn (string $slug) => Service::where('template', self::T)->where('slug', $slug)->value('id');

        $rows = [
            ['bathroom-tap-replacement-model-town', 'Bathroom tap replacement', 'tap-faucet-repair', 'Model Town, Ludhiana', '1680008702835-d79e376a5f79', '1610278764397-388d11c35ddb', 'A corroded 12-year-old tap that leaked at the base, replaced with a new quarter-turn tap, fresh angle valve and connector. Done in 40 minutes.'],
            ['toilet-repair-sector-70', 'Toilet flush & base repair', 'toilet-repair', 'Sector 70, Mohali', '1563204719-44395a035bb6', '1587527901949-ab0341697c1e', 'Continuously running flush and a leak at the WC base. Replaced the fill valve and flush valve, re-seated the WC with a new wax ring.'],
            ['drain-repair-manimajra', 'Blocked drain line repair', 'blocked-drain-repair', 'Manimajra, Chandigarh', '1668961915523-884872e392f8', '1741997376539-a63ee600dac9', 'Outdoor drain line collapsed and kept backing up. Replaced the damaged section with the correct gradient and a new inspection chamber.'],
            ['pipe-replacement-urban-estate', 'GI to CPVC pipe replacement', 'pipe-replacement', 'Urban Estate, Jalandhar', '1606340671662-27ee685dd111', '1694827893591-af9b80361599', 'Rusted GI supply lines causing brown water and weak flow. Replaced the full ground-floor run with CPVC in two days.'],
            ['tank-installation-tripuri', 'Overhead tank installation', 'water-tank-services', 'Tripuri, Patiala', '1646488993053-8c182b628696', '1497990136619-d0c2de2b5b4b', 'Old cracked tank replaced with a new 1000 L three-layer tank on a raised stand, with new inlet, outlet, overflow and float valve.'],
            ['kitchen-plumbing-ranjit-avenue', 'Kitchen sink & RO plumbing', 'kitchen-plumbing', 'Ranjit Avenue, Amritsar', '1618840626133-54463084a141', '1645343804676-c5edd2272f9d', 'Leaking bottle trap and no proper RO connection. Fitted a new sink drain assembly, bottle trap and a dedicated RO inlet with a shut-off valve.'],
        ];

        foreach ($rows as $i => [$slug, $title, $service, $location, $before, $after, $desc]) {
            Project::updateOrCreate(['slug' => $slug, 'template' => self::T], [
                'title' => $title,
                'service_id' => $svc($service),
                'location' => $location,
                'before_image' => $this->img($before, 1000, 750),
                'after_image' => $this->img($after, 1000, 750),
                'description' => $desc,
                'completed_on' => now()->subDays(7 + $i * 11),
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    private function faqs(): void
    {
        Faq::where('template', self::T)->delete();
        $rows = [
            ['Services', 'How quickly can a plumber arrive?', 'For emergencies, usually within 60–90 minutes in our main service areas. For scheduled work you choose the day and a morning, afternoon or evening slot, and we confirm the time on call.'],
            ['Services', 'Do you provide emergency plumbing?', 'Yes — 24×7, including Sundays and holidays, for burst pipes, major leaks, overflowing drains and no-water situations. Call the emergency number or use “Get emergency help”.'],
            ['Services', 'Which areas do you service?', 'Ludhiana, Chandigarh, Mohali, Panchkula, Zirakpur, Jalandhar, Amritsar and Patiala, with the localities listed on each service-area page. Not sure? Call us and we will check.'],
            ['Booking', 'Do you provide estimates before starting work?', 'Always. The plumber inspects the problem, tells you the cost of labour and parts, and starts only after you approve. For bigger jobs we send a written estimate first.'],
            ['Booking', 'Can I book a plumber online?', 'Yes. Use “Book a plumber”, pick the service, describe the problem, choose a date and slot, and share your contact details. You get a reference number and a confirmation call.'],
            ['Booking', 'How do I know the plumber is genuine?', 'Every plumber is background-verified and trained by us. You receive the plumber’s name before the visit and they carry a PipeCare ID card.'],
            ['Services', 'Do you handle commercial plumbing?', 'Yes — shops, restaurants, offices, hospitals, hotels and housing societies, including annual maintenance contracts and after-hours visits.'],
            ['Services', 'Do you provide maintenance services?', 'Yes. Our plumbing maintenance service covers a full inspection of lines, taps, valves, tanks and pumps with minor repairs done on the spot and a written report.'],
            ['Booking', 'Is there a warranty on the work?', 'Every job carries a 30-day work warranty. If the same problem returns within 30 days, we fix it free of charge.'],
            ['Booking', 'What payment methods do you accept?', 'UPI, cards and cash after the work is done. A GST invoice is provided on every job.'],
        ];
        foreach ($rows as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'is_active' => true, 'template' => self::T]);
        }
    }

    private function pages(): void
    {
        $img = fn (string $id, int $w = 1600, int $h = 900) => $this->img($id, $w, $h);

        $pages = [
            'about' => [
                'title' => 'Professional plumbers your neighbours already trust.',
                'eyebrow' => 'About PipeCare',
                'template' => 'about',
                'excerpt' => 'PipeCare started in 2014 with two plumbers and one promise: show up on time, explain the cost first and fix it properly. Today we are a team of 60 verified plumbers serving homes and businesses across Punjab and Chandigarh Tricity.',
                'image' => $img('1568868053799-0728ed208ce4', 2000, 900),
                'meta_title' => 'About PipeCare — professional plumbing services in Punjab',
                'meta_description' => 'Who we are, how our plumbers are trained and verified, and why thousands of families in Ludhiana, Chandigarh and Mohali trust PipeCare.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'Our plumbers', 'heading' => 'Verified, trained and accountable', 'body' => '<p>Every PipeCare plumber is background-verified, trained in our workshop on CPVC, UPVC, GI and sanitaryware work, and carries an ID card. You get the plumber’s name before the visit and a follow-up call after it.</p>', 'image' => $img('1529836349180-223cd77d8cb6', 1200, 900)],
                        ['layout' => 'image-text', 'eyebrow' => 'Quality commitment', 'heading' => 'Genuine parts, tested work, 30-day warranty', 'body' => '<p>We use ISI-marked pipes and fittings and genuine spares from trusted brands. Every repair is pressure-tested before we leave, and if the same problem returns within 30 days we fix it free.</p>', 'image' => $img('1503788943072-cd614c3056cf', 1200, 900)],
                        ['layout' => 'text-image', 'eyebrow' => 'Our approach', 'heading' => 'Estimate first, then the work', 'body' => '<p>No surprises. The plumber inspects, explains what is wrong, and quotes labour and parts before starting. For bigger jobs — pipe replacement, new bathrooms, society tanks — you get a written estimate.</p>', 'image' => $img('1621905252472-943afaa20e20', 1200, 900)],
                    ],
                    'values' => [
                        ['title' => 'On time', 'text' => 'Slots are confirmed on call and we tell you if we are running late.'],
                        ['title' => 'Transparent', 'text' => 'You approve the price before work begins. GST invoice on every job.'],
                        ['title' => 'Clean work', 'text' => 'We protect the area, clean up and take away old parts.'],
                        ['title' => 'Local', 'text' => 'Teams based in each city, so response times stay short.'],
                    ],
                    'sustainability_heading' => 'Every drop counts.',
                    'sustainability_body' => '<p>A single dripping tap wastes over 5,000 litres a year. Fixing leaks fast, fitting water-efficient fixtures and cleaning tanks properly is our small part in saving water.</p>',
                    'sustainability_image' => $img('1669920282730-ab422e592f97', 1200, 900),
                    'founder_quote' => 'A plumber who explains the problem and the price before touching a pipe — that is the company we wanted to build.',
                    'founder_name' => 'Jaspreet Singh, founder',
                ],
            ],
            'contact' => [
                'title' => 'Talk to us',
                'eyebrow' => 'Contact',
                'template' => 'contact',
                'excerpt' => 'Call, WhatsApp or send a message — we reply within business hours and pick up emergencies round the clock.',
                'meta_description' => 'Contact PipeCare Plumbing Services by phone, WhatsApp, email or the form. Emergency line open 24×7.',
                'image' => $img('1564483335100-3413b45dbd37', 1600, 900),
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'eyebrow' => 'Help',
                'template' => 'faq',
                'excerpt' => 'Response times, service areas, estimates, warranty and payments.',
            ],
            'privacy' => [
                'title' => 'Privacy policy',
                'template' => 'legal',
                'excerpt' => 'What we collect when you book a plumber, and how we use it.',
                'body' => '<h3>What we collect</h3><p>Your name, phone number, address and problem description when you book, request a quote or contact us; your email if you share it.</p><h3>How we use it</h3><p>To dispatch and confirm your service visit, send updates about your booking and, if you opt in, tips and offers. We do not sell your data.</p><h3>Who can see it</h3><p>Our dispatch team and the plumber assigned to your job. Payment details are processed by the payment provider and never stored by us.</p><h3>Your choices</h3><p>Write to us to view, correct or delete your data.</p>',
            ],
            'terms' => [
                'title' => 'Terms of service',
                'template' => 'legal',
                'excerpt' => 'Bookings, estimates, cancellations and warranty.',
                'body' => '<h3>Bookings</h3><p>A booking is a request for a visit; the time is confirmed on call. Emergency visits may carry a visit charge which is told to you before dispatch.</p><h3>Estimates</h3><p>Work starts only after you approve the estimate. Additional work found during the job is quoted separately before it is done.</p><h3>Cancellation</h3><p>Cancel free of charge any time before the plumber is dispatched.</p><h3>Warranty</h3><p>Every job carries a 30-day work warranty as described on the warranty page. Parts carry the manufacturer warranty.</p>',
            ],
            'warranty' => [
                'title' => 'Service warranty',
                'template' => 'legal',
                'excerpt' => 'Our 30-day work warranty, in plain language.',
                'body' => '<h3>What is covered</h3><p>If the same problem we repaired returns within 30 days of the visit, we fix it again free of charge — labour and any part we supplied.</p><h3>What is not covered</h3><p>New problems, damage caused by others, parts supplied by the customer, and failures caused by water hammer, freezing or civil work after our visit.</p><h3>How to claim</h3><p>Call or WhatsApp with your booking reference. We schedule a revisit within 48 hours.</p>',
            ],
            'careers' => [
                'title' => 'Join the PipeCare team.',
                'eyebrow' => 'Careers',
                'template' => 'careers',
                'excerpt' => 'Plumbers, supervisors, dispatch and customer-support roles across Punjab and Chandigarh Tricity.',
                'data' => ['roles' => [
                    ['title' => 'Plumber (residential)', 'location' => 'Ludhiana · Chandigarh · Mohali', 'type' => 'Full-time'],
                    ['title' => 'Senior plumber / site supervisor', 'location' => 'Ludhiana', 'type' => 'Full-time'],
                    ['title' => 'Dispatch & customer support executive', 'location' => 'Ludhiana (office)', 'type' => 'Full-time'],
                    ['title' => 'Apprentice plumber', 'location' => 'All cities', 'type' => 'Trainee'],
                ], 'perks' => ['Fixed salary plus per-job incentives', 'Tools, uniform and ID provided', 'Hands-on training in our workshop', 'Health insurance for you and your family']],
            ],
        ];

        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug, 'storefront_template' => self::T], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }
    }

    private function posts(): void
    {
        $img = fn (string $id) => $this->img($id, 1600, 900);
        $posts = [
            ['slug' => 'how-to-identify-a-water-leak', 'title' => 'How to identify a water leak at home', 'category' => 'Leaks', 'excerpt' => 'Damp patches, a spinning meter and a rising bill — the signs of a hidden leak and how to narrow it down before the plumber arrives.', 'image' => $img('1542855368-ca6ea825bca2'), 'read_time' => 4, 'is_featured' => true, 'body' => '<p>Close every tap and check the water meter. If it still moves, water is going somewhere. Then look for damp patches on walls and ceilings, a musty smell in cupboards under sinks, and taps or flushes that never quite stop. Note where the damp is worst — that is usually directly below or beside the leak — and share it with the plumber when you book.</p>'],
            ['slug' => 'what-causes-low-water-pressure', 'title' => 'What causes low water pressure?', 'category' => 'Pressure', 'excerpt' => 'Clogged aerators, half-closed valves, scaled pipes or a tank that is simply too low — the usual suspects and what fixes each.', 'image' => $img('1517646287270-a5a9ca602e5c'), 'read_time' => 5, 'body' => '<p>If only one tap is weak, unscrew the aerator and rinse it — that fixes most cases. If the whole house is weak, check the main and angle valves are fully open. Old GI pipes scale up inside and choke the flow; the fix is replacement. And if your tank is barely above the top-floor shower, a small booster pump makes a big difference.</p>'],
            ['slug' => 'when-should-you-replace-a-pipe', 'title' => 'When should you replace a pipe instead of repairing it?', 'category' => 'Pipes', 'excerpt' => 'Three repairs on the same line in a year, brown water or a GI pipe older than 15 years — replacement is usually cheaper in the long run.', 'image' => $img('1601507622731-4abab94ebeb8'), 'read_time' => 4, 'body' => '<p>Repairs make sense for isolated joints and accidental damage. Replacement makes sense when leaks keep coming back along the same run, when water is rusty or discoloured, or when the line is GI and more than 12–15 years old. Modern CPVC and UPVC lines cost less than repeated repairs and civil work.</p>'],
            ['slug' => 'how-to-prevent-blocked-drains', 'title' => 'How to prevent blocked drains', 'category' => 'Drains', 'excerpt' => 'Grease, hair and wet wipes cause most blockages. A few habits keep your drains running for years.', 'image' => $img('1697652973421-0d688f661d89'), 'read_time' => 3, 'body' => '<p>Never pour cooking oil or ghee down the sink — wipe pans with paper first. Use a hair catcher in the bathroom drain. Nothing but toilet paper goes in the WC; wipes and sanitary products block sewer lines. Once a month, pour hot water down the kitchen drain to move soft grease along.</p>'],
            ['slug' => 'bathroom-plumbing-maintenance-tips', 'title' => 'Bathroom plumbing maintenance tips', 'category' => 'Maintenance', 'excerpt' => 'Ten minutes a month keeps taps, flushes and drains in shape and catches leaks before they reach the flat below.', 'image' => $img('1629079447777-1e605162dc8d'), 'read_time' => 4, 'body' => '<p>Check under the basin for damp, run the flush and listen for it stopping, clean the shower head and tap aerators, and look at the silicone around the basin and WC base. Any drip, gurgle or crack is worth a call before monsoon.</p>'],
            ['slug' => 'signs-you-need-a-professional-plumber', 'title' => 'Signs you need a professional plumber', 'category' => 'Guides', 'excerpt' => 'Some jobs are DIY. These are not — and trying can turn a small problem into a flooded floor.', 'image' => $img('1503789146722-cf137a3c0fea'), 'read_time' => 3, 'body' => '<p>Call a professional for concealed leaks, anything involving the main line or the sewer, geyser connections, repeated blockages, and any job that needs breaking tiles or cutting pipes. A washer or aerator is DIY; a joint behind a wall is not.</p>'],
        ];
        foreach ($posts as $i => $post) {
            Post::updateOrCreate(['slug' => $post['slug'], 'template' => self::T], $post + ['published_at' => now()->subDays(3 + $i * 6)]);
        }
    }
}
