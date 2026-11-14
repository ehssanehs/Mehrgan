<?php

use Illuminate\Support\Facades\Route;

/*
 * This module has no HTTP controller: backups are managed from the Filament
 * page Modules\MatinBackup\Filament\Pages\ManageBackups. The generated
 * resource route pointed at a controller that was never created, so every
 * request to /matinbackups returned a 500 and `route:list` crashed while
 * reflecting the missing class. The file stays because the module's
 * RouteServiceProvider loads it unconditionally.
 */
