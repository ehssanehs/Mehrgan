<?php

use Illuminate\Support\Facades\Route;

/*
 * The reseller UI lives in Filament (Modules\Reseller\Filament\*). The
 * generated resource route pointed at a ResellerController that was never
 * created, so /resellers returned a 500 and `route:list` crashed. The file is
 * kept because the module's RouteServiceProvider loads it unconditionally.
 */
