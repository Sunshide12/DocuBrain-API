<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Override Lighthouse's CDN-based GraphiQL with a local-asset version.
// The CDN assets (jsdelivr.net) may be unavailable from the browser,
// causing the "Loading…" spinner to hang forever.
// Assets are pre-downloaded inside the container at public/graphiql-assets/.
Route::get('/graphiql', fn () => view('graphiql'))->name('graphiql');
