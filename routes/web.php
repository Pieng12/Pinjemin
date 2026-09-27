<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ItemController as AdminItemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingInvoiceController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BookingPaymentReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncomingBookingController;
use App\Http\Controllers\IncomingPaymentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MyItemController;
use App\Http\Controllers\MyItemPhotoController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PrivatePaymentImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentOutController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('marketplace')->group(function (): void {
    Route::get('/jelajahi', [ItemController::class, 'index'])->name('browse');
    Route::get('/sewakan-barang', RentOutController::class)->name('rent-out');
    Route::get('/items/{item:slug}/availability', [BookingController::class, 'availability'])->name('items.availability');
    Route::get('/items/{item:slug}/calendar', [BookingController::class, 'calendar'])->name('items.calendar');
    Route::get('/items/{item:slug}', [ItemController::class, 'show'])->name('items.show');

    Route::middleware('guest')->group(function (): void {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store']);

        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);

        Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/owner', OwnerDashboardController::class)->name('owner.dashboard');
        Route::post('/items/{item:slug}/bookings', [BookingController::class, 'store'])->name('items.bookings.store');

        Route::get('/my-bookings', [BookingController::class, 'index'])->name('my-bookings.index');
        Route::get('/my-bookings/{booking:booking_code}', [BookingController::class, 'show'])->name('my-bookings.show');
        Route::patch('/my-bookings/{booking:booking_code}/cancel', [BookingController::class, 'cancel'])->name('my-bookings.cancel');
        Route::patch('/my-bookings/{booking:booking_code}/confirm-delivery', [BookingController::class, 'confirmDelivery'])->name('my-bookings.confirm-delivery');
        Route::patch('/my-bookings/{booking:booking_code}/decline-delivery', [BookingController::class, 'declineDelivery'])->name('my-bookings.decline-delivery');
        Route::get('/bookings/{booking:booking_code}/invoice', BookingInvoiceController::class)->name('bookings.invoice');
        Route::get('/bookings/{booking:booking_code}/payment-receipt', BookingPaymentReceiptController::class)->name('bookings.payment-receipt');
        Route::post('/my-bookings/{booking:booking_code}/payments', [BookingPaymentController::class, 'store'])->name('my-bookings.payments.store');
        Route::get('/bookings/{booking:booking_code}/payment-methods/{index}/image', [PrivatePaymentImageController::class, 'bookingMethod'])->whereNumber('index')->name('bookings.payment-method-image');
        Route::get('/bookings/{booking:booking_code}/payments/{payment}/proof', [PrivatePaymentImageController::class, 'proof'])->name('bookings.payment-proof');
        Route::get('/bookings/{booking:booking_code}/refund-proof', [PrivatePaymentImageController::class, 'refund'])->name('bookings.refund-proof');

        Route::get('/incoming-bookings', [IncomingBookingController::class, 'index'])->name('incoming-bookings.index');
        Route::get('/incoming-bookings/{booking:booking_code}', [IncomingBookingController::class, 'show'])->name('incoming-bookings.show');
        Route::patch('/incoming-bookings/{booking:booking_code}/approve', [IncomingBookingController::class, 'approve'])->name('incoming-bookings.approve');
        Route::patch('/incoming-bookings/{booking:booking_code}/reject', [IncomingBookingController::class, 'reject'])->name('incoming-bookings.reject');
        Route::patch('/incoming-bookings/{booking:booking_code}/quote-delivery', [IncomingBookingController::class, 'quoteDelivery'])->name('incoming-bookings.quote-delivery');
        Route::patch('/incoming-bookings/{booking:booking_code}/cancel', [IncomingBookingController::class, 'cancel'])->name('incoming-bookings.cancel');
        Route::patch('/incoming-bookings/{booking:booking_code}/hand-over', [IncomingBookingController::class, 'handOver'])->name('incoming-bookings.hand-over');
        Route::patch('/incoming-bookings/{booking:booking_code}/complete', [IncomingBookingController::class, 'complete'])->name('incoming-bookings.complete');
        Route::patch('/incoming-bookings/{booking:booking_code}/payments/{payment}/review', [IncomingPaymentController::class, 'review'])->name('incoming-bookings.payments.review');
        Route::patch('/incoming-bookings/{booking:booking_code}/cash-and-hand-over', [IncomingPaymentController::class, 'cashAndHandOver'])->name('incoming-bookings.cash-and-hand-over');
        Route::post('/incoming-bookings/{booking:booking_code}/refund', [IncomingPaymentController::class, 'refund'])->name('incoming-bookings.refund');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        Route::get('/payment-methods/{paymentMethod}/image', [PrivatePaymentImageController::class, 'method'])->name('payment-methods.image');

        Route::get('/my-items/create', [MyItemController::class, 'create'])->name('my-items.create');
        Route::resource('my-items', MyItemController::class)->parameters(['my-items' => 'item'])->except(['create']);
        Route::patch('/my-items/{item}/toggle-status', [MyItemController::class, 'toggleStatus'])->name('my-items.toggle-status');
        Route::delete('/my-items/{item}/photos/{photo}', [MyItemPhotoController::class, 'destroy'])->name('my-items.photos.destroy');
        Route::patch('/my-items/{item}/photos/{photo}/primary', [MyItemPhotoController::class, 'primary'])->name('my-items.photos.primary');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth', 'admin'])
        ->group(function (): void {
            Route::get('/', AdminDashboardController::class)->name('dashboard');
            Route::resource('categories', CategoryController::class)->except(['show']);
            Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking:booking_code}', [AdminBookingController::class, 'show'])->name('bookings.show');
            Route::get('items', [AdminItemController::class, 'index'])->name('items.index');
            Route::get('items/{item}', [AdminItemController::class, 'show'])->name('items.show');
            Route::patch('items/{item}/deactivate', [AdminItemController::class, 'deactivate'])->name('items.deactivate');
            Route::get('users', [UserController::class, 'index'])->name('users.index');
        });
});
