<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ExternalPropertyController;
use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ProjectDuplicateController;

Route::get('/p/{project}', [ProjectController::class, 'publicShow'])->name('projects.public');
Route::post('/p/{project}/inquire', [InquiryController::class, 'storePublic'])->name('projects.public.inquire');
Route::get('/p/{project}/expose/{apartment}', [ApartmentController::class, 'downloadExpose'])->name('apartments.expose');

// Public Cached Map Data Endpoint
Route::get('/p/{project}/map-data', [\App\Http\Controllers\MapController::class, 'getMapData'])->name('projects.public.map-data');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/images', [ProjectController::class, 'uploadImages'])->name('projects.images');
    Route::post('projects/{project}/relation/store', [ProjectController::class, 'storeRelation'])->name('projects.relation.store');
    Route::post('projects/{project}/relation/delete/{id}', [ProjectController::class, 'deleteRelation'])->name('projects.relation.delete');
    Route::post('projects/{project}/floors/reorder', [ProjectController::class, 'reorderFloors'])->name('projects.floors.reorder');
    Route::post('projects/{project}/rooms/reorder', [ProjectController::class, 'reorderRooms'])->name('projects.rooms.reorder');
    Route::post('projects/{project}/layers/reorder', [ProjectController::class, 'reorderLayers'])->name('projects.layers.reorder');
    Route::post('projects/{project}/views/{view}/toggle-layer', [ProjectController::class, 'toggleViewLayer'])->name('projects.views.toggle-layer');
    Route::post('projects/{project}/frames/{frame}/optimize', [ProjectController::class, 'optimizeFrameMedia'])->name('projects.frames.optimize');
    Route::patch('projects/{project}/auto-tour', [ProjectController::class, 'updateAutoTour'])->name('projects.auto-tour.update');

    Route::post('media/upload/{model}/{id}', [\App\Http\Controllers\MediaController::class, 'store'])->name('media.upload');
    Route::put('media/{media}', [\App\Http\Controllers\MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [\App\Http\Controllers\MediaController::class, 'destroy'])->name('media.destroy');
    
    // Depth Maps / Normal Maps Upload for Frames
    Route::post('projects/{project}/frames/{frame}/maps', [\App\Http\Controllers\FrameMapController::class, 'store'])->name('frames.maps.store');
    Route::delete('projects/{project}/maps', [\App\Http\Controllers\FrameMapController::class, 'destroyAll'])->name('projects.maps.destroy');

    Route::post('projects/{project}/sliders', [SliderController::class, 'store'])->name('sliders.store');
    Route::put('sliders/{slider}', [SliderController::class, 'update'])->name('sliders.update');
    Route::delete('sliders/{slider}', [SliderController::class, 'destroy'])->name('sliders.destroy');
    Route::post('projects/{project}/sliders/{slider}/reorder-slides', [SliderController::class, 'reorderSlides'])->name('sliders.reorder-slides');

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
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.update-status');
    Route::post('inquiries/{inquiry}/reply', [InquiryController::class, 'reply'])->name('inquiries.reply');
    Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');
    Route::post('inquiries/bulk-action', [InquiryController::class, 'bulkAction'])->name('inquiries.bulk-action');

    // Project Users
    Route::post('projects/{project}/sync-users', [ProjectController::class, 'syncUsers'])->name('projects.sync-users');

    // Integrations (Project scoped)
    Route::post('projects/{project}/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::put('integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');

    // External Properties (Project scoped)
    Route::post('projects/{project}/external-properties/dispatch-sync', [ExternalPropertyController::class, 'dispatchSync'])->name('external-properties.dispatch-sync');

    // Apartment Sync
    Route::post('apartments/{apartment}/sync-external', [ApartmentController::class, 'syncExternal'])->name('apartments.sync-external');

    // OpenImmo Export
    Route::get('projects/{project}/openimmo-export', [\App\Http\Controllers\OpenImmoExportController::class, 'export'])->name('projects.openimmo-export');

    // Floor / Room Reordering
    Route::post('projects/{project}/reorder-floors', [ProjectController::class, 'reorderFloors'])->name('projects.reorder-floors');
    Route::post('projects/{project}/reorder-rooms', [ProjectController::class, 'reorderRooms'])->name('projects.reorder-rooms');

    // Project Duplication
    Route::post('projects/{project}/duplicate', [ProjectDuplicateController::class, 'duplicate'])->name('projects.duplicate');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('notifications/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');

    // Global Search
    Route::get('search', [SearchController::class, 'search'])->name('search');

    // Exports (CSV)
    Route::get('export/inquiries', [ExportController::class, 'inquiries'])->name('export.inquiries');
    Route::get('export/contacts', [ExportController::class, 'contacts'])->name('export.contacts');
    Route::get('export/visitors/{project}', [ExportController::class, 'visitors'])->name('export.visitors');
    Route::get('export/apartments/{project}', [ExportController::class, 'apartments'])->name('export.apartments');

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // AI Generate
    Route::post('ai/generate', [\App\Http\Controllers\AiController::class, 'generate'])->name('ai.generate');

    Route::middleware([\App\Http\Middleware\IsSuperadmin::class])->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
    });
});
