<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Collection as MemoryCollection;
use App\Models\Entity;
use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Models\MemoryRelationship;
use App\Models\ProcessingJob;
use App\Models\Reminder;
use App\Models\SearchHistory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private User $user;

    /** @var array<string, MemoryCollection> */
    private array $collections = [];

    /** @var array<string, Tag> */
    private array $tags = [];

    /** @var array<string, Entity> */
    private array $entities = [];

    public function run(): void
    {
        $this->user = User::updateOrCreate(
            ['email' => 'demo@realitylog.app'],
            [
                'name' => 'Ayesha Rahman',
                'password' => 'password',
                'email_verified_at' => now()->subYear(),
            ]
        );

        $this->user->settings()->updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'interface_locale' => 'en',
                'ai_locale' => 'en',
                'timezone' => 'Asia/Dhaka',
                'country' => 'BD',
                'currency' => 'BDT',
                'date_format' => 'd M Y',
                'theme' => 'light',
                'notifications_enabled' => true,
                'email_reminders' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'external_ai_consent' => false,
            ]
        );

        $this->clearDemoData();

        DB::transaction(function (): void {
            $this->seedCollections();
            $this->seedMemoriesAndTags();
            $memories = $this->seedMemories();

            $this->seedRelationships($memories);
            $this->seedReminders($memories);
            $this->seedSearchHistory();
            $this->seedAuditTrail();
        });
    }

    private function clearDemoData(): void
    {
        $this->user->memories()->delete();
        $this->user->reminders()->delete();
        $this->user->collections()->delete();
        $this->user->tags()->delete();
        $this->user->entities()->delete();
        SearchHistory::where('user_id', $this->user->id)->delete();
        AuditLog::where('user_id', $this->user->id)->delete();
    }

    private function seedCollections(): void
    {
        $definitions = [
            'devices' => ['Devices & Warranties', '#517A64', 'Phones, laptops and the appliances that keep life running.'],
            'home' => ['Home & Documents', '#D95F3B', 'Rent, bills, insurance and the paperwork of everyday life.'],
            'trips' => ['Trips & Places', '#526A8A', 'Journeys, day trips and places worth returning to.'],
            'people' => ['People & Moments', '#C08A3E', 'Conversations, family and the small things worth keeping.'],
            'work' => ['Work & Ideas', '#8B5D6F', 'Meetings, decisions, books and half-formed plans.'],
        ];

        foreach ($definitions as $key => [$name, $color, $description]) {
            $this->collections[$key] = MemoryCollection::create([
                'user_id' => $this->user->id,
                'name' => $name,
                'color' => $color,
                'description' => $description,
            ]);
        }
    }

    private function seedMemoriesAndTags(): void
    {
        $tagNames = [
            'phone', 'receipt', 'warranty', 'dhaka', 'family', 'travel', 'work', 'health',
            'home', 'subscription', 'bangla', 'idea', 'laptop', 'repair', 'food', 'rent',
            'insurance', 'meeting', 'book', 'shopping', 'fridge', 'internet', 'bike',
        ];

        foreach ($tagNames as $name) {
            $this->tags[$name] = Tag::create([
                'user_id' => $this->user->id,
                'name' => $name,
                'color' => $this->tagColor($name),
            ]);
        }
    }

    /**
     * @return array<string, Memory>
     */
    private function seedMemories(): array
    {
        $iphoneReceipt = $this->demoImage(
            'iphone-receipt',
            'iPhone 15 purchase receipt',
            'Star Tech · Dhanmondi · 12 September 2026',
            '#D95F3B'
        );

        $internetBill = $this->demoImage(
            'internet-bill',
            'Link3 home internet bill',
            'October 2026 · BDT 1,200',
            '#526A8A'
        );

        $padmaTrip = $this->demoImage(
            'padma-bridge',
            'Padma Bridge day trip',
            'Family · 24 January 2026',
            '#C08A3E'
        );

        $sylhetTrip = $this->demoImage(
            'sylhet-trip',
            'Eid trip to Sylhet',
            'Tea gardens and rain · April 2026',
            '#4C6956'
        );

        $bikeService = $this->demoImage(
            'bike-service',
            'Bike servicing receipt',
            'Rahman Motors · July 2026',
            '#8B5D6F'
        );

        $recipeScan = $this->demoImage(
            'recipe-scan',
            'Handwritten recipe scan',
            'Family notebook · needs review',
            '#B64B43'
        );

        $rentPdf = $this->demoPdf('rent-agreement-2026.pdf', 'Flat Rent Agreement Renewal 2026', [
            'Flat 5B, Road 12, Bashundhara R/A, Dhaka',
            'Landlord: Mr. Karim Ahmed',
            'Monthly rent: BDT 28,000',
            'Renewed: 1 August 2026',
            'Valid until: 31 July 2027',
            '',
            'This is a fictional demo document for the RealityLog sample archive.',
        ]);

        $healthPdf = $this->demoPdf('health-check-aug-2026.pdf', 'Annual Health Check Report', [
            'Patient: Ayesha Rahman',
            'Provider: Labaid Diagnostics, Dhanmondi',
            'Date: 18 August 2026',
            'Total paid: BDT 3,200',
            '',
            'Blood panel, ECG and physician consultation completed.',
            'All results within normal range except vitamin D, which is low.',
            '',
            'This is a fictional demo document for the RealityLog sample archive.',
        ]);

        $insurancePdf = $this->demoPdf('insurance-policy-2026.pdf', 'Insurance Policy Renewal 2026', [
            'Policy holder: Ayesha Rahman',
            'Provider: MetLife',
            'Annual premium: BDT 18,500',
            'Renewed: 2 June 2026',
            'Coverage until: 31 May 2027',
            '',
            'This is a fictional demo document for the RealityLog sample archive.',
        ]);

        $memories = [];

        $memories['iphone'] = $this->createMemory([
            'title' => 'iPhone 15 purchase receipt',
            'type' => 'receipt',
            'source' => 'image',
            'language' => 'en',
            'event' => '2026-09-12',
            'captured' => '2026-09-12 18:24:00',
            'summary' => 'Bought a blue iPhone 15 (128 GB) from Star Tech, Dhanmondi for BDT 132,999. Warranty runs until 12 September 2027.',
            'body' => "iPhone 15 · 128 GB · Blue\nStar Tech, Dhanmondi, Dhaka\nOrder ST-88214\n12 September 2026\n\nSubtotal: BDT 124,299\nVAT: BDT 8,700\nTotal paid: BDT 132,999\n\nWarranty valid until 12 September 2027.",
            'extracted' => "STAR TECH\nDhanmondi, Dhaka\nInvoice ST-88214\niPhone 15 128GB Blue\nTotal BDT 132,999\nWarranty until 12 September 2027",
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['iphone', 'receipt', 'star tech', 'warranty'],
                'BDT 132,999',
                'Star Tech',
                '2027-09-12',
                0.94
            ),
            'collection' => 'devices',
            'tags' => ['phone', 'receipt', 'warranty', 'dhaka'],
            'entities' => ['organization' => ['Star Tech'], 'place' => ['Dhanmondi'], 'item' => ['iPhone']],
            'favorite' => true,
            'attachments' => [[
                'disk' => 'public',
                'path' => $iphoneReceipt,
                'name' => 'iphone-15-receipt.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'stored',
            ]],
        ]);

        $memories['fridge'] = $this->createMemory([
            'title' => 'Walton fridge repair',
            'type' => 'repair',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2025-11-15',
            'captured' => '2025-11-15 11:40:00',
            'summary' => 'The Walton refrigerator compressor was repaired at the Mirpur service centre for BDT 4,500 with a six-month warranty.',
            'body' => 'গত বছর ওয়ালটন ফ্রিজের কম্প্রেসার মেরামত করিয়েছি। মিরপুর সার্ভিস সেন্টার থেকে ৪,৫০০ টাকা দিয়ে সারাই করানো হয়েছে। ছয় মাসের ওয়ারেন্টি দেওয়া হয়েছিল। এখন ফ্রিজটি ঠিকভাবে চলছে।',
            'extracted' => 'Walton refrigerator compressor repair. Service centre: Mirpur. Cost BDT 4,500. Warranty: six months. The appliance is working normally now.',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['walton', 'fridge', 'repair', 'mirpur'],
                'BDT 4,500',
                'Walton',
                '2026-05-15',
                0.89
            ),
            'collection' => 'devices',
            'tags' => ['fridge', 'repair', 'warranty', 'home'],
            'entities' => ['organization' => ['Walton'], 'place' => ['Mirpur'], 'item' => ['Fridge']],
        ]);

        $memories['rent'] = $this->createMemory([
            'title' => 'Flat rent agreement renewal',
            'type' => 'document',
            'source' => 'document',
            'language' => 'en',
            'event' => '2026-08-01',
            'captured' => '2026-08-01 09:15:00',
            'summary' => 'Renewed the Bashundhara flat rent agreement for one year at BDT 28,000 per month, valid until 31 July 2027.',
            'body' => 'Flat 5B, Road 12, Bashundhara R/A, Dhaka. Landlord Mr. Karim Ahmed. Monthly rent BDT 28,000. The agreement was renewed on 1 August 2026 and runs until 31 July 2027.',
            'extracted' => 'Flat 5B, Road 12, Bashundhara R/A, Dhaka. Landlord: Mr. Karim Ahmed. Monthly rent BDT 28,000. Renewed 1 August 2026. Valid until 31 July 2027.',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['rent', 'agreement', 'bashundhara', 'dhaka'],
                'BDT 28,000',
                null,
                '2027-07-31',
                0.91
            ),
            'collection' => 'home',
            'tags' => ['home', 'rent', 'dhaka', 'warranty'],
            'entities' => ['place' => ['Bashundhara', 'Dhaka'], 'person' => ['Karim Ahmed']],
            'attachments' => [[
                'disk' => 'local',
                'path' => $rentPdf['path'],
                'name' => 'flat-rent-agreement-2026.pdf',
                'mime' => 'application/pdf',
                'kind' => 'document',
                'status' => 'extracted',
                'size' => $rentPdf['size'],
                'extracted' => 'Flat 5B, Road 12, Bashundhara R/A, Dhaka. Monthly rent BDT 28,000. Valid until 31 July 2027.',
            ]],
        ]);

        $memories['padma'] = $this->createMemory([
            'title' => 'Padma Bridge day trip',
            'type' => 'travel',
            'source' => 'image',
            'language' => 'bn',
            'event' => '2026-01-24',
            'captured' => '2026-01-24 20:05:00',
            'summary' => 'A one-day family trip across the Padma Bridge, with lunch in Jashore and tea on the way back.',
            'body' => 'পদ্মা সেতু হয়ে পরিবারের সাথে একদিনের ট্রিপ। সকালে ঢাকা থেকে রওনা দিয়ে সেতুতে ছবি তুলেছি, দুপুরে যশোরে খেলাম, ফেরার পথে চায়ের দোকানে থেমেছি। মায়ের খুব ভালো লেগেছে।',
            'extracted' => 'Family day trip across the Padma Bridge. Started from Dhaka in the morning, stopped for photos on the bridge, had lunch in Jashore and tea on the way back.',
            'status' => 'ready',
            'metadata' => $this->metadata(['padma', 'bridge', 'family', 'trip'], null, null, null, 0.84),
            'collection' => 'trips',
            'tags' => ['travel', 'family', 'bangla', 'dhaka'],
            'entities' => ['place' => ['Padma Bridge', 'Jashore', 'Dhaka']],
            'favorite' => true,
            'attachments' => [[
                'disk' => 'public',
                'path' => $padmaTrip,
                'name' => 'padma-bridge-day-trip.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'stored',
            ]],
        ]);

        $memories['coffee'] = $this->createMemory([
            'title' => 'Coffee with Nusrat',
            'type' => 'person',
            'source' => 'text',
            'language' => 'en',
            'event' => '2026-09-30',
            'captured' => '2026-09-30 19:10:00',
            'summary' => 'Coffee at North End in Gulshan 2 with Nusrat. She started a new job and we planned to exchange books next month.',
            'body' => 'Met Nusrat at North End, Gulshan 2. She has joined a new design studio and seems happy. We talked about her move, her family, and a book exchange we want to do next month. She recommended a novel about the Padma river.',
            'extracted' => 'Coffee with Nusrat at North End, Gulshan 2. New job at a design studio. Planned a book exchange next month.',
            'status' => 'ready',
            'metadata' => $this->metadata(['nusrat', 'coffee', 'gulshan', 'book'], null, null, null, 0.82),
            'collection' => 'people',
            'tags' => ['meeting', 'family', 'book', 'dhaka'],
            'entities' => ['person' => ['Nusrat'], 'place' => ['Gulshan']],
        ]);

        $memories['macbook'] = $this->createMemory([
            'title' => 'MacBook Air purchase',
            'type' => 'purchase',
            'source' => 'image',
            'language' => 'en',
            'event' => '2026-03-18',
            'captured' => '2026-03-18 16:45:00',
            'summary' => 'Bought a 13-inch MacBook Air M4 from Gadget & Gear, Banani for BDT 154,900 with AppleCare+ until 18 March 2029.',
            'body' => 'MacBook Air 13-inch, M4, 16 GB memory, 512 GB storage. Purchased from Gadget & Gear, Banani for BDT 154,900. Added AppleCare+ for three years. Warranty is valid until 18 March 2029.',
            'extracted' => 'Gadget & Gear, Banani. MacBook Air 13-inch M4. Total BDT 154,900. AppleCare+ until 18 March 2029.',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['macbook', 'laptop', 'applecare', 'banani'],
                'BDT 154,900',
                'Gadget & Gear',
                '2029-03-18',
                0.93
            ),
            'collection' => 'devices',
            'tags' => ['laptop', 'receipt', 'warranty', 'work'],
            'entities' => ['organization' => ['Gadget & Gear'], 'place' => ['Banani'], 'item' => ['MacBook']],
        ]);

        $memories['sundarbans'] = $this->createMemory([
            'title' => 'Sundarbans trip plan',
            'type' => 'travel',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2026-12-12',
            'captured' => '2026-10-18 21:30:00',
            'summary' => 'Planning a three-day Sundarbans trip in December with the family, including a boat stay and a forest guide.',
            'body' => 'ডিসেম্বরে সুন্দরবন যাওয়ার পরিকল্পনা। তিন দিনের ট্রিপ, পরিবারের পাঁচজন। খুলনা থেকে বোটে করে যাব, এক রাত বোটে থাকব। গাইড বুক করতে হবে এবং মশারি ও ওষুধেরリスト করতে হবে।',
            'extracted' => 'Three-day Sundarbans trip in December with five family members. Travel by boat from Khulna, one night on the boat, need a forest guide, mosquito nets and medicines.',
            'status' => 'ready',
            'metadata' => $this->metadata(['sundarbans', 'trip', 'family', 'khulna'], null, null, null, 0.8),
            'collection' => 'trips',
            'tags' => ['travel', 'family', 'bangla', 'idea'],
            'entities' => ['place' => ['Sundarbans', 'Khulna']],
        ]);

        $memories['health'] = $this->createMemory([
            'title' => 'Annual health check',
            'type' => 'medical',
            'source' => 'document',
            'language' => 'en',
            'event' => '2026-08-18',
            'captured' => '2026-08-18 12:20:00',
            'summary' => 'Annual check at Labaid Diagnostics, Dhanmondi. Blood panel, ECG and consultation for BDT 3,200; vitamin D is low.',
            'body' => 'Annual health check at Labaid Diagnostics, Dhanmondi. Blood panel, ECG and doctor consultation. Total paid BDT 3,200. Everything is within normal range except vitamin D, which is low. The doctor suggested a supplement and more sunlight.',
            'extracted' => 'Labaid Diagnostics, Dhanmondi. Annual health check. Blood panel, ECG, consultation. Total BDT 3,200. Vitamin D low.',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['health', 'labaid', 'blood', 'vitamin d'],
                'BDT 3,200',
                'Labaid Diagnostics',
                null,
                0.9
            ),
            'collection' => 'people',
            'tags' => ['health', 'receipt', 'dhaka'],
            'entities' => ['organization' => ['Labaid Diagnostics'], 'place' => ['Dhanmondi']],
            'attachments' => [[
                'disk' => 'local',
                'path' => $healthPdf['path'],
                'name' => 'annual-health-check-aug-2026.pdf',
                'mime' => 'application/pdf',
                'kind' => 'document',
                'status' => 'extracted',
                'size' => $healthPdf['size'],
                'extracted' => 'Annual health check at Labaid Diagnostics. Total BDT 3,200. Vitamin D low.',
            ]],
        ]);

        $memories['grocery'] = $this->createMemory([
            'title' => 'October grocery budget',
            'type' => 'note',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2026-10-01',
            'captured' => '2026-10-01 08:05:00',
            'summary' => 'Set a BDT 12,000 grocery budget for October, with separate amounts for rice, oil, vegetables and cleaning supplies.',
            'body' => 'অক্টোবর মাসের বাজার বাজেট ১২,০০০ টাকা। চাল ও ডাল ৩,৫০০, তেল ও মসলা ২,২০০, সবজি ২,৫০০, মাছ-মাংস ২,৮০০, পরিষ্কার সামগ্রী ১,০০০। বাজেটের মধ্যে রাখতে হবে।',
            'extracted' => 'October grocery budget BDT 12,000. Rice and lentils 3,500, oil and spices 2,200, vegetables 2,500, fish and meat 2,800, cleaning supplies 1,000.',
            'status' => 'ready',
            'metadata' => $this->metadata(['grocery', 'budget', 'october', 'home'], 'BDT 12,000', null, null, 0.78),
            'collection' => 'home',
            'tags' => ['home', 'shopping', 'bangla'],
        ]);

        $memories['internet'] = $this->createMemory([
            'title' => 'Home internet bill',
            'type' => 'receipt',
            'source' => 'image',
            'language' => 'en',
            'event' => '2026-10-10',
            'captured' => '2026-10-10 10:12:00',
            'summary' => 'Paid the Link3 home internet bill for October 2026: BDT 1,200, connection LK3-4471, Gulshan.',
            'body' => 'Link3 home internet bill for October 2026. Amount BDT 1,200. Paid on 10 October 2026. Connection ID LK3-4471, Gulshan, Dhaka.',
            'extracted' => 'LINK3\nHome internet bill\nOctober 2026\nAmount BDT 1,200\nConnection LK3-4471\nPaid 10 October 2026',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['link3', 'internet', 'bill', 'october'],
                'BDT 1,200',
                'Link3',
                null,
                0.92
            ),
            'collection' => 'home',
            'tags' => ['internet', 'subscription', 'receipt', 'home'],
            'entities' => ['organization' => ['Link3'], 'place' => ['Gulshan']],
            'attachments' => [[
                'disk' => 'public',
                'path' => $internetBill,
                'name' => 'link3-internet-bill-oct-2026.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'stored',
            ]],
        ]);

        $memories['launch'] = $this->createMemory([
            'title' => 'Product launch meeting notes',
            'type' => 'meeting',
            'source' => 'text',
            'language' => 'en',
            'event' => '2026-10-20',
            'captured' => '2026-10-20 15:00:00',
            'summary' => 'Launch meeting notes: keep the beta invite-only, move the public launch to 18 November, and ask support to prepare Bengali help articles.',
            'body' => "Product launch meeting, 20 October 2026.\n\nDecisions:\n- Keep the beta invite-only for two more weeks.\n- Public launch moves to 18 November.\n- Support will prepare Bengali help articles and a short demo video.\n- Rahim owns the launch checklist; Nusrat reviews the onboarding copy.\n\nOpen question: do we need a separate pricing page for annual plans?",
            'extracted' => 'Product launch meeting. Beta invite-only for two more weeks. Public launch on 18 November. Bengali help articles and demo video needed. Rahim owns checklist, Nusrat reviews onboarding copy.',
            'status' => 'ready',
            'metadata' => $this->metadata(['launch', 'meeting', 'beta', 'november'], null, null, null, 0.88),
            'collection' => 'work',
            'tags' => ['work', 'meeting', 'idea'],
            'entities' => ['person' => ['Rahim', 'Nusrat']],
        ]);

        $memories['book'] = $this->createMemory([
            'title' => 'Book recommendation from Rahim',
            'type' => 'link',
            'source' => 'link',
            'language' => 'en',
            'event' => '2026-09-15',
            'captured' => '2026-09-15 22:40:00',
            'summary' => 'Rahim recommended a novel about the Padma river. Saved the link to read after the launch project slows down.',
            'body' => 'Rahim sent this link and said the first chapter is beautiful. Save it for a quiet weekend after the launch.',
            'extracted' => 'Book recommendation from Rahim: a novel about the Padma river.',
            'status' => 'ready',
            'source_url' => 'https://example.com/books/padma-river',
            'metadata' => $this->metadata(['book', 'rahim', 'padma', 'reading'], null, null, null, 0.76),
            'collection' => 'work',
            'tags' => ['book', 'idea', 'work'],
            'entities' => ['person' => ['Rahim']],
        ]);

        $memories['sylhet'] = $this->createMemory([
            'title' => 'Eid trip to Sylhet',
            'type' => 'travel',
            'source' => 'image',
            'language' => 'bn',
            'event' => '2026-04-20',
            'captured' => '2026-04-20 18:30:00',
            'summary' => 'Eid holiday in Sylhet with the family: tea gardens, rainy afternoons and a visit to the shrine.',
            'body' => 'ঈদের ছুটিতে সিলেটে পরিবারের সাথে। চা বাগানে ঘুরেছি, বৃষ্টির বিকেলে চায়ের দোকানে বসেছি, মাজারে গিয়েছি। ফেরার পথে পান্তা ও মাছ খেয়েছি।',
            'extracted' => 'Eid holiday trip to Sylhet with family. Tea gardens, rainy afternoons, shrine visit, and a stop for panta and fish on the way back.',
            'status' => 'ready',
            'metadata' => $this->metadata(['sylhet', 'eid', 'tea', 'family'], null, null, null, 0.83),
            'collection' => 'trips',
            'tags' => ['travel', 'family', 'bangla', 'food'],
            'entities' => ['place' => ['Sylhet']],
            'attachments' => [[
                'disk' => 'public',
                'path' => $sylhetTrip,
                'name' => 'sylhet-eid-trip.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'stored',
            ]],
        ]);

        $memories['bike'] = $this->createMemory([
            'title' => 'Bike servicing receipt',
            'type' => 'repair',
            'source' => 'image',
            'language' => 'en',
            'event' => '2026-07-07',
            'captured' => '2026-07-07 17:25:00',
            'summary' => 'Bike serviced at Rahman Motors for BDT 3,500: engine oil, brake pads and a general check-up.',
            'body' => 'Bike servicing at Rahman Motors. Engine oil, brake pads, chain adjustment and general check-up. Total BDT 3,500. Next service recommended after 3,000 km.',
            'extracted' => 'RAHMAN MOTORS\nBike servicing\nEngine oil, brake pads, chain adjustment\nTotal BDT 3,500\nNext service after 3,000 km',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['bike', 'servicing', 'rahman motors', 'repair'],
                'BDT 3,500',
                'Rahman Motors',
                null,
                0.9
            ),
            'collection' => 'devices',
            'tags' => ['bike', 'repair', 'receipt'],
            'entities' => ['organization' => ['Rahman Motors']],
            'attachments' => [[
                'disk' => 'public',
                'path' => $bikeService,
                'name' => 'bike-service-receipt.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'stored',
            ]],
        ]);

        $memories['insurance'] = $this->createMemory([
            'title' => 'Insurance policy renewal',
            'type' => 'warranty',
            'source' => 'document',
            'language' => 'en',
            'event' => '2026-06-02',
            'captured' => '2026-06-02 13:05:00',
            'summary' => 'Renewed the MetLife policy for another year. Annual premium BDT 18,500; coverage runs until 31 May 2027.',
            'body' => 'MetLife insurance policy renewed on 2 June 2026. Annual premium BDT 18,500. Coverage is valid until 31 May 2027. Policy documents are stored with the home paperwork.',
            'extracted' => 'MetLife insurance renewal. Annual premium BDT 18,500. Coverage until 31 May 2027.',
            'status' => 'ready',
            'metadata' => $this->metadata(
                ['insurance', 'metlife', 'renewal', 'policy'],
                'BDT 18,500',
                'MetLife',
                '2027-05-31',
                0.9
            ),
            'collection' => 'home',
            'tags' => ['insurance', 'warranty', 'home'],
            'entities' => ['organization' => ['MetLife']],
            'attachments' => [[
                'disk' => 'local',
                'path' => $insurancePdf['path'],
                'name' => 'metlife-policy-renewal-2026.pdf',
                'mime' => 'application/pdf',
                'kind' => 'document',
                'status' => 'extracted',
                'size' => $insurancePdf['size'],
                'extracted' => 'MetLife insurance renewal. Premium BDT 18,500. Coverage until 31 May 2027.',
            ]],
        ]);

        $memories['call_ma'] = $this->createMemory([
            'title' => 'Call with Ma',
            'type' => 'note',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2026-10-19',
            'captured' => '2026-10-19 20:45:00',
            'summary' => 'A long call with Ma. She is feeling better, asked about the December trip, and wants us to visit for Eid.',
            'body' => 'মায়ের সাথে ফোনে অনেকক্ষণ কথা হয়েছে।他现在身体好多了，十二月の旅行について聞いてきた。ঈদের সময় বাড়িতে যেতে বলেছেন। পরের সপ্তাহে আবার ফোন করতে হবে।',
            'extracted' => 'Long phone call with Ma. She is feeling better, asked about the December trip, and wants a visit for Eid. Call again next week.',
            'status' => 'ready',
            'metadata' => $this->metadata(['ma', 'family', 'call', 'eid'], null, null, null, 0.79),
            'collection' => 'people',
            'tags' => ['family', 'bangla'],
            'entities' => ['person' => ['Ma']],
            'favorite' => true,
        ]);

        $memories['old_id'] = $this->createMemory([
            'title' => 'Old office ID card',
            'type' => 'document',
            'source' => 'image',
            'language' => 'en',
            'event' => '2024-01-10',
            'captured' => '2024-01-10 10:00:00',
            'summary' => 'Photo of an old office ID card kept for records after leaving the company.',
            'body' => 'Old office ID card, 2024. Kept for records. The card was deactivated when I left the company.',
            'extracted' => 'Office ID card, 2024. Kept for records.',
            'status' => 'ready',
            'metadata' => $this->metadata(['office', 'id card', 'archive'], null, null, null, 0.6),
            'collection' => 'work',
            'tags' => ['work'],
            'archived' => true,
        ]);

        $memories['recipe'] = $this->createMemory([
            'title' => 'Handwritten recipe scan',
            'type' => 'document',
            'source' => 'image',
            'language' => 'bn',
            'event' => '2025-12-05',
            'captured' => '2025-12-05 09:30:00',
            'summary' => 'A scanned page from the family recipe notebook. The handwriting still needs to be typed in.',
            'body' => 'Family recipe notebook, handwritten page. This demo has no image OCR, so the recipe needs to be typed in before it can be searched.',
            'extracted' => null,
            'status' => 'needs_review',
            'error' => 'Image OCR is not configured in this demo. The original image is preserved; add the key text manually or connect an OCR provider.',
            'metadata' => $this->metadata(['recipe', 'family', 'notebook'], null, null, null, 0.4),
            'collection' => 'people',
            'tags' => ['food', 'family', 'bangla'],
            'attachments' => [[
                'disk' => 'public',
                'path' => $recipeScan,
                'name' => 'family-recipe-scan.svg',
                'mime' => 'image/svg+xml',
                'kind' => 'image',
                'status' => 'needs_review',
            ]],
        ]);

        $memories['apartment'] = $this->createMemory([
            'title' => 'Dhanmondi apartment viewing',
            'type' => 'place',
            'source' => 'text',
            'language' => 'en',
            'event' => '2026-09-05',
            'captured' => '2026-09-05 17:50:00',
            'summary' => 'Viewed a three-bedroom apartment in Dhanmondi. Rent is BDT 35,000; the building is quiet but the kitchen is small.',
            'body' => 'Viewed a three-bedroom apartment in Dhanmondi, near Road 27. Rent BDT 35,000 per month, service charge extra. The building is quiet and the balcony gets good light, but the kitchen is small and the second bathroom needs work.',
            'extracted' => 'Three-bedroom apartment in Dhanmondi, near Road 27. Rent BDT 35,000. Quiet building, good balcony light, small kitchen.',
            'status' => 'ready',
            'metadata' => $this->metadata(['apartment', 'dhanmondi', 'rent', 'viewing'], 'BDT 35,000', null, null, 0.8),
            'collection' => 'home',
            'tags' => ['home', 'rent', 'dhaka'],
            'entities' => ['place' => ['Dhanmondi']],
        ]);

        $memories['winter'] = $this->createMemory([
            'title' => 'Winter clothes shopping list',
            'type' => 'note',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2026-10-14',
            'captured' => '2026-10-14 07:20:00',
            'summary' => 'Winter shopping list: two sweaters for Ma, a jacket for Baba, warm socks and a scarf.',
            'body' => 'শীতের জন্য কেনাকাটার তালিকা: মায়ের জন্য দুটি সোয়েটার, বাবার জন্য একটি জ্যাকেট, নিজের জন্য গরম মোজা ও একটি স্কার্ফ। বাজেট ৮,০০০ টাকা।',
            'extracted' => 'Winter shopping list: two sweaters for Ma, one jacket for Baba, warm socks and a scarf. Budget BDT 8,000.',
            'status' => 'ready',
            'metadata' => $this->metadata(['winter', 'shopping', 'family', 'clothes'], 'BDT 8,000', null, null, 0.72),
            'collection' => 'home',
            'tags' => ['shopping', 'family', 'bangla'],
        ]);

        $memories['dentist'] = $this->createMemory([
            'title' => 'Dentist appointment',
            'type' => 'medical',
            'source' => 'text',
            'language' => 'en',
            'event' => '2026-09-22',
            'captured' => '2026-09-22 12:35:00',
            'summary' => 'Dental check-up and scaling with Dr. Farhana at Dhanmondi Dental. Paid BDT 2,000; follow-up in six months.',
            'body' => 'Dental check-up and scaling with Dr. Farhana at Dhanmondi Dental. Everything looked fine. Paid BDT 2,000. Follow-up appointment recommended in six months.',
            'extracted' => 'Dentist appointment with Dr. Farhana at Dhanmondi Dental. Scaling completed. Paid BDT 2,000. Follow-up in six months.',
            'status' => 'ready',
            'metadata' => $this->metadata(['dentist', 'dr farhana', 'dental', 'appointment'], 'BDT 2,000', 'Dhanmondi Dental', null, 0.86),
            'collection' => 'people',
            'tags' => ['health', 'receipt', 'dhaka'],
            'entities' => ['person' => ['Dr. Farhana'], 'place' => ['Dhanmondi'], 'organization' => ['Dhanmondi Dental']],
        ]);

        $memories['aarong'] = $this->createMemory([
            'title' => 'Aarong order for Eid',
            'type' => 'purchase',
            'source' => 'text',
            'language' => 'bn',
            'event' => '2026-03-30',
            'captured' => '2026-03-30 14:15:00',
            'summary' => 'Ordered an Eid panjabi from Aarong for BDT 4,850. Delivery was scheduled before Eid.',
            'body' => 'আরং থেকে ঈদের জন্য একটি পাঞ্জাবি অর্ডার করেছি। দাম ৪,৮৫০ টাকা। ঈদের আগে ডেলিভারি পাওয়ার কথা।',
            'extracted' => 'Aarong order for Eid: one panjabi, BDT 4,850. Delivery before Eid.',
            'status' => 'ready',
            'metadata' => $this->metadata(['aarong', 'eid', 'panjabi', 'purchase'], 'BDT 4,850', 'Aarong', null, 0.85),
            'collection' => 'people',
            'tags' => ['shopping', 'receipt', 'family'],
            'entities' => ['organization' => ['Aarong']],
        ]);

        return $memories;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMemory(array $data): Memory
    {
        $captured = Carbon::parse($data['captured']);

        $memory = Memory::create([
            'user_id' => $this->user->id,
            'title' => $data['title'],
            'memory_type' => $data['type'],
            'source_type' => $data['source'],
            'source_url' => $data['source_url'] ?? null,
            'body' => $data['body'] ?? null,
            'extracted_text' => $data['extracted'] ?? null,
            'summary' => $data['summary'] ?? null,
            'detected_language' => $data['language'],
            'event_date' => $data['event'] ?? null,
            'captured_at' => $captured,
            'processing_status' => $data['status'] ?? 'ready',
            'processing_error' => $data['error'] ?? null,
            'ai_metadata' => $data['metadata'] ?? null,
            'is_favorite' => (bool) ($data['favorite'] ?? false),
            'is_archived' => (bool) ($data['archived'] ?? false),
            'visibility' => 'private',
        ]);

        $memory->timestamps = false;
        $memory->forceFill(['created_at' => $captured, 'updated_at' => $captured])->saveQuietly();
        $memory->timestamps = true;

        if (! empty($data['collection']) && isset($this->collections[$data['collection']])) {
            $memory->collections()->syncWithoutDetaching([$this->collections[$data['collection']]->id]);
        }

        foreach ($data['tags'] ?? [] as $tagName) {
            if (isset($this->tags[$tagName])) {
                $memory->tags()->syncWithoutDetaching([$this->tags[$tagName]->id]);
            }
        }

        foreach ($data['entities'] ?? [] as $type => $names) {
            foreach ($names as $name) {
                $entity = $this->entity($type, $name);
                $memory->entities()->syncWithoutDetaching([
                    $entity->id => ['provenance' => 'extracted', 'confidence' => 0.82],
                ]);
            }
        }

        foreach ($data['attachments'] ?? [] as $attachment) {
            MemoryAttachment::create([
                'memory_id' => $memory->id,
                'disk' => $attachment['disk'],
                'path' => $attachment['path'],
                'original_name' => $attachment['name'],
                'mime_type' => $attachment['mime'],
                'size' => $attachment['size'] ?? Storage::disk($attachment['disk'])->size($attachment['path']),
                'kind' => $attachment['kind'],
                'extracted_text' => $attachment['extracted'] ?? null,
                'status' => $attachment['status'],
            ]);
        }

        $this->processingJobs($memory, $captured);

        return $memory;
    }

    private function entity(string $type, string $name): Entity
    {
        $key = $type.':'.$name;

        return $this->entities[$key] ??= Entity::create([
            'user_id' => $this->user->id,
            'type' => $type,
            'name' => $name,
            'metadata' => ['discovered_from' => 'demo_seed'],
        ]);
    }

    private function processingJobs(Memory $memory, Carbon $captured): void
    {
        $jobs = [
            ['type' => 'intake', 'status' => 'completed', 'meta' => ['source' => $memory->source_type]],
            ['type' => 'classify', 'status' => 'completed', 'meta' => ['memory_type' => $memory->memory_type]],
            ['type' => 'index', 'status' => 'completed', 'meta' => ['strategy' => 'bilingual-keyword-index']],
        ];

        if ($memory->processing_status === 'needs_review') {
            $jobs[] = [
                'type' => 'review_required',
                'status' => 'needs_review',
                'meta' => ['reason' => 'ocr_unavailable'],
                'error' => $memory->processing_error,
            ];
        }

        foreach ($jobs as $job) {
            $model = ProcessingJob::create([
                'memory_id' => $memory->id,
                'type' => $job['type'],
                'status' => $job['status'],
                'attempts' => 1,
                'error_message' => $job['error'] ?? null,
                'meta' => $job['meta'],
                'started_at' => $captured,
                'finished_at' => $captured,
            ]);

            DB::table('processing_jobs')->where('id', $model->id)->update([
                'created_at' => $captured,
                'updated_at' => $captured,
            ]);
        }
    }

    /**
     * @param  array<string, Memory>  $memories
     */
    private function seedRelationships(array $memories): void
    {
        $this->relate($memories['iphone'], $memories['macbook'], 'related');
        $this->relate($memories['rent'], $memories['insurance'], 'related');
        $this->relate($memories['padma'], $memories['sylhet'], 'related');
        $this->relate($memories['coffee'], $memories['book'], 'related');
        $this->relate($memories['fridge'], $memories['rent'], 'related');
    }

    private function relate(Memory $from, Memory $to, string $type): void
    {
        MemoryRelationship::create([
            'user_id' => $this->user->id,
            'from_memory_id' => $from->id,
            'to_memory_id' => $to->id,
            'relationship_type' => $type,
            'provenance' => 'user',
            'is_confirmed' => true,
        ]);
    }

    /**
     * @param  array<string, Memory>  $memories
     */
    private function seedReminders(array $memories): void
    {
        $definitions = [
            [
                'memory' => 'rent',
                'title' => 'Renew the flat rent agreement',
                'notes' => 'Talk to Mr. Karim about the rent and get the renewal draft.',
                'type' => 'renewal',
                'due_at' => now()->addMonths(8)->setTime(10, 0),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'iphone',
                'title' => 'iPhone warranty ends',
                'notes' => 'Decide whether to extend the warranty before it expires.',
                'type' => 'warranty',
                'due_at' => Carbon::parse('2027-08-12 09:00'),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'book',
                'title' => 'Return Rahim’s book',
                'notes' => 'Finish the last two chapters and return it at the next coffee meet.',
                'type' => 'follow_up',
                'due_at' => now()->addDays(6)->setTime(18, 0),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'insurance',
                'title' => 'Insurance renewal',
                'notes' => 'Compare the premium before the May renewal window.',
                'type' => 'renewal',
                'due_at' => Carbon::parse('2027-05-20 11:00'),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'call_ma',
                'title' => 'Call Ma on Sunday',
                'notes' => 'Ask about the December trip and whether she needs anything from Dhaka.',
                'type' => 'follow_up',
                'due_at' => now()->addDays(3)->setTime(19, 30),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'dentist',
                'title' => 'Dental follow-up',
                'notes' => 'Book the six-month check-up with Dr. Farhana.',
                'type' => 'event',
                'due_at' => now()->subDays(9)->setTime(16, 0),
                'status' => 'upcoming',
            ],
            [
                'memory' => 'fridge',
                'title' => 'Check the fridge warranty',
                'notes' => 'The six-month repair warranty has ended. Everything is still working.',
                'type' => 'warranty',
                'due_at' => now()->subMonths(5)->setTime(10, 0),
                'status' => 'completed',
                'completed_at' => now()->subMonths(5)->setTime(10, 0),
            ],
            [
                'memory' => 'health',
                'title' => 'Submit health report to HR',
                'notes' => 'Uploaded the medical report for the annual health benefit.',
                'type' => 'follow_up',
                'due_at' => now()->subDays(16)->setTime(12, 0),
                'status' => 'completed',
                'completed_at' => now()->subDays(16)->setTime(12, 0),
            ],
        ];

        foreach ($definitions as $definition) {
            Reminder::create([
                'user_id' => $this->user->id,
                'memory_id' => $memories[$definition['memory']]->id,
                'title' => $definition['title'],
                'notes' => $definition['notes'],
                'type' => $definition['type'],
                'due_at' => $definition['due_at'],
                'status' => $definition['status'],
                'notification_channel' => 'in_app',
                'completed_at' => $definition['completed_at'] ?? null,
            ]);
        }
    }

    private function seedSearchHistory(): void
    {
        $queries = [
            ['Where did I save my phone receipt?', 3, now()->subHours(2)],
            ['Which appliance did I repair last year?', 1, now()->subHours(5)],
            ['আমার ফোনের রিসিট কোথায় রেখেছিলাম?', 3, now()->subDay()],
            ['What did I pay for my last three purchases?', 12, now()->subDays(2)],
            ['Show me everything related to my laptop', 2, now()->subDays(3)],
            ['Flat rent agreement', 1, now()->subDays(5)],
        ];

        foreach ($queries as [$query, $count, $createdAt]) {
            SearchHistory::create([
                'user_id' => $this->user->id,
                'query' => $query,
                'locale' => 'en',
                'results_count' => $count,
                'created_at' => $createdAt,
            ]);
        }
    }

    private function seedAuditTrail(): void
    {
        $entries = [
            ['account.created', null, now()->subYear()],
            ['settings.language_updated', null, now()->subMonths(2)],
            ['data.exported', null, now()->subDays(10)],
            ['memory.created', null, now()->subDays(2)],
        ];

        foreach ($entries as [$action, $subjectId, $createdAt]) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => $action,
                'subject_type' => $action === 'memory.created' ? Memory::class : null,
                'subject_id' => $subjectId,
                'ip_address' => '127.0.0.1',
                'created_at' => $createdAt,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<string, mixed>
     */
    private function metadata(
        array $keywords,
        ?string $amount = null,
        ?string $merchant = null,
        ?string $warrantyUntil = null,
        float $confidence = 0.85
    ): array {
        $facts = [];

        if ($amount) {
            $facts[] = ['key' => 'amount', 'value' => $amount, 'provenance' => 'extracted', 'confidence' => 0.9];
        }

        if ($merchant) {
            $facts[] = ['key' => 'merchant', 'value' => $merchant, 'provenance' => 'extracted', 'confidence' => 0.78];
        }

        if ($warrantyUntil) {
            $facts[] = ['key' => 'warranty_until', 'value' => $warrantyUntil, 'provenance' => 'extracted', 'confidence' => 0.76];
        }

        return array_filter([
            'extractor' => 'realitylog-local-v1',
            'classifier' => 'realitylog-rules-v1',
            'category_confidence' => $confidence,
            'keywords' => $keywords,
            'amount' => $amount,
            'merchant' => $merchant,
            'warranty_until' => $warrantyUntil,
            'facts' => $facts,
            'language_confidence' => 0.92,
        ], fn ($value) => $value !== null);
    }

    private function tagColor(string $name): string
    {
        $colors = ['#D95F3B', '#517A64', '#C08A3E', '#526A8A', '#8B5D6F', '#68756C'];

        return $colors[abs(crc32($name)) % count($colors)];
    }

    private function demoImage(string $slug, string $title, string $subtitle, string $accent): string
    {
        $path = 'demo/'.$slug.'.svg';
        $lines = explode("\n", wordwrap($title, 24, "\n", true));
        $titleMarkup = '';
        $y = 250;

        foreach (array_slice($lines, 0, 3) as $line) {
            $titleMarkup .= '<text x="110" y="'.$y.'" font-family="Helvetica, Arial, sans-serif" font-size="44" font-weight="700" fill="#17231e">'.$this->escape($line).'</text>';
            $y += 58;
        }

        $subtitleY = $y + 22;
        $subtitleMarkup = '<text x="110" y="'.$subtitleY.'" font-family="Helvetica, Arial, sans-serif" font-size="22" fill="#71786f">'.$this->escape($subtitle).'</text>';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800" role="img" aria-label="{$this->escape($title)}">
    <defs>
        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#f5f1e8"/>
            <stop offset="100%" stop-color="#ebe4d6"/>
        </linearGradient>
    </defs>
    <rect width="1200" height="800" fill="url(#bg)"/>
    <circle cx="1040" cy="130" r="220" fill="{$accent}" opacity="0.12"/>
    <circle cx="1060" cy="700" r="150" fill="{$accent}" opacity="0.08"/>
    <rect x="72" y="68" width="1056" height="664" rx="32" fill="#fffdf8" stroke="#e5dfd2" stroke-width="2"/>
    <rect x="110" y="120" width="76" height="76" rx="20" fill="{$accent}" opacity="0.16"/>
    <circle cx="148" cy="158" r="17" fill="{$accent}"/>
    <text x="210" y="168" font-family="Helvetica, Arial, sans-serif" font-size="20" font-weight="700" letter-spacing="3" fill="{$accent}">REALITYLOG DEMO</text>
    {$titleMarkup}
    {$subtitleMarkup}
    <line x1="110" y1="430" x2="1010" y2="430" stroke="#e5dfd2" stroke-width="2"/>
    <rect x="110" y="480" width="520" height="18" rx="9" fill="#e5dfd2"/>
    <rect x="110" y="520" width="760" height="14" rx="7" fill="#eee9df"/>
    <rect x="110" y="552" width="680" height="14" rx="7" fill="#eee9df"/>
    <rect x="110" y="584" width="430" height="14" rx="7" fill="#eee9df"/>
    <rect x="110" y="636" width="190" height="44" rx="12" fill="{$accent}" opacity="0.14"/>
    <text x="132" y="664" font-family="Helvetica, Arial, sans-serif" font-size="18" font-weight="700" fill="{$accent}">PRIVATE BY DEFAULT</text>
    <rect x="830" y="480" width="180" height="200" rx="18" fill="{$accent}" opacity="0.1"/>
    <path d="M870 540 h100 M870 580 h100 M870 620 h60" stroke="{$accent}" stroke-width="10" stroke-linecap="round" opacity="0.5"/>
</svg>
SVG;

        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{path: string, size: int}
     */
    private function demoPdf(string $filename, string $title, array $lines): array
    {
        $path = 'demo/'.$filename;
        $content = "BT\n/F1 18 Tf\n72 740 Td\n(".$this->pdfEscape($title).") Tj\n";
        $content .= "/F1 10 Tf\n0 -30 Td\n";

        foreach ($lines as $line) {
            $content .= '('.$this->pdfEscape($line).") Tj\n0 -16 Td\n";
        }

        $content .= 'ET';

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length '.strlen($content).">>\nstream\n".$content."\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        Storage::disk('local')->put($path, $pdf);

        return ['path' => $path, 'size' => strlen($pdf)];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
