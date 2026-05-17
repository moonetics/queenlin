<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventDetailDispatchController;
use App\Http\Controllers\Admin\ManualFullDateController;
use App\Http\Controllers\Admin\ScheduleDispatchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/schedule', ScheduleController::class)->name('schedule');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', 'admin')->name('dashboard');

    Route::get('admin', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::get('admin/events/create', [AdminEventController::class, 'create'])->name('admin.events.create');
    Route::post('admin/events', [AdminEventController::class, 'store'])->name('admin.events.store');
    Route::get('admin/events/{event}/edit', [AdminEventController::class, 'edit'])->name('admin.events.edit');
    Route::put('admin/events/{event}', [AdminEventController::class, 'update'])->name('admin.events.update');
    Route::post('admin/events/{event}/progress', [AdminEventController::class, 'updateProgress'])->name('admin.events.progress');
    Route::post('admin/events/{event}/discord-detail/send', [EventDetailDispatchController::class, 'sendEvent'])->name('admin.events.discord-detail.send');
    Route::post('admin/events/{event}/discord-detail/update', [EventDetailDispatchController::class, 'updateEvent'])->name('admin.events.discord-detail.update');
    Route::delete('admin/events/{event}/discord-detail', [EventDetailDispatchController::class, 'deleteEvent'])->name('admin.events.discord-detail.destroy');
    Route::delete('admin/events/{event}', [AdminEventController::class, 'destroy'])->name('admin.events.destroy');
    Route::post('admin/manual-full-dates/toggle', [ManualFullDateController::class, 'toggle'])->name('admin.manual-full-dates.toggle');
    Route::post('admin/schedule-dispatches/send', [ScheduleDispatchController::class, 'send'])->name('admin.schedule-dispatches.send');
    Route::post('admin/schedule-dispatches/update', [ScheduleDispatchController::class, 'update'])->name('admin.schedule-dispatches.update');
    Route::delete('admin/schedule-dispatches', [ScheduleDispatchController::class, 'destroy'])->name('admin.schedule-dispatches.destroy');
    Route::post('admin/event-detail-dispatches/date/send', [EventDetailDispatchController::class, 'sendDate'])->name('admin.event-detail-dispatches.date.send');
    Route::post('admin/event-detail-dispatches/date/update', [EventDetailDispatchController::class, 'updateDate'])->name('admin.event-detail-dispatches.date.update');
    Route::delete('admin/event-detail-dispatches/date', [EventDetailDispatchController::class, 'deleteDate'])->name('admin.event-detail-dispatches.date.destroy');
});

require __DIR__.'/settings.php';
