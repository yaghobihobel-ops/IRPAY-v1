<?php

namespace Database\Factories\Merchants;

use App\Models\Merchants\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName;
        $lastName = $this->faker->lastName;
        $username = Str::slug($firstName . '.' . $lastName) . $this->faker->numberBetween(100, 9999);

        return [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'username' => $username,
            'email' => $this->faker->unique()->safeEmail(),
            'mobile_code' => '+1',
            'mobile' => $this->faker->unique()->numerify('555#######'),
            'full_mobile' => $this->faker->unique()->numerify('+1555#######'),
            'password' => bcrypt('password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
        ];
    }
}

