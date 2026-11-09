<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('userSearchFields')]
    public function test_user_search_is_trimmed_and_bound_to_the_selected_role(string $field): void
    {
        $admin = User::factory()->admin()->create([$field => 'Needle Admin']);
        $user = User::factory()->create([$field => 'Needle User']);
        User::factory()->create(['name' => 'Unrelated', 'email' => 'other@example.test']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => '  Needle  ', 'role' => User::RoleUser]));

        $response->assertOk()->assertViewHas('filters', ['q' => 'Needle', 'role' => User::RoleUser]);
        $this->assertSame([$user->id], $response->viewData('users')->getCollection()->modelKeys());
    }

    public static function userSearchFields(): array
    {
        return ['name' => ['name'], 'email' => ['email']];
    }

    #[DataProvider('categorySearchFields')]
    public function test_category_search_and_status_are_combined(string $field): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->inactive()->create([$field => 'needle-category']);
        Category::factory()->create([$field => 'needle-active']);
        Category::factory()->inactive()->create(['name' => 'Other', 'slug' => 'other']);

        $response = $this->actingAs($admin)->get(route('admin.categories.index', ['q' => 'needle', 'status' => 'inactive']));

        $response->assertOk();
        $this->assertSame([$category->id], $response->viewData('categories')->getCollection()->modelKeys());
    }

    public static function categorySearchFields(): array
    {
        return ['name' => ['name'], 'slug' => ['slug']];
    }

    #[DataProvider('itemSearchFields')]
    public function test_admin_item_search_combines_name_or_owner_with_status_and_category(string $field): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => $field === 'owner' ? 'Needle Owner' : 'Owner']);
        $category = Category::factory()->inactive()->create();
        $name = $field === 'name' ? 'Needle Camera' : 'Camera';
        $item = Item::factory()->inactive()->for($owner, 'user')->for($category)->create(['name' => $name]);
        Item::factory()->for($owner, 'user')->for($category)->create(['name' => $name]);
        Item::factory()->inactive()->for($owner, 'user')->create(['name' => $name]);
        Item::factory()->inactive()->for($category)->create(['name' => 'Unrelated']);

        $response = $this->actingAs($admin)->get(route('admin.items.index', ['q' => 'Needle', 'status' => 'inactive', 'category' => $category->slug]));

        $response->assertOk();
        $this->assertSame([$item->id], $response->viewData('items')->getCollection()->modelKeys());
    }

    public static function itemSearchFields(): array
    {
        return ['name' => ['name'], 'owner' => ['owner']];
    }

    public function test_own_item_filters_cannot_expose_another_owners_items(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $item = Item::factory()->inactive()->for($owner, 'user')->for($category)->create(['name' => 'Needle Camera']);
        Item::factory()->inactive()->for($category)->create(['name' => 'Needle Camera']);
        Item::factory()->for($owner, 'user')->for($category)->create(['name' => 'Needle Active']);
        Item::factory()->inactive()->for($owner, 'user')->create(['name' => 'Needle Other Category']);
        Item::factory()->inactive()->for($owner, 'user')->for($category)->create(['name' => 'Unrelated']);

        $response = $this->actingAs($owner)->get(route('my-items.index', ['q' => 'Needle', 'status' => 'inactive', 'category' => $category->slug]));

        $response->assertOk()->assertViewHas('totalCount', 4);
        $this->assertSame([$item->id], $response->viewData('items')->getCollection()->modelKeys());
    }

    #[DataProvider('itemStatusCases')]
    public function test_item_status_filter_agrees_with_the_displayed_availability(string $route, string $status, array $expectedIndexes): void
    {
        $owner = User::factory()->admin()->create();
        $items = Item::factory()->count(4)->for($owner, 'user')->sequence(
            ['is_active' => true, 'status' => ItemStatus::Available],
            ['is_active' => false, 'status' => ItemStatus::Inactive],
            ['is_active' => true, 'status' => ItemStatus::Inactive],
            ['is_active' => false, 'status' => ItemStatus::Available],
        )->create();

        $response = $this->actingAs($owner)->get(route($route, ['status' => $status]));

        $response->assertOk();
        $this->assertSame(array_map(fn (int $index): int => $items[$index]->id, $expectedIndexes), $response->viewData('items')->getCollection()->modelKeys());
    }

    public static function itemStatusCases(): array
    {
        return [
            'admin active' => ['admin.items.index', 'active', [0]],
            'admin inactive' => ['admin.items.index', 'inactive', [3, 2, 1]],
            'own active' => ['my-items.index', 'active', [0]],
            'own inactive' => ['my-items.index', 'inactive', [3, 2, 1]],
        ];
    }

    #[DataProvider('bookingSearchCases')]
    public function test_booking_search_combines_status_with_code_item_or_counterparty(string $route, string $field): void
    {
        $viewer = User::factory()->admin()->create(['name' => 'Viewer']);
        $owner = $route === 'incoming-bookings.index' ? $viewer : User::factory()->create(['name' => $field === 'owner' ? 'Needle Owner' : 'Owner']);
        $renter = $route === 'my-bookings.index' ? $viewer : User::factory()->create(['name' => $field === 'renter' ? 'Needle Renter' : 'Renter']);
        $item = Item::factory()->for($owner, 'user')->create(['name' => $field === 'item' ? 'Needle Camera' : 'Camera']);
        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create(['booking_code' => $field === 'code' ? 'PNJ-NEEDLE' : 'PNJ-MATCH']);
        Booking::factory()->rejected()->for($item)->for($renter, 'renter')->create(['booking_code' => 'PNJ-NEEDLE-REJECTED']);
        Booking::factory()->create(['booking_code' => 'PNJ-UNRELATED']);

        $response = $this->actingAs($viewer)->get(route($route, ['q' => '  Needle  ', 'status' => 'pending']));

        $response->assertOk()->assertViewHas('filters', ['q' => 'Needle', 'status' => 'pending']);
        $this->assertSame([$booking->id], $response->viewData('bookings')->getCollection()->modelKeys());
    }

    public static function bookingSearchCases(): array
    {
        return [
            'admin code' => ['admin.bookings.index', 'code'],
            'admin item' => ['admin.bookings.index', 'item'],
            'admin renter' => ['admin.bookings.index', 'renter'],
            'admin owner' => ['admin.bookings.index', 'owner'],
            'renter code' => ['my-bookings.index', 'code'],
            'renter item' => ['my-bookings.index', 'item'],
            'renter owner' => ['my-bookings.index', 'owner'],
            'owner code' => ['incoming-bookings.index', 'code'],
            'owner item' => ['incoming-bookings.index', 'item'],
            'owner renter' => ['incoming-bookings.index', 'renter'],
        ];
    }

    public function test_renter_search_never_returns_another_renters_matching_bookings(): void
    {
        $renter = User::factory()->create();
        $owner = User::factory()->create(['name' => 'Needle Owner']);
        $item = Item::factory()->for($owner, 'user')->create(['name' => 'Needle Camera']);
        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create(['booking_code' => 'PNJ-NEEDLE-MINE']);
        Booking::factory()->for($item)->create(['booking_code' => 'PNJ-NEEDLE-PRIVATE']);
        Booking::factory()->rejected()->for($item)->for($renter, 'renter')->create();

        $response = $this->actingAs($renter)->get(route('my-bookings.index', ['q' => 'Needle', 'status' => 'pending']));

        $response->assertOk()->assertViewHas('totalCount', 2)->assertDontSee('PNJ-NEEDLE-PRIVATE');
        $this->assertSame([$booking->id], $response->viewData('bookings')->getCollection()->modelKeys());
    }

    public function test_owner_search_never_returns_bookings_for_another_owners_items(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create(['name' => 'Needle Renter']);
        $item = Item::factory()->for($owner, 'user')->create(['name' => 'Needle Camera']);
        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create(['booking_code' => 'PNJ-NEEDLE-MINE']);
        Booking::factory()->for($renter, 'renter')->create(['booking_code' => 'PNJ-NEEDLE-PRIVATE']);

        $response = $this->actingAs($owner)->get(route('incoming-bookings.index', ['q' => 'Needle', 'status' => 'pending']));

        $response->assertOk()->assertViewHas('totalCount', 1)->assertDontSee('PNJ-NEEDLE-PRIVATE');
        $this->assertSame([$booking->id], $response->viewData('bookings')->getCollection()->modelKeys());
    }

    #[DataProvider('listRoutes')]
    public function test_pagination_preserves_filters_and_existing_page_sizes(string $route, string $key, int $pageSize): void
    {
        $viewer = User::factory()->admin()->create(['name' => 'Viewer']);
        $category = Category::factory()->create(['name' => 'Camera', 'slug' => 'camera']);
        $item = Item::factory()->for($viewer, 'user')->for($category)->create(['name' => 'Camera']);
        match ($key) {
            'users' => User::factory()->count($pageSize + 1)->create(['name' => 'Needle']),
            'categories' => Category::factory()->count($pageSize + 1)->create(['name' => 'Needle']),
            'items' => Item::factory()->count($pageSize + 1)->for($viewer, 'user')->for($category)->create(['name' => 'Needle']),
            'bookings' => Booking::factory()->count($pageSize + 1)->for($item)->for($viewer, 'renter')->create(['renter_note' => 'Needle']),
        };
        $filters = match ($key) {
            'users' => ['q' => 'Needle', 'role' => User::RoleUser],
            'categories' => ['q' => 'Needle', 'status' => 'active'],
            'items' => ['q' => 'Needle', 'status' => 'active', 'category' => 'camera'],
            'bookings' => ['q' => 'Camera', 'status' => 'pending'],
        };

        $response = $this->actingAs($viewer)->get(route($route, [...$filters, 'page' => 2]));

        $response->assertOk();
        $results = $response->viewData($key);
        $this->assertSame($pageSize, $results->perPage());
        $this->assertSame($pageSize + 1, $results->total());
        $this->assertSame(2, $results->currentPage());
        $this->assertSame(1, $results->count());
        parse_str(parse_url($results->url(1), PHP_URL_QUERY), $query);
        $this->assertSame([...$filters, 'page' => '1'], $query);
    }

    public static function listRoutes(): array
    {
        return [
            'users' => ['admin.users.index', 'users', 10],
            'categories' => ['admin.categories.index', 'categories', 10],
            'admin items' => ['admin.items.index', 'items', 15],
            'own items' => ['my-items.index', 'items', 10],
            'admin bookings' => ['admin.bookings.index', 'bookings', 15],
            'renter bookings' => ['my-bookings.index', 'bookings', 10],
            'incoming bookings' => ['incoming-bookings.index', 'bookings', 10],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_lists_reject_invalid_filters(string $route, array $filters, string $field): void
    {
        $viewer = User::factory()->admin()->create();

        $response = $this->actingAs($viewer)->getJson(route($route, $filters));

        $response->assertUnprocessable()->assertInvalid([$field]);
    }

    public static function invalidFilters(): array
    {
        $cases = [];
        foreach (self::listRoutes() as $name => [$route]) {
            $cases[$name.' long search'] = [$route, ['q' => str_repeat('a', 101)], 'q'];
            $cases[$name.' array search'] = [$route, ['q' => ['unexpected']], 'q'];
        }
        foreach (['admin.categories.index', 'admin.items.index', 'my-items.index', 'admin.bookings.index', 'my-bookings.index', 'incoming-bookings.index'] as $route) {
            $cases[$route.' invalid status'] = [$route, ['status' => 'bogus'], 'status'];
            $cases[$route.' array status'] = [$route, ['status' => ['pending']], 'status'];
        }
        $cases['invented owner role'] = ['admin.users.index', ['role' => 'owner'], 'role'];
        $cases['array role'] = ['admin.users.index', ['role' => ['user']], 'role'];
        $cases['unknown admin category'] = ['admin.items.index', ['category' => 'missing'], 'category'];
        $cases['unknown own category'] = ['my-items.index', ['category' => 'missing'], 'category'];
        $cases['array category'] = ['my-items.index', ['category' => ['camera']], 'category'];

        return $cases;
    }

    public function test_filter_chips_remove_only_the_selected_filter_and_reset_the_page(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['slug' => 'camera']);
        Item::factory()->for($category)->create(['name' => 'Needle']);

        $response = $this->actingAs($admin)->get(route('admin.items.index', ['q' => 'Needle', 'status' => 'active', 'category' => 'camera', 'page' => 2]));

        $response->assertSee('Hapus filter Pencarian: Needle')
            ->assertSee('Hapus filter Status: Aktif')
            ->assertSee('Hapus filter Kategori: '.$category->name)
            ->assertSee('Halaman ini tidak berisi hasil');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//nav[@aria-label="Filter aktif"]/a');
        $queries = [];
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $queries[] = $query;
        }
        $this->assertSame([
            ['status' => 'active', 'category' => 'camera'],
            ['q' => 'Needle', 'category' => 'camera'],
            ['q' => 'Needle', 'status' => 'active'],
        ], $queries);
        $form = $xpath->query('//form[@role="search"]')->item(0);
        $this->assertSame('GET', $form->getAttribute('method'));
        $this->assertSame(route('admin.items.index'), $form->getAttribute('action'));
        $this->assertSame(0, $xpath->query('.//input[@name="page"]', $form)->length);
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('admin.items.index').'" and normalize-space(.)="Reset"]', $form)->length);
    }

    public function test_zero_is_a_search_term_and_search_content_is_escaped(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrator', 'email' => 'admin@example.test']);
        $user = User::factory()->create(['name' => 'Person 0', 'email' => 'zero@example.test']);
        User::factory()->create(['name' => 'Other', 'email' => 'other@example.test']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => '0']));

        $response->assertOk();
        $this->assertSame([$user->id], $response->viewData('users')->getCollection()->modelKeys());
        $this->get(route('admin.users.index', ['q' => '<script>alert(1)</script>']))
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_empty_lists_distinguish_no_data_from_no_matches_with_scoped_counts(): void
    {
        $owner = User::factory()->create();
        Item::factory()->create(['name' => 'Someone Elses Camera']);

        $this->actingAs($owner)->get(route('my-items.index', ['q' => 'Camera']))
            ->assertViewHas('totalCount', 0)
            ->assertSee('Belum ada barang yang kamu sewakan.')
            ->assertDontSee('Tidak ada hasil yang cocok');

        Item::factory()->for($owner, 'user')->create(['name' => 'My Camera']);

        $this->get(route('my-items.index', ['q' => 'Missing']))
            ->assertViewHas('totalCount', 1)
            ->assertSee('Tidak ada hasil yang cocok')
            ->assertDontSee('Belum ada barang yang kamu sewakan.');
    }

    public function test_incoming_pending_bookings_link_to_review_without_inline_approval(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner, 'user')->create();
        $booking = Booking::factory()->for($item)->create();

        $response = $this->actingAs($owner)->get(route('incoming-bookings.index'));

        $response->assertSee('Tinjau Pengajuan')->assertSee(route('incoming-bookings.show', $booking))
            ->assertDontSee(route('incoming-bookings.approve', $booking));
    }

    public function test_own_item_management_keeps_requirements_and_mutations_inside_kelola(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner, 'user')->create(['requires_condition_photos' => true, 'requires_identity_verification' => true]);

        $response = $this->actingAs($owner)->get(route('my-items.index'));

        $response->assertSee('Kelola')->assertSee('Lihat')->assertSee('Edit');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//details[summary[normalize-space(.)="Kelola"]]//form')->length);
        $this->assertSame(2, $xpath->query('//details[summary[normalize-space(.)="Kelola"]]//input[@type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//details//form[@action="'.route('my-items.requirements', $item).'"]')->length);
    }

    #[DataProvider('adminRoutes')]
    public function test_regular_users_cannot_search_admin_lists(string $route): void
    {
        $this->actingAs(User::factory()->create())->get(route($route, ['q' => 'Needle']))->assertForbidden();
    }

    public static function adminRoutes(): array
    {
        return [
            ['admin.users.index'], ['admin.categories.index'], ['admin.items.index'], ['admin.bookings.index'],
        ];
    }

    #[DataProvider('guestRoutes')]
    public function test_guests_are_redirected_to_login_before_searching(string $route): void
    {
        $this->get(route($route, ['q' => 'Needle']))->assertRedirect(route('login'));
    }

    public static function guestRoutes(): array
    {
        return array_map(fn (array $case): array => [$case[0]], self::listRoutes());
    }

    #[DataProvider('guestRoutes')]
    public function test_search_accepts_one_hundred_characters_and_blank_searches_have_no_chip(string $route): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route, ['q' => str_repeat('a', 100)]))->assertOk()->assertValid();
        $this->get(route($route, ['q' => '   ']))->assertOk()->assertDontSee('Hapus filter Pencarian:');
    }

    #[DataProvider('bookingRoutes')]
    public function test_booking_search_preserves_computed_expired_status_filter(string $route): void
    {
        $this->freezeTime();
        $viewer = User::factory()->admin()->create();
        $item = Item::factory()->for($viewer, 'user')->create(['name' => 'Camera']);
        $expired = Booking::factory()->for($item)->for($viewer, 'renter')->create([
            'status' => BookingStatus::AwaitingPayment,
            'payment_due_at' => now()->subMinute(),
        ]);
        Booking::factory()->for($item)->for($viewer, 'renter')->create([
            'status' => BookingStatus::AwaitingPayment,
            'payment_due_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($viewer)->get(route($route, ['q' => 'Camera', 'status' => 'expired']));

        $response->assertOk();
        $this->assertSame([$expired->id], $response->viewData('bookings')->getCollection()->modelKeys());
    }

    public static function bookingRoutes(): array
    {
        return [['admin.bookings.index'], ['my-bookings.index'], ['incoming-bookings.index']];
    }
}
