<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin SPA — shell + assets are built via `npm run build` in /admin into
// public/admin-assets. The fallback serves index.html so React Router
// handles /admin, /admin/live, /admin/menu... on artisan serve + nginx.
Route::get('/admin/{any?}', function () {
    $index = public_path('admin-assets/index.html');
    if (! File::exists($index)) {
        abort(404, 'Admin build missing. Run `npm run build` inside /admin first.');
    }

    return response()->file($index, [
        // SPA shell hamesha fresh — nayi build turant mile, hard refresh ki
        // zaroorat na pade. Hashed js/css pe long-cache nginx se hai.
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Pragma' => 'no-cache',
    ]);
})->where('any', '.*');
