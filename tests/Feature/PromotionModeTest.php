<?php

namespace Tests\Feature;

use Tests\TestCase;

class PromotionModeTest extends TestCase
{
    public function test_promotion_page_remains_available_while_marketplace_is_disabled(): void
    {
        config()->set('features.marketplace_enabled', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sewa lebih bijak, barang lebih bermanfaat')
            ->assertSee('Kebutuhan sesaat tidak selalu harus dibeli.')
            ->assertSee('https://www.instagram.com/pinjemin911/', false)
            ->assertSee('mailto:pinjemin99@gmail.com', false)
            ->assertSee('images/landing/logo pinjemin.svg', false)
            ->assertSee('images/landing/promo-01.jpeg', false)
            ->assertSee('images/landing/promo-02.jpeg', false)
            ->assertSee('images/landing/promo-03.jpeg', false)
            ->assertSee('images/landing/promo-04.jpeg', false)
            ->assertDontSee('sedang kami siapkan')
            ->assertDontSee('sedang dikembangkan')
            ->assertDontSee('akan segera hadir')
            ->assertDontSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_marketplace_and_account_routes_return_to_promotion_page_when_disabled(): void
    {
        config()->set('features.marketplace_enabled', false);

        foreach (['/jelajahi', '/sewakan-barang', '/login', '/register', '/dashboard', '/admin'] as $path) {
            $this->get($path)
                ->assertRedirect(route('home'))
                ->assertSessionHas('status', 'Halaman tersebut tidak tersedia. Kenali Pinjemin dan temukan informasi terbaru di beranda.');
        }
    }

    public function test_marketplace_routes_remain_available_when_feature_is_enabled(): void
    {
        config()->set('features.marketplace_enabled', true);

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
