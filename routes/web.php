<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
    ]);
});

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InquiryController;

Route::get('/p/{project}', [ProjectController::class, 'publicShow'])->name('projects.public');
Route::post('/p/{project}/inquire', [InquiryController::class, 'storePublic'])->name('projects.public.inquire');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/images', [ProjectController::class, 'uploadImages'])->name('projects.images');
    Route::post('projects/{project}/relation/store', [ProjectController::class, 'storeRelation'])->name('projects.relation.store');
    Route::post('projects/{project}/relation/delete/{id}', [ProjectController::class, 'deleteRelation'])->name('projects.relation.delete');
    Route::post('projects/{project}/views/{view}/toggle-layer', [ProjectController::class, 'toggleViewLayer'])->name('projects.views.toggle-layer');

    Route::post('media/upload/{model}/{id}', [\App\Http\Controllers\MediaController::class, 'store'])->name('media.upload');
    Route::put('media/{media}', [\App\Http\Controllers\MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [\App\Http\Controllers\MediaController::class, 'destroy'])->name('media.destroy');

    Route::post('projects/{project}/sliders', [SliderController::class, 'store'])->name('sliders.store');
    Route::put('sliders/{slider}', [SliderController::class, 'update'])->name('sliders.update');
    Route::delete('sliders/{slider}', [SliderController::class, 'destroy'])->name('sliders.destroy');
    
    Route::post('projects/{project}/virtual-tours', [\App\Http\Controllers\VirtualTourController::class, 'store'])->name('virtual_tours.store');
    Route::put('virtual-tours/{virtualTour}', [\App\Http\Controllers\VirtualTourController::class, 'update'])->name('virtual_tours.update');
    Route::delete('virtual-tours/{virtualTour}', [\App\Http\Controllers\VirtualTourController::class, 'destroy'])->name('virtual_tours.destroy');
    Route::post('virtual-tours/{virtualTour}/points', [\App\Http\Controllers\VirtualTourController::class, 'storePoint'])->name('virtual_tour_points.store');
    Route::put('virtual-tour-points/{virtualTourPoint}', [\App\Http\Controllers\VirtualTourController::class, 'updatePoint'])->name('virtual_tour_points.update');
    Route::delete('virtual-tour-points/{virtualTourPoint}', [\App\Http\Controllers\VirtualTourController::class, 'destroyPoint'])->name('virtual_tour_points.destroy');
    Route::post('sliders/{slider}/slides', [SliderController::class, 'storeSlide'])->name('slides.store');
    Route::put('slides/{slide}', [SliderController::class, 'updateSlide'])->name('slides.update');
    Route::delete('slides/{slide}', [SliderController::class, 'destroySlide'])->name('slides.destroy');

    Route::get('tracking/stats/{project}', [TrackingController::class, 'stats'])->name('tracking.stats');

    // Contacts (global per team)
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Project ↔ Contact assignment
    Route::post('projects/{project}/contacts/attach', [ContactController::class, 'attachToProject'])->name('contacts.attach');
    Route::delete('projects/{project}/contacts/{contact}/detach', [ContactController::class, 'detachFromProject'])->name('contacts.detach');
    Route::post('projects/{project}/contacts/{contact}/toggle-notify', [ContactController::class, 'toggleNotify'])->name('contacts.toggle-notify');

    // Inquiries (backend)
    Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.update-status');
    Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');

    Route::middleware([\App\Http\Middleware\IsSuperadmin::class])->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
    });
});
