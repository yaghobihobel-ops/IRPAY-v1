<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\BasicSettings;
use App\Models\Merchants\DeveloperApiCredential;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeveloperApiController extends Controller
{
    public function index()
    {
        $merchant = auth()->user();
        $credentials = DeveloperApiCredential::auth()
            ->with(['secrets' => function($query) {
                $query->orderBy('scope')->orderByDesc('id');
            }])
            ->latest()
            ->get();

        $keys = $credentials->map(function ($credential) {
            $scopes = [];
            foreach (DeveloperApiCredential::defaultScopes() as $scope) {
                $active = $credential->secrets->where('scope', $scope)->whereNull('revoked_at')->sortByDesc('id')->first();
                $revoked = $credential->secrets->where('scope', $scope)->whereNotNull('revoked_at')->sortByDesc('revoked_at')->first();
                $scopes[$scope] = [
                    'is_active' => (bool) $active,
                    'last_four' => $active?->secret_last_four,
                    'last_used_at' => optional($active?->last_used_at)->toDateTimeString(),
                    'rotated_at' => optional($active?->rotated_at)->toDateTimeString(),
                    'revoked_at' => optional($revoked?->revoked_at)->toDateTimeString(),
                ];
            }

            return [
                'id' => $credential->id,
                'name' => $credential->name,
                'client_id' => $credential->client_id,
                'mode' => $credential->mode,
                'status' => (bool) $credential->status,
                'created_at' => optional($credential->created_at)->toDateTimeString(),
                'last_used_at' => optional($credential->last_used_at)->toDateTimeString(),
                'scopes' => $scopes,
            ];
        });

        $data = [
            'keys' => $keys,
        ];
        $message = ['success' => [__('Merchant Developer Api Key')]];
        return Helpers::success($data, $message);
    }
    public function updateMode(Request $request) {
        $validator = Validator::make($request->all(), [
            'target'     => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $merchant_developer_api = DeveloperApiCredential::where('id',$validated['target'])->auth()->first();

        if(!$merchant_developer_api) {
            $error = ['error'=>[__('Developer API not found!')]];
            return Helpers::error($error);
        }
        $update_mode = ($merchant_developer_api->mode == PaymentGatewayConst::ENV_SANDBOX) ? PaymentGatewayConst::ENV_PRODUCTION : PaymentGatewayConst::ENV_SANDBOX;

        try{
            $merchant_developer_api->update([
                'mode'      => $update_mode,
            ]);
        }catch(Exception $e) {
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }
        $message = ['success'=>[__('Developer API mode updated successfully!')]];
        return Helpers::onlysuccess($message);
    }
    public function generateApiKeys(Request $request){
        $validator = Validator::make($request->all(), [
            'name'     => "required|string|max:100",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $merchant =  authGuardApi()['user'];
        $check = DeveloperApiCredential::auth()->where('name',$validated['name'])->first();
        if( $check){
            $error = ['error'=>[__("The developer API key with this name has already been created")]];
            return Helpers::error($error);
        }
        try{
            [$credential, $secrets] = DeveloperApiCredential::provisionForMerchant($merchant, $validated['name']);
        }catch(Exception $e) {
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }
        $message = ['success'=>[__('Api Keys Created Successfully')]];
        return Helpers::success([
            'credential' => [
                'id' => $credential->id,
                'client_id' => $credential->client_id,
                'name' => $credential->name,
                'secrets' => $secrets,
            ],
        ], $message);

    }
    public function deleteKys(Request $request) {
        $validator = Validator::make($request->all(), [
            'target'     => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $merchant_developer_api = DeveloperApiCredential::where('id',$validated['target'])->auth()->first();
        if(!$merchant_developer_api) {
            $error = ['error'=>[__('Developer API not found!')]];
            return Helpers::error($error);
        }
        try{
            $merchant_developer_api->logActivity('deleted', [], 'merchant', auth()->id());
            $merchant_developer_api->delete();
        }catch(Exception $e) {
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }
        $message = ['success'=>[__('Api Keys Deleted Successfully')]];
        return Helpers::onlysuccess($message);
    }

    public function rotateSecret(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required|integer',
            'scope' => 'required|string|in:' . implode(',', DeveloperApiCredential::defaultScopes()),
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $validated = $validator->validate();

        $credential = DeveloperApiCredential::where('id', $validated['target'])->auth()->first();
        if(!$credential) {
            $error = ['error'=>[__('Developer API not found!')]];
            return Helpers::error($error);
        }

        try {
            [, $secret] = $credential->rotateSecret($validated['scope'], null, 'merchant', auth()->id());
        } catch (Exception $e) {
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }

        $message = ['success'=>[__('Secret rotated successfully. Store the new secret securely.')]];
        return Helpers::success([
            'scope' => $validated['scope'],
            'secret' => $secret,
            'client_id' => $credential->client_id,
        ], $message);
    }

    public function revokeSecret(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required|integer',
            'scope' => 'required|string|in:' . implode(',', DeveloperApiCredential::defaultScopes()),
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $validated = $validator->validate();

        $credential = DeveloperApiCredential::where('id', $validated['target'])->auth()->first();
        if(!$credential) {
            $error = ['error'=>[__('Developer API not found!')]];
            return Helpers::error($error);
        }

        $secret = $credential->revokeScope($validated['scope'], 'merchant', auth()->id());
        if(!$secret) {
            $error = ['error'=>[__('No active secret found for the requested scope.')]];
            return Helpers::error($error);
        }

        $message = ['success'=>[__('Secret revoked successfully')]];
        return Helpers::onlysuccess($message);
    }

}
