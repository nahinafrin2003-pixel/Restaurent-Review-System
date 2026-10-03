<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\ComplaintStatusHistory;
use App\Models\PostedReview;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\ComplaintWorkflow;
use App\Services\TrustScoreService;
use App\Support\ComplaintStatus as S;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo data so every screen has something to show. All restaurant names are fictional.
 * Demo accounts use the reserved .test domain: no e-mail is sent and, with APP_DEBUG=true,
 * the login OTP is shown on screen.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass2026!';

    public function run(): void
    {
        @set_time_limit(0);

        if (User::findByEmail('officer1@demo.test')) {
            $this->command?->info('Demo data already present — skipped.');

            return;
        }

        $officers = [
            $this->user('Officer@01', 'officer1@demo.test', '01700000001', 'officer'),
            $this->user('Officer@02', 'officer2@demo.test', '01700000002', 'officer'),
        ];
        $customers = [
            $this->user('Rahim@2026', 'customer@demo.test', '01800000001', 'customer'),
            $this->user('Karim#2026', 'karim@demo.test', '01800000002', 'customer'),
            $this->user('Nadia$2026', 'nadia@demo.test', '01800000003', 'customer'),
        ];

        $restaurants = collect([
            ['Golden Spoon Kacchi House', 'Road 27, Dhanmondi', 'Dhaka', 23.7461, 90.3742],
            ['Spice Route Kitchen', 'Gulshan Avenue', 'Dhaka', 23.7925, 90.4078],
            ['Old Town Biryani Ghor', 'Nazira Bazar', 'Dhaka', 23.7176, 90.4048],
            ['Green Leaf Café', 'Uttara Sector 7', 'Dhaka', 23.8728, 90.3984],
            ['Mirpur Tehari Point', 'Mirpur 10', 'Dhaka', 23.8069, 90.3687],
            ['Port City Mezban Bari', 'GEC Circle', 'Chittagong', 22.3590, 91.8215],
            ['Seaside Grill & Fry', 'Patenga', 'Chittagong', 22.2350, 91.7915],
            ['Tea Garden Bistro', 'Zindabazar', 'Sylhet', 24.8966, 91.8710],
            ['Padma Fish Corner', 'Shaheb Bazar', 'Rajshahi', 24.3667, 88.6000],
            ['Rupsha River Diner', 'KDA Avenue', 'Khulna', 22.8159, 89.5530],
            ['Kirtonkhola Hotel & Rice', 'Sadar Road', 'Barisal', 22.7029, 90.3700],
            ['Brahmaputra Sweets', 'Ganginarpar', 'Mymensingh', 24.7539, 90.4073],
        ])->map(fn ($r) => Restaurant::create(['name' => $r[0], 'address' => $r[1], 'district' => $r[2], 'latitude' => $r[3], 'longitude' => $r[4], 'trust_score' => 5]));

        // Approved & pending reviews
        $reviewTexts = [
            [9, 'Excellent kacchi, tender mutton and the staff were very polite.'],
            [7, 'Good food but the waiting time on Friday night was long.'],
            [4, 'The biryani was cold and too oily this time.'],
            [8, 'Fresh fish curry, fair prices and a clean dining area.'],
            [6, 'Average taste, service was okay.'],
            [10, 'Best mezban beef I have had in years!'],
        ];
        foreach ($restaurants->take(9) as $i => $restaurant) {
            [$score, $text] = $reviewTexts[$i % count($reviewTexts)];
            $review = Review::create([
                'user_id' => $customers[$i % 3]->user_id, 'restaurant_id' => $restaurant->id, 'restaurant_name' => $restaurant->name,
                'review_text' => $text, 'review_score' => $score, 'location' => $restaurant->address, 'city' => $restaurant->district,
                'status' => $i < 7 ? 'approved' : 'pending', 'edit_count' => 0, 'max_edit_limit' => 3,
            ]);
            $review->generateMac();
            $review->generateSignature();
            $review->save();
            if ($review->status === 'approved') {
                PostedReview::createFromReview($review);
            }
        }

        $sharp = $this->sampleImage(false);
        $blurry = $this->sampleImage(true);

        // [restaurant idx, customer idx, title, description, months ago, final path]
        $cases = [
            [0, 0, 'Food poisoning after kacchi biryani', 'My family had kacchi biryani for dinner. Within four hours all three of us were vomiting and had diarrhea; we had to go to the hospital the next morning.', 8, [S::UNDER_VERIFICATION, S::VERIFIED, S::SENT_TO_INVESTIGATION, S::RESOLVED]],
            [0, 1, 'Got sick with stomach pain after eating mutton', 'After eating the mutton kacchi I had severe stomach pain, nausea and vomiting the whole night. The doctor said it was food poisoning.', 8, [S::UNDER_VERIFICATION, S::VERIFIED]],
            [2, 2, 'Cockroach found in the kitchen and in my plate', 'There was a cockroach in my rice and the kitchen was visibly dirty, with flies everywhere. Very unhygienic.', 7, [S::UNDER_VERIFICATION, S::VERIFIED, S::SENT_TO_INVESTIGATION, S::RESOLVED]],
            [2, 0, 'Dirty kitchen and rats near the storage', 'I saw rats near the rice storage and the cooks were not wearing gloves. The whole place smelled bad.', 6, [S::UNDER_VERIFICATION, S::VERIFIED, S::CLOSED]],
            [1, 1, 'Overcharged — bill much higher than menu', 'The menu said Tk 450 for the platter but the bill charged Tk 650 plus a hidden service charge. They refused to give a proper receipt.', 5, [S::UNDER_VERIFICATION, S::VERIFIED, S::RESOLVED]],
            [4, 2, 'Fake BSTI certificate displayed', 'The restaurant displays a BSTI certificate and halal certificate, but the certificate number does not exist. Looks forged.', 5, [S::UNDER_VERIFICATION, S::VERIFIED, S::SENT_TO_INVESTIGATION]],
            [5, 0, 'Stale beef served at mezban', 'The beef smelled sour and was clearly stale, maybe from the previous day. Some pieces had mould.', 4, [S::UNDER_VERIFICATION, S::VERIFIED, S::RESOLVED]],
            [6, 1, 'Rude staff threatened me', 'When I complained about the cold food the manager shouted at me and threatened me in front of other customers.', 4, [S::UNDER_VERIFICATION, S::REJECTED]],
            [3, 2, 'Expired milk used in coffee', 'The milk carton on the counter had an expiry date from last week. My coffee tasted sour.', 3, [S::UNDER_VERIFICATION, S::EVIDENCE_REQUESTED]],
            [8, 0, 'Formalin suspected in fish', 'The fish looked unnaturally fresh and had a chemical smell. Suspect formalin adulteration.', 3, [S::UNDER_VERIFICATION, S::VERIFIED, S::SENT_TO_INVESTIGATION]],
            [9, 1, 'Overcharging on soft drinks', 'They charged Tk 60 for a soft drink with MRP Tk 30. Extra charge without explanation.', 2, [S::UNDER_VERIFICATION, S::VERIFIED, S::RESOLVED]],
            [7, 2, 'Hair and flies in the food', 'Found hair in my noodles and the table was full of flies. Hygiene is very poor.', 2, [S::UNDER_VERIFICATION]],
            [0, 2, 'Vomiting after eating biryani yesterday', 'I ate biryani there yesterday and started vomiting with diarrhea at night. Same problem others reported.', 1, []],
            [10, 0, 'Artificial colour in sweets', 'The sweets had an unnatural bright colour that stained my hands. Possibly textile dye.', 1, [S::UNDER_VERIFICATION]],
            [11, 1, 'Expired packaged food sold', 'Packaged yoghurt sold to me was expired by two weeks.', 0, []],
            [1, 0, 'Wrong bill again', 'Charged for items I did not order; the bill was wrong and staff refused a refund.', 0, [S::UNDER_VERIFICATION, S::EVIDENCE_REQUESTED]],
        ];

        $base = Carbon::now();
        foreach ($cases as $i => [$ri, $ci, $title, $description, $monthsAgo, $path]) {
            $restaurant = $restaurants[$ri];
            $customer = $customers[$ci];
            $officer = $officers[$i % 2];
            $when = $base->copy()->subMonths($monthsAgo)->subDays(($i * 3) % 20)->setTime(10 + $i % 8, 15);
            Carbon::setTestNow($when);

            $files = [];
            if ($i % 3 === 0) {
                $files['image'] = [new UploadedFile($i % 2 ? $blurry : $sharp, 'photo-' . ($i + 1) . '.png', 'image/png', null, true)];
            }

            $complaint = ComplaintWorkflow::submit($customer, $restaurant, [
                'title' => $title, 'description' => $description, 'category' => 'auto', 'incident_date' => $when->copy()->subDay()->toDateString(),
            ], $files);

            $step = $when->copy();
            foreach ($path as $status) {
                $step->addDays(1 + ($i % 4));
                Carbon::setTestNow($step);
                if ($status === S::UNDER_VERIFICATION) {
                    $complaint->update(['assigned_officer_id' => $officer->user_id]);
                }
                $note = match ($status) {
                    S::UNDER_VERIFICATION => 'Complaint picked up for verification.',
                    S::EVIDENCE_REQUESTED => 'Please upload a photo of the receipt or the item.',
                    S::VERIFIED => 'Evidence reviewed and complaint verified.',
                    S::SENT_TO_INVESTIGATION => 'Forwarded to the district food safety inspection team.',
                    S::RESOLVED => 'Inspection completed.',
                    S::REJECTED => 'Insufficient evidence for this allegation.',
                    default => null,
                };
                $outcome = match ($status) {
                    S::RESOLVED => ['Fined Tk 50,000 and kitchen sealed for 3 days', 'Warning issued; hygiene re-inspection passed', 'Refund ordered and fine of Tk 10,000'][$i % 3],
                    S::CLOSED => 'Restaurant issued an official warning',
                    S::REJECTED => 'Not substantiated',
                    default => null,
                };
                ComplaintWorkflow::transition($complaint->fresh(), $status, $officer, $note, $outcome);
            }

            if ($i % 4 === 1) {
                Carbon::setTestNow($step->copy()->addHours(3));
                ComplaintMessage::create(['complaint_id' => $complaint->id, 'sender_id' => $officer->user_id, 'body' => 'Thank you for the report. Do you still have the receipt?', 'created_at' => now()]);
                ComplaintMessage::create(['complaint_id' => $complaint->id, 'sender_id' => $customer->user_id, 'body' => 'Yes, I will upload a photo of it today.', 'created_at' => now()->addMinutes(40)]);
            }
        }
        Carbon::setTestNow();

        $restaurants->each(fn ($r) => TrustScoreService::recalculate($r));

        $this->command?->info('Demo officers: officer1@demo.test, officer2@demo.test');
        $this->command?->info('Demo customers: customer@demo.test, karim@demo.test, nadia@demo.test');
        $this->command?->info('Demo password for all: ' . self::PASSWORD . '  (OTP is shown on screen while APP_DEBUG=true)');
    }

    private function user(string $username, string $email, string $phone, string $role): User
    {
        $user = User::create(['username' => $username, 'email' => $email, 'contact_info' => $phone, 'password' => self::PASSWORD, 'role' => $role, 'is_active' => true]);
        UserActivity::create(['user_id' => $user->user_id, 'remaining_reviews' => 5]);

        return $user;
    }

    /** Draws a test "food photo"; the blurry variant is heavily smoothed so blur detection flags it. */
    private function sampleImage(bool $blur): string
    {
        $path = storage_path('app/demo-' . ($blur ? 'blurry' : 'sharp') . '.png');
        if (! function_exists('imagecreatetruecolor')) {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

            return $path;
        }
        $img = imagecreatetruecolor(480, 360);
        imagefill($img, 0, 0, imagecolorallocate($img, 240, 230, 210));
        mt_srand($blur ? 7 : 3);
        for ($k = 0; $k < 60; $k++) {
            $c = imagecolorallocate($img, mt_rand(80, 230), mt_rand(40, 180), mt_rand(20, 120));
            imagefilledellipse($img, mt_rand(0, 480), mt_rand(0, 360), mt_rand(10, 70), mt_rand(10, 70), $c);
        }
        for ($k = 0; $k < 40; $k++) {
            imageline($img, mt_rand(0, 480), mt_rand(0, 360), mt_rand(0, 480), mt_rand(0, 360), imagecolorallocate($img, 30, 30, 30));
        }
        imagestring($img, 5, 20, 20, 'RestoSafe evidence sample', imagecolorallocate($img, 0, 0, 0));
        if ($blur) {
            for ($k = 0; $k < 40; $k++) {
                imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
