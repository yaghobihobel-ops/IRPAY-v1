<?php

namespace App\Traits\Merchant;

use App\Constants\PaymentGatewayConst;
use App\Models\Admin\Currency;
use App\Models\Merchants\DeveloperApiCredential;
use App\Models\Merchants\GatewaySetting;
use App\Models\Merchants\MerchantLoginLog;
use App\Models\Merchants\MerchantWallet;
use App\Models\Merchants\SandboxWallet;
use Exception;
use Jenssegers\Agent\Agent;

trait LoggedInUsers {

    protected function refreshUserWallets($user) {
        $user_wallets = $user->wallets->pluck("currency_id")->toArray();
        $currencies = Currency::active()->roleHasOne()->pluck("id")->toArray();
        $new_currencies = array_diff($currencies,$user_wallets);
        $new_wallets = [];
        foreach($new_currencies as $item) {
            $new_wallets[] = [
                'merchant_id'       => $user->id,
                'currency_id'   => $item,
                'balance'       => 0,
                'status'        => true,
                'created_at'    => now(),
            ];
        }

        try{
            MerchantWallet::insert($new_wallets);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function createLoginLog($user) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        // $mac = exec('getmac');
        // $mac = explode(" ",$mac);
        // $mac = array_shift($mac);
        $mac = "";

        $data = [
            'merchant_id'       => $user->id,
            'ip'            => $client_ip,
            'mac'           => $mac,
            'city'          => $location['city'] ?? "",
            'country'       => $location['country'] ?? "",
            'longitude'     => $location['lon'] ?? "",
            'latitude'      => $location['lat'] ?? "",
            'timezone'      => $location['timezone'] ?? "",
            'browser'       => $agent->browser() ?? "",
            'os'            => $agent->platform() ?? "",
        ];

        try{
            MerchantLoginLog::create($data);
        }catch(Exception $e) {
            // return false;
        }
    }
    protected function refreshSandboxWallets($user) {

        $user_wallets = $user->sandboxWallets->pluck("currency_id")->toArray();
        $currencies = Currency::active()->roleHasOne()->pluck("id")->toArray();
        $new_currencies = array_diff($currencies,$user_wallets);
        $new_wallets = [];
        foreach($new_currencies as $item) {
            $new_wallets[] = [
                'merchant_id'   => $user->id,
                'currency_id'   => $item,
                'balance'       => 0,
                'status'        => true,
                'created_at'    => now(),
            ];
        }

        try{
            SandboxWallet::insert($new_wallets);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    protected function createDeveloperApi($user) {
        $developing_api = DeveloperApiCredential::where('merchant_id', $user->id)->first();
        try{
            if($developing_api){
                if($developing_api->secrets()->count() === 0) {
                    foreach(DeveloperApiCredential::defaultScopes() as $scope) {
                        $developing_api->issueSecret($scope, null, false, 'merchant', $user->id);
                    }
                    $activeSecrets = $developing_api->activeSecrets();
                    if(!empty($activeSecrets)) {
                        $first = reset($activeSecrets);
                        $developing_api->forceFill(['client_secret' => $first->secret_identifier])->save();
                    }
                }
            }else{
                DeveloperApiCredential::provisionForMerchant($user, 'Primary');
            }

        }catch(Exception $e) {

            return throw new Exception(__("Failed to create developer API. Something went wrong!"));
        }
    }
    protected function createGatewaySetting($user) {
        $gateway_setting = GatewaySetting::where('merchant_id',$user->id)->first();
        try{
            if($gateway_setting){
                $gateway_setting->merchant_id  = $gateway_setting->merchant_id;
                $gateway_setting->wallet_status  = $gateway_setting->wallet_status;
                $gateway_setting->virtual_card_status  = $gateway_setting->virtual_card_status;
                $gateway_setting->master_visa_status  = $gateway_setting->master_visa_status;
                $gateway_setting->save();
            }else{
                GatewaySetting::create([
                'merchant_id'               => $user->id,
                'wallet_status'             => true,
                'virtual_card_status'       => true,
                'master_visa_status'        => false,
                'credentials'               => [
                                                'primary_key' => '',
                                                'secret_key' => ''
                                                ],
                'created_at'        => now(),
            ]);
        }

        }catch(Exception $e) {

            return throw new Exception("Failed to create Gateway Settings. Something went wrong!");
        }
    }
}
