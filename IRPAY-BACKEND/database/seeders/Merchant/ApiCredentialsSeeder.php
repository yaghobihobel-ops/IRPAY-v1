<?php

namespace Database\Seeders\Merchant;

use App\Models\Merchants\DeveloperApiCredential;
use App\Models\Merchants\Merchant;
use Illuminate\Database\Seeder;

class ApiCredentialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $merchant = Merchant::find(1);
        if(!$merchant) {
            return;
        }

        if($merchant->developerApi) {
            return;
        }

        DeveloperApiCredential::provisionForMerchant($merchant, 'Test Name');

    }
}
