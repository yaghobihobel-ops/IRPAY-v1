<?php

namespace App\Http\Controllers\Merchant;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Merchants\DeveloperApiCredential;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeveloperApiController extends Controller
{
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $merchant = auth()->user();
        $page_title = __("API Credentials");
        $apis = DeveloperApiCredential::auth()
            ->with(['secrets' => function($query) {
                $query->orderBy('scope')->orderByDesc('id');
            }])
            ->latest()
            ->paginate(20);
        return view('merchant.sections.api.index',compact('page_title','apis'));
    }

    public function updateMode(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'     => "required",
        ])->validate();
        $merchant_developer_api = DeveloperApiCredential::where('id',$validated['target'])->auth()->first();
        if(!$merchant_developer_api) return back()->with(['error' => [__('Developer API not found!')]]);
        $update_mode = ($merchant_developer_api->mode == PaymentGatewayConst::ENV_SANDBOX) ? PaymentGatewayConst::ENV_PRODUCTION : PaymentGatewayConst::ENV_SANDBOX;
        try{
            $merchant_developer_api->update([
                'mode'      => $update_mode,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with(['success' => [__('Developer API mode updated successfully!')]]);
    }
    public function deleteKys(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'     => "required",
        ])->validate();
        $merchant_developer_api = DeveloperApiCredential::where('id',$validated['target'])->auth()->first();
        try{
            $merchant_developer_api->delete();
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with(['success' => [__('Api Keys Deleted Successfully')]]);
    }
    public function generateApiKeys(Request $request){
        $validated = Validator::make($request->all(),[
            'name'     => "required|string|max:100",
        ])->validate();
        $merchant =  userGuard()['user'];
        $check = DeveloperApiCredential::auth()->where('name',$validated['name'])->first();
        if( $check){
            return back()->with(['error' => [__("The developer API key with this name has already been created")]]);
        }
        try{
            [, $secrets] = DeveloperApiCredential::provisionForMerchant($merchant, $validated['name']);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with([
            'success' => [__('Api Keys Created Successfully')],
            'developer_api_secret' => $secrets,
        ]);

    }

    public function rotateSecret(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'target' => 'required|integer',
            'scope' => 'required|string|in:' . implode(',', DeveloperApiCredential::defaultScopes()),
        ])->validate();

        $credential = DeveloperApiCredential::where('id', $validated['target'])->auth()->first();
        if(!$credential) return back()->with(['error' => [__('Developer API not found!')]]);

        try{
            [, $secret] = $credential->rotateSecret($validated['scope'], null, 'merchant', auth()->id());
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with([
            'success' => [__('Secret rotated successfully. Store the new secret securely.')],
            'developer_api_secret' => [$validated['scope'] => $secret],
        ]);
    }

    public function revokeSecret(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'target' => 'required|integer',
            'scope' => 'required|string|in:' . implode(',', DeveloperApiCredential::defaultScopes()),
        ])->validate();

        $credential = DeveloperApiCredential::where('id', $validated['target'])->auth()->first();
        if(!$credential) return back()->with(['error' => [__('Developer API not found!')]]);

        $secret = $credential->revokeScope($validated['scope'], 'merchant', auth()->id());
        if(!$secret) return back()->with(['error' => [__('No active secret found for the requested scope.')]]);

        return back()->with(['success' => [__('Secret revoked successfully')]]);
    }
}
