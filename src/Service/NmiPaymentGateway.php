<?php

namespace App\Service;

use App\Entity\PaymentTransaction;
use App\Enum\PaymentStatus;
use App\Exception\PaymentGatewayException;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use DOMDocument;
use SimpleXMLElement;
use Exception;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NMI Payment Gateway implementation
 *
 * @see https://secure.nmi.com/merchants/resources/integration/integration_portal.php?tid=4a0d25146526480a75f81a71f616c04f#3step_methodology
 */
class NmiPaymentGateway implements PaymentGatewayInterface
{
    private const NMI_THREE_STEP_URL = 'https://secure.nmi.com/api/v2/three-step';

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private HttpClientInterface $client;
    private ValidationService $validationService;
    private string $nmiApiKey;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $paymentLogger,
        HttpClientInterface $client,
        ValidationService $validationService,
        string $nmiApiKey,
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $paymentLogger;
        $this->client = $client;
        $this->validationService = $validationService;
        $this->nmiApiKey = $nmiApiKey;
    }

    /**
     * Step 1: Initialize payment and get form URL
     */
    public function initializePayment(
        float $amount,
        string $currency = 'USD',
        ?string $redirectUrl = null,
        array $billingInfo = [],
        array $shippingInfo = []
    ): array {
        // Validate input parameters
        $validationErrors = $this->validationService->validatePaymentData([
            'amount' => $amount,
            'currency' => $currency
        ]);

        if (!empty($validationErrors)) {
            throw new PaymentGatewayException(
                'Invalid payment data: ' . json_encode($validationErrors),
                'VALIDATION_ERROR',
                ['validation_errors' => $validationErrors]
            );
        }
        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlSale = $xmlRequest->createElement('sale');

        // Required fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'redirect-url', $redirectUrl ?: $_SERVER['HTTP_REFERER']);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'amount', number_format($amount, 2, '.', ''));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'ip-address', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'currency', $currency);

        // Optional order fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-id', uniqid('ORD-'));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-description', 'Payment Gateway Order');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'tax-amount', '0.00');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'shipping-amount', '0.00');

        // Billing information
        if (!empty($billingInfo)) {
            $xmlBillingAddress = $xmlRequest->createElement('billing');
            foreach ($billingInfo as $key => $value) {
                $this->appendXmlNode($xmlRequest, $xmlBillingAddress, $key, $value);
            }
            $xmlSale->appendChild($xmlBillingAddress);
        }

        // Shipping information
        if (!empty($shippingInfo)) {
            $xmlShippingAddress = $xmlRequest->createElement('shipping');
            foreach ($shippingInfo as $key => $value) {
                $this->appendXmlNode($xmlRequest, $xmlShippingAddress, $key, $value);
            }
            $xmlSale->appendChild($xmlShippingAddress);
        }

        $xmlRequest->appendChild($xmlSale);

        // Send request
        $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);

        // Parse response
        $gwResponse = @new SimpleXMLElement($data);
        if ((string)$gwResponse->result == 1) {
            return [
                'status' => 'success',
                'form_url' => (string)$gwResponse->{'form-url'},
            ];
        } else {
            $this->logger->error('Step 1 failed', ['response' => $data]);

            return [
                'status' => 'error',
                'message' => 'Failed to initialize payment',
            ];
        }
    }

    /**
     * Step 3: Complete transaction with token
     */
    public function completeTransaction(Request $request): array
    {
        $tokenId = $request->get('token-id');
        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlCompleteTransaction = $xmlRequest->createElement('complete-action');

        $this->appendXmlNode($xmlRequest, $xmlCompleteTransaction, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlCompleteTransaction, 'token-id', $tokenId);

        $xmlRequest->appendChild($xmlCompleteTransaction);

        // Send request
        $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);

        // Parse response
        $gwResponse = @new SimpleXMLElement($data);

        if ((string)$gwResponse->result == 1) {
            // Save transaction
            $transaction = new PaymentTransaction();
            $transaction->setCreatedAt(new \DateTime());
            $transaction->setUuid(Uuid::v4());
            $transaction->setUsedToken((string)$gwResponse->{'token-id'});
            $transaction->setTransactionId((string)$gwResponse->{'transaction-id'});
            $transaction->setAmount((float)$gwResponse->{'amount'});
            $transaction->setCurrencyCode((string)$gwResponse->{'currency'} ?: 'USD');
            $transaction->setPaymentStatus(PaymentStatus::APPROVED->value);
            $transaction->setLast4Digits(substr((string)$gwResponse->billing->{'cc-number'}, -4));
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            $this->logger->info('Payment successful', ['transaction_id' => (string)$gwResponse->{'transaction-id'}]);

            return [
                'status' => 'success',
                'transaction_id' => (string)$gwResponse->{'transaction-id'},
                'response' => $gwResponse,
            ];
        } elseif ((string)$gwResponse->result == 2) {
            $this->logger->warning('Payment declined', ['response' => $data]);

            return [
                'status' => 'declined',
                'decline_message' => (string)$gwResponse->{'result-text'},
            ];
        } else {
            $this->logger->error('Payment error', ['response' => $data]);

            return [
                'status' => 'error',
                'error_message' => (string)$gwResponse->{'result-text'},
            ];
        }
    }

    private function sendApiRequest(\DOMDocument $xmlRequest, string $gatewayURL): string
    {
        try {
            $response = $this->client->request('POST', $gatewayURL, [
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
                'body' => $xmlRequest->saveXML(),
                'timeout' => 30,
                'verify_peer' => true, // Enable SSL verification for security
            ]);

            return $response->getContent();
        } catch (TransportExceptionInterface $e) {
            throw new PaymentGatewayException(
                "Gateway communication failed: " . $e->getMessage(),
                'TRANSPORT_ERROR',
                ['original_exception' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Helper function to append XML nodes
     */
    private function appendXmlNode($domDocument, $parentNode, $name, $value)
    {
        $childNode = $domDocument->createElement($name);
        $childNodeValue = $domDocument->createTextNode($value);
        $childNode->appendChild($childNodeValue);
        $parentNode->appendChild($childNode);
    }

    public function processRefund(string $originalTransactionId, float $refundAmount): array
    {
        if ($refundAmount <= 0) {
            return ['status' => 'error', 'message' => 'Refund amount must be positive.'];
        }

        $xmlRequest = new DOMDocument('1.0', 'UTF-8');

        $xmlRequest->formatOutput = true;
        $xmlRefund = $xmlRequest->createElement('refund');

        $this->appendXmlNode($xmlRequest, $xmlRefund, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'transaction-id', $originalTransactionId);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'amount', $refundAmount);

        $xmlRequest->appendChild($xmlRefund);

        $xml = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);
        $gwResponse = @new SimpleXMLElement((string)$xml);

        if ((string)$gwResponse->{'result'} !== '1') {
            $this->logger->info(
                'Refund successful',
                [
                    'transaction_id' => (string)$gwResponse->{'transaction-id'},
                    'original_transaction_id' => $originalTransactionId,
                ],
            );

            return ['status' => 'success', 'transaction_id' => (string)$gwResponse->{'transaction-id'}];
        } else {
            $message = $responseArray['responsetext'] ?? 'Refund failed.';
            $this->logger->warning(
                'Refund failed',
                ['response_text' => $message, 'original_transaction_id' => $originalTransactionId],
            );

            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Process rebilling/subscription charge
     */
    public function processRebilling(string $customerId, float $amount, string $currency = 'USD', array $metadata = []): array
    {
        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Rebilling amount must be positive.'];
        }

        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlSale = $xmlRequest->createElement('sale');

        $this->appendXmlNode($xmlRequest, $xmlSale, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'customer-id', $customerId);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'amount', $amount);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'currency', $currency);

        // Add metadata as order description
        if (!empty($metadata)) {
            $description = 'Subscription billing';
            if (isset($metadata['subscription_id'])) {
                $description .= ' - ID: ' . $metadata['subscription_id'];
            }
            if (isset($metadata['billing_cycle'])) {
                $description .= ' - Cycle: ' . $metadata['billing_cycle'];
            }
            $this->appendXmlNode($xmlRequest, $xmlSale, 'order-description', $description);
        }

        $xmlRequest->appendChild($xmlSale);

        try {
            $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);
            $gwResponse = @new SimpleXMLElement($data);

            if ((string)$gwResponse->result == 1) {
                // Save transaction
                $transaction = new PaymentTransaction();
                $transaction->setCreatedAt(new \DateTime());
                $transaction->setUuid(Uuid::v4());
                $transaction->setTransactionId((string)$gwResponse->{'transaction-id'});
                $transaction->setAmount($amount);
                $transaction->setCurrencyCode($currency);
                $transaction->setPaymentStatus(PaymentStatus::APPROVED->value);
                $transaction->setLast4Digits('****'); // Customer vault doesn't expose card details
                $this->entityManager->persist($transaction);
                $this->entityManager->flush();

                $this->logger->info('Rebilling successful', [
                    'customer_id' => $customerId,
                    'transaction_id' => (string)$gwResponse->{'transaction-id'},
                    'amount' => $amount
                ]);

                return [
                    'status' => 'success',
                    'transaction_id' => (string)$gwResponse->{'transaction-id'},
                    'amount' => $amount,
                    'currency' => $currency
                ];
            } else {
                $this->logger->error('Rebilling failed', [
                    'customer_id' => $customerId,
                    'response' => $data
                ]);

                return [
                    'status' => 'error',
                    'message' => (string)$gwResponse->{'result-text'} ?: 'Rebilling failed'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Rebilling exception', [
                'customer_id' => $customerId,
                'exception' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Rebilling processing failed: ' . $e->getMessage()
            ];
        }
    }
}
