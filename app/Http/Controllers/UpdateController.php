<?php

namespace App\Http\Controllers;

use App\CPU\Helpers;
use App\Model\AdminWallet;
use App\Model\BusinessSetting;
use App\Model\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    public function update_software_index()
    {
        return view('update.update-software');
    }

    public function update_software(Request $request)
    {
        Helpers::setEnvironmentValue('SOFTWARE_ID', 'Mzk4MjcwMTE=');
        Helpers::setEnvironmentValue('BUYER_USERNAME', $request['username']);
        Helpers::setEnvironmentValue('PURCHASE_CODE', $request['purchase_key']);
        Helpers::setEnvironmentValue('SOFTWARE_VERSION', '1.1');
        Helpers::setEnvironmentValue('APP_MODE', 'live');
        Helpers::setEnvironmentValue('SESSION_LIFETIME', '60');

        $data = Helpers::requestSender($request);
        if (!$data['active']) {
            session()->flash('error', 'Invalid credentials');
            return back();
        }

        Artisan::call('migrate', ['--force' => true]);
        $previousRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.php');
        $newRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.txt');
        copy($newRouteServiceProvier, $previousRouteServiceProvier);
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        return redirect('/admin/auth/login');
    }
}
