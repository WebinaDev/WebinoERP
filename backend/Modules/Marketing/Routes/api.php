<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\MarketingAcademyController;
use Modules\Marketing\Http\Controllers\MarketingAnnouncementsController;
use Modules\Marketing\Http\Controllers\MarketingBlogCategoriesController;
use Modules\Marketing\Http\Controllers\MarketingBlogController;
use Modules\Marketing\Http\Controllers\MarketingDownloadsController;
use Modules\Marketing\Http\Controllers\MarketingFaqController;
use Modules\Marketing\Http\Controllers\MarketingMagazineController;
use Modules\Marketing\Http\Controllers\MarketingMediaController;
use Modules\Marketing\Http\Controllers\MarketingPagesController;
use Modules\Marketing\Http\Controllers\MarketingPortfolioController;
use Modules\Marketing\Http\Controllers\MarketingServiceCategoriesController;
use Modules\Marketing\Http\Controllers\MarketingServicesController;
use Modules\Marketing\Http\Controllers\MarketingSettingsController;
use Modules\Marketing\Http\Controllers\MarketingSolutionsController;
use Modules\Marketing\Http\Controllers\MarketingTeamController;
use Modules\Marketing\Http\Controllers\MarketingTestimonialsController;

Route::get('/settings', [MarketingSettingsController::class, 'show']);
Route::put('/settings', [MarketingSettingsController::class, 'update']);

Route::apiResource('pages', MarketingPagesController::class);

Route::get('/blog/categories', [MarketingBlogCategoriesController::class, 'index']);
Route::post('/blog/categories', [MarketingBlogCategoriesController::class, 'store']);
Route::get('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'show']);
Route::put('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'update']);
Route::delete('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'destroy']);

Route::apiResource('blog', MarketingBlogController::class);

Route::apiResource('magazine', MarketingMagazineController::class);
Route::apiResource('academy', MarketingAcademyController::class);
Route::apiResource('portfolio', MarketingPortfolioController::class);
Route::apiResource('faq', MarketingFaqController::class);
Route::apiResource('team', MarketingTeamController::class);
Route::apiResource('announcements', MarketingAnnouncementsController::class);
Route::apiResource('testimonials', MarketingTestimonialsController::class);
Route::apiResource('downloads', MarketingDownloadsController::class);

Route::get('/service-categories', [MarketingServiceCategoriesController::class, 'index']);
Route::post('/service-categories', [MarketingServiceCategoriesController::class, 'store']);
Route::get('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'show']);
Route::put('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'update']);
Route::delete('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'destroy']);

Route::apiResource('services', MarketingServicesController::class);

Route::get('/solutions/industries', [MarketingSolutionsController::class, 'industries']);
Route::post('/solutions/industries', [MarketingSolutionsController::class, 'storeIndustry']);
Route::put('/solutions/industries/{id}', [MarketingSolutionsController::class, 'updateIndustry']);
Route::delete('/solutions/industries/{id}', [MarketingSolutionsController::class, 'destroyIndustry']);
Route::get('/solutions/industries/{industryId}/pages', [MarketingSolutionsController::class, 'pages']);
Route::post('/solutions/industries/{industryId}/pages', [MarketingSolutionsController::class, 'storePage']);
Route::put('/solutions/pages/{id}', [MarketingSolutionsController::class, 'updatePage']);
Route::delete('/solutions/pages/{id}', [MarketingSolutionsController::class, 'destroyPage']);

Route::get('/media', [MarketingMediaController::class, 'index']);
Route::get('/media/folders', [MarketingMediaController::class, 'folders']);
Route::post('/media/folders', [MarketingMediaController::class, 'storeFolder']);
Route::post('/media/upload', [MarketingMediaController::class, 'upload']);
Route::delete('/media/{id}', [MarketingMediaController::class, 'destroy']);
