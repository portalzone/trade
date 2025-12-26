<?php

namespace App\Services\Payment;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('payments.gateways.paystack.secret_key');
        $this->publicKey = config('payments.gateways.paystack.public_key');
    }

    /**
     * Initialize a payment transaction
     */
    public function initializePayment(User $user, float $amount, array $metadata = []): array
    {
        $reference = 'PAY_' . strtoupper(Str::random(16));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', [
            'email' => $user->email,
            'amount' => $amount * 100, // Convert to kobo
            'reference' => $reference,
            'callback_url' => config('payments.gateways.paystack.callback_url'),
            'metadata' => array_merge([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
            ], $metadata),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Paystack initialization failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'reference' => $reference,
            'authorization_url' => $data['data']['authorization_url'],
            'access_code' => $data['data']['access_code'],
        ];
    }

    /**
     * Verify a payment transaction
     */
    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/transaction/verify/' . $reference);

        if (!$response->successful()) {
            throw new \Exception('Paystack verification failed: ' . $response->body());
        }

        $data = $response->json();

        if ($data['data']['status'] !== 'success') {
            throw new \Exception('Payment not successful: ' . $data['data']['gateway_response']);
        }

        return [
            'status' => $data['data']['status'],
            'amount' => $data['data']['amount'] / 100, // Convert from kobo
            'currency' => $data['data']['currency'],
            'reference' => $data['data']['reference'],
            'paid_at' => $data['data']['paid_at'],
            'customer' => $data['data']['customer'],
            'metadata' => $data['data']['metadata'],
        ];
    }

    /**
     * Initialize a transfer (withdrawal)
     */
    public function initiateTransfer(User $user, float $amount, array $bankDetails): array
    {
        // First, create a transfer recipient
        $recipient = $this->createTransferRecipient($user, $bankDetails);

        $reference = 'WTH_' . strtoupper(Str::random(16));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transfer', [
            'source' => 'balance',
            'amount' => $amount * 100, // Convert to kobo
            'recipient' => $recipient['recipient_code'],
            'reason' => 'Wallet withdrawal',
            'reference' => $reference,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Paystack transfer failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'reference' => $reference,
            'transfer_code' => $data['data']['transfer_code'],
            'status' => $data['data']['status'],
            'amount' => $amount,
        ];
    }

    /**
     * Create a transfer recipient
     */
    protected function createTransferRecipient(User $user, array $bankDetails): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transferrecipient', [
            'type' => 'nuban',
            'name' => $user->full_name,
            'account_number' => $bankDetails['account_number'],
            'bank_code' => $bankDetails['bank_code'],
            'currency' => 'NGN',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create transfer recipient: ' . $response->body());
        }

        $data = $response->json();

        return [
            'recipient_code' => $data['data']['recipient_code'],
        ];
    }

    /**
     * List Nigerian banks
     */
    public function listBanks(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/bank', [
            'country' => 'nigeria',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch banks: ' . $response->body());
        }

        return $response->json()['data'];
    }

    /**
     * Verify bank account
     */
    public function verifyBankAccount(string $accountNumber, string $bankCode): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to verify bank account: ' . $response->body());
        }

        $data = $response->json();

        return [
            'account_number' => $data['data']['account_number'],
            'account_name' => $data['data']['account_name'],
        ];
    }
}
