<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

/**
 * PAY.JP adapter (ADR 0013). Creates charges via the PAY.JP REST API using the
 * secret key (basic auth, key as username, empty password). The card token comes
 * from PAY.JP Checkout on the payer's browser, so no PAN passes through here
 * (SAQ-A). The secret key is never logged.
 */
final readonly class PayjpGateway implements PaymentGatewayInterface
{
    public function __construct(
        private string $secretKey,
        private string $apiBaseUrl = 'https://api.pay.jp',
        private int $timeoutSeconds = 30,
    ) {
    }

    public function name(): string
    {
        return 'payjp';
    }

    public function createCharge(GatewayChargeRequest $request): GatewayCharge
    {
        if ($this->secretKey === '') {
            throw new PaymentGatewayException('PAY.JP secret key is not configured.');
        }

        $fields = [
            'amount'   => (string) $request->amountCents,
            'currency' => $request->currency,
            'card'     => $request->cardToken,
        ];
        foreach ($request->metadata as $key => $value) {
            $fields['metadata[' . $key . ']'] = $value;
        }

        [$status, $body] = $this->post('/v1/charges', $fields);

        /** @var array<string, mixed> $decoded */
        $decoded = is_array($parsed = json_decode($body, true)) ? $parsed : [];

        if ($status < 200 || $status >= 300) {
            // PAY.JP error payload: { "error": { "message": "...", "code": "..." } }.
            $error   = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $message = is_string($error['message'] ?? null) ? $error['message'] : 'PAY.JP charge failed.';

            throw new PaymentGatewayException($message);
        }

        $id = is_string($decoded['id'] ?? null) ? $decoded['id'] : '';
        if ($id === '') {
            throw new PaymentGatewayException('PAY.JP returned a charge without an id.');
        }

        return new GatewayCharge(
            id: $id,
            paid: (bool) ($decoded['paid'] ?? false),
            amountCents: (int) ($decoded['amount'] ?? $request->amountCents),
            currency: is_string($decoded['currency'] ?? null) ? $decoded['currency'] : $request->currency,
        );
    }

    /**
     * @param array<string, string> $fields
     * @return array{0: int, 1: string}
     */
    private function post(string $path, array $fields): array
    {
        $handle = curl_init($this->apiBaseUrl . $path);
        if ($handle === false) {
            throw new PaymentGatewayException('Unable to initialise the PAY.JP request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $body   = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno  = curl_errno($handle);
        $error  = curl_error($handle);
        curl_close($handle);

        if ($errno !== 0 || !is_string($body)) {
            // Never include the secret key; curl errors do not contain it.
            throw new PaymentGatewayException('PAY.JP request failed: ' . $error);
        }

        return [$status, $body];
    }
}
