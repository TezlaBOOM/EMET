<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\LlmAccount;
use App\Models\LlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LlmAccountEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_at_rest_and_decrypted_on_access(): void
    {
        $provider = LlmProvider::create([
            'name' => 'OpenAI Test',
            'slug' => 'openai-test',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'is_active' => true,
        ]);

        $plainApiKey = 'sk-proj-test-super-secret-key-1234567890';
        $plainApiSecret = 'sec-confidential-secret-987654321';

        $account = LlmAccount::create([
            'provider_id' => $provider->id,
            'name' => 'Primary OpenAI Key',
            'api_key' => $plainApiKey,
            'api_secret' => $plainApiSecret,
            'weight' => 15,
        ]);

        // 1. Sprawdzenie surowego rekordu bezpośrednio w bazie danych (DB raw query)
        $rawAccount = DB::table('llm_accounts')->where('id', $account->id)->first();
        $this->assertNotNull($rawAccount);

        // Klucze w bazie danych NIE MOGĄ być w postaci jawnej (plaintext)
        $this->assertNotEquals($plainApiKey, $rawAccount->api_key);
        $this->assertNotEquals($plainApiSecret, $rawAccount->api_secret);

        // 2. Sprawdzenie dostępu przez Eloquent model (automatyczne odszyfrowanie przez kastowanie 'encrypted')
        $freshAccount = LlmAccount::find($account->id);
        $this->assertEquals($plainApiKey, $freshAccount->api_key);
        $this->assertEquals($plainApiSecret, $freshAccount->api_secret);
    }
}
