<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PrivacyPolicy;
use App\Models\TermCondition;
use Illuminate\Database\Seeder;

class ContentPagesSeeder extends Seeder
{
    /**
     * Terms & Conditions, Privacy Policy, and the "About Us" pages had no
     * seeder at all - a fresh install had zero rows for any of them, so
     * every footer link to /terms and /privacy 404ed (rendering as Next's
     * generic "page doesn't exist" screen on a page that isn't actually
     * missing, just empty) and /about rendered blank. Real, readable
     * placeholder copy pending an actual legal review of terms/privacy.
     *
     * @return void
     */
    public function run(): void
    {
        if (!TermCondition::exists()) {
            $term = TermCondition::create([]);
            $term->translations()->create([
                'locale' => 'en',
                'title' => 'Terms & Conditions',
                'description' => $this->termsHtml(),
            ]);
        }

        if (!PrivacyPolicy::exists()) {
            $policy = PrivacyPolicy::create([]);
            $policy->translations()->create([
                'locale' => 'en',
                'title' => 'Privacy Policy',
                'description' => $this->privacyHtml(),
            ]);
        }

        foreach ($this->aboutPages() as $data) {
            if (Page::where('type', $data['type'])->exists()) {
                continue;
            }

            $page = Page::create([
                'type' => $data['type'],
                'active' => true,
                'img' => $data['img'],
                'bg_img' => $data['img'],
                'buttons' => [],
            ]);

            $page->translations()->create([
                'locale' => 'en',
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }
    }

    private function aboutPages(): array
    {
        return [
            [
                'type' => Page::ABOUT,
                // Unsplash - already on next.config.js's remotePatterns
                // allowlist. Real, purpose-fitting photos (booking on a
                // phone; a home-service visit; a small shop owner) instead
                // of the generic /img/banner*.png site assets used at
                // first pass, before real content existed to pair with.
                'img' => 'https://images.unsplash.com/photo-1522125670776-3c7abb882bc2?auto=format&fit=crop&w=1200&h=600&q=80',
                'title' => 'About AgendaAlly',
                'description' => "<p>AgendaAlly was built to make booking beauty and wellness services as easy as it should be. Whether you're looking for a haircut, a manicure, a massage, or a full spa day, we connect you with trusted local salons and independent masters, so you can browse real availability and book in just a few taps.</p><p>No more phone tag or guessing whether your favorite salon has an opening - see who's available, compare services and prices, and confirm your appointment on the spot.</p>",
            ],
            [
                'type' => Page::ABOUT_SECOND,
                'img' => 'https://images.unsplash.com/photo-1646980241033-cd7abda2ee88?auto=format&fit=crop&w=1200&h=600&q=80',
                'title' => 'Our mission',
                'description' => '<p>We believe booking a service should be simple, transparent, and reliable - for customers and professionals alike. That means clear pricing, real reviews, and a booking experience that respects your time.</p>',
            ],
            [
                'type' => Page::ABOUT_THREE,
                'img' => 'https://images.unsplash.com/photo-1531058240690-006c446962d8?auto=format&fit=crop&w=1200&h=600&q=80',
                'title' => 'Built for the community',
                'description' => "<p>Every booking made through AgendaAlly supports a local salon or independent professional. We're proud to help small beauty and wellness businesses reach more customers and grow, one appointment at a time.</p>",
            ],
        ];
    }

    private function termsHtml(): string
    {
        return <<<'HTML'
<p>These Terms & Conditions ("Terms") govern your use of AgendaAlly and the booking services we provide. By creating an account or making a booking through our platform, you agree to these Terms.</p>

<h2>Using AgendaAlly</h2>
<p>AgendaAlly connects customers with independent beauty and wellness professionals ("masters") and salons ("shops"). We provide the booking platform; the services themselves are performed by the master or shop you book with, who are responsible for the quality and safety of the service provided.</p>

<h2>Bookings & Payments</h2>
<p>When you book a service, you agree to pay the price shown at checkout, plus any applicable service fees. Payment can be made online or, where offered, directly at the salon. Prices, availability, and service details are set by each individual master or shop.</p>

<h2>Cancellations & Rescheduling</h2>
<p>Each booking is subject to the cancellation and rescheduling policy shown on that booking's details page. Cancelling or rescheduling after the stated deadline may incur a fee, which is disclosed to you before you confirm your booking.</p>

<h2>Your Account</h2>
<p>You are responsible for keeping your account credentials secure and for all activity that occurs under your account. Please notify us promptly if you suspect unauthorized use of your account.</p>

<h2>Conduct</h2>
<p>We expect all customers, masters, and shops to treat each other with respect. We may suspend or remove accounts that violate these Terms, engage in fraud, or behave abusively toward other users.</p>

<h2>Changes to These Terms</h2>
<p>We may update these Terms from time to time. Continued use of AgendaAlly after an update means you accept the revised Terms.</p>

<h2>Contact Us</h2>
<p>Questions about these Terms? Reach out through our <a href="/contact">Contact page</a>.</p>

<p><em>Note: this is placeholder content pending a full legal review, not final legal terms.</em></p>
HTML;
    }

    private function privacyHtml(): string
    {
        return <<<'HTML'
<p>This Privacy Policy explains what information AgendaAlly collects, how we use it, and the choices you have.</p>

<h2>Information We Collect</h2>
<p>We collect information you provide directly, such as your name, email address, phone number, and payment details, as well as information generated by using the service, like your booking history and location (when you allow it, to show nearby salons and masters).</p>

<h2>How We Use Your Information</h2>
<p>We use your information to create and manage your bookings, process payments, send booking confirmations and reminders, provide customer support, and improve our platform. We may also use it to send you offers or updates, which you can opt out of at any time.</p>

<h2>Sharing Your Information</h2>
<p>We share the details needed to fulfil a booking (such as your name and contact information) with the master or shop you book with. We also work with payment processors to handle transactions securely. We do not sell your personal information to third parties.</p>

<h2>Cookies</h2>
<p>We use cookies to keep you signed in, remember your preferences (like language and currency), and understand how our platform is used so we can improve it.</p>

<h2>Your Rights</h2>
<p>You can access, update, or delete your account information at any time from your profile settings, or by contacting us. You may also request a copy of the data we hold about you.</p>

<h2>Data Retention</h2>
<p>We keep your information for as long as your account is active, or as needed to provide our services and meet legal obligations.</p>

<h2>Contact Us</h2>
<p>Questions about this Privacy Policy? Reach out through our <a href="/contact">Contact page</a>.</p>

<p><em>Note: this is placeholder content pending a full legal review, not a final privacy policy.</em></p>
HTML;
    }
}
