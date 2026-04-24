<?php

namespace Tests\Feature\Offer;

use App\Events\OfferOpened;
use App\Models\Client;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OfferOpenedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_the_offer_opened_event(): void
    {
        Event::fake();

        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '4445556667',
            'address_line1' => 'Main 7',
            'postal_code' => '00-007',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $offer = Offer::create([
            'company_id' => $client->id,
        ]);

        event(new OfferOpened($offer));

        Event::assertDispatched(OfferOpened::class, function (OfferOpened $event) use ($offer) {
            return $event->offer->is($offer);
        });
    }
}
