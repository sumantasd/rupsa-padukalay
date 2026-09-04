<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    /**
     * Find existing customer by mobile or create a new customer.
     * Returns null if no mobile number is provided (Guest / Walk-in sale).
     */
    public function resolveCustomer(?string $mobileNumber, ?string $name = null, ?string $email = null): ?Customer
    {
        $mobileNumber = trim((string) $mobileNumber);

        if (empty($mobileNumber)) {
            return null; // Guest customer
        }

        $customer = Customer::where('mobile_number', $mobileNumber)->first();

        if (! $customer) {
            $customer = Customer::create([
                'mobile_number' => $mobileNumber,
                'name' => ! empty(trim((string) $name)) ? trim($name) : 'Valued Customer',
                'email' => ! empty(trim((string) $email)) ? trim($email) : null,
            ]);
        } elseif (! empty(trim((string) $name)) && $customer->name === 'Walk-in Customer') {
            $customer->name = trim($name);
            $customer->save();
        }

        return $customer;
    }
}
