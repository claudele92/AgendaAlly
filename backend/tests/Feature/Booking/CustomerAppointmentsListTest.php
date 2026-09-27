<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BookingService::create() inserts a Booking row the moment a time slot
 * is confirmed - before the customer ever reaches the payment step - and
 * it stays at the DB default status 'new' if they abandon checkout
 * before paying (see create_bookings_table migration). The customer's
 * own "My Appointments" list (Dashboard/User/BookingController::index())
 * must not show those - they were never actually confirmed - while
 * still showing every other status, canceled included.
 */
class CustomerAppointmentsListTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $customer, string $status): Booking
    {
        $shop     = Shop::factory()->create(['user_id' => User::factory()->create()->id]);
        $master   = User::factory()->create();
        $currency = Currency::factory()->create();

        return Booking::create([
            'service_master_id' => null,
            'master_id'         => $master->id,
            'user_id'           => $customer->id,
            'shop_id'           => $shop->id,
            'currency_id'       => $currency->id,
            'start_date'        => now()->addDay(),
            'end_date'          => now()->addDay()->addHour(),
            'price'             => 5000,
            'total_price'       => 5000,
            'service_fee'       => 0,
            'status'            => $status,
        ]);
    }

    public function test_appointments_list_excludes_never_paid_bookings_but_keeps_every_other_status(): void
    {
        $customer = User::factory()->create();

        $new      = $this->makeBooking($customer, Booking::STATUS_NEW);
        $booked   = $this->makeBooking($customer, Booking::STATUS_BOOKED);
        $progress = $this->makeBooking($customer, Booking::STATUS_PROGRESS);
        $ended    = $this->makeBooking($customer, Booking::STATUS_ENDED);
        $canceled = $this->makeBooking($customer, Booking::STATUS_CANCELED);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('api/v1/dashboard/user/bookings?parent=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($new->id, $ids, 'a never-paid booking must not appear in My Appointments');
        $this->assertContains($booked->id, $ids);
        $this->assertContains($progress->id, $ids);
        $this->assertContains($ended->id, $ids);
        $this->assertContains($canceled->id, $ids, 'canceled bookings must stay visible');
    }

    public function test_an_explicit_status_filter_is_not_overridden_by_the_default_exclusion(): void
    {
        $customer = User::factory()->create();

        $new = $this->makeBooking($customer, Booking::STATUS_NEW);
        $this->makeBooking($customer, Booking::STATUS_BOOKED);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('api/v1/dashboard/user/bookings?parent=1&status=' . Booking::STATUS_NEW)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains(
            $new->id,
            $ids,
            'an explicit status filter should still be able to ask for new bookings directly'
        );
    }
}
