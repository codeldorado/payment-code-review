<?php

namespace App\Controller;

use App\Form\SubscriptionType;
use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {}

    #[Route('/', name: 'app_subscription_index')]
    public function index(): Response
    {
        return $this->render('subscription/index.html.twig');
    }

    #[Route('/create', name: 'app_subscription_create')]
    public function create(Request $request): Response
    {
        $form = $this->createForm(SubscriptionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $subscription = $this->subscriptionService->createSubscription(
                    $data['customerId'],
                    $data['amount'],
                    $data['currency'],
                    $data['frequency']
                );

                $this->addFlash('success', 'Subscription created successfully! ID: ' . $subscription->getUuid());
                $this->logger->info('Subscription created via form', [
                    'subscription_id' => $subscription->getUuid(),
                    'customer_id' => $data['customerId']
                ]);

                return $this->redirectToRoute('app_subscription_view', ['uuid' => $subscription->getUuid()]);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Failed to create subscription: ' . $e->getMessage());
                $this->logger->error('Subscription creation failed', [
                    'customer_id' => $data['customerId'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->render('subscription/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{uuid}', name: 'app_subscription_view')]
    public function view(string $uuid): Response
    {
        $subscription = $this->subscriptionService->getSubscription($uuid);

        if (!$subscription) {
            throw $this->createNotFoundException('Subscription not found');
        }

        return $this->render('subscription/view.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/{uuid}/cancel', name: 'app_subscription_cancel', methods: ['POST'])]
    public function cancel(string $uuid): Response
    {
        $success = $this->subscriptionService->cancelSubscription($uuid);

        if ($success) {
            $this->addFlash('success', 'Subscription cancelled successfully');
        } else {
            $this->addFlash('danger', 'Failed to cancel subscription');
        }

        return $this->redirectToRoute('app_subscription_view', ['uuid' => $uuid]);
    }

    #[Route('/api/subscriptions', name: 'app_api_subscriptions', methods: ['GET'])]
    public function apiList(Request $request): JsonResponse
    {
        $customerId = $request->query->get('customer_id');

        if ($customerId) {
            $subscriptions = $this->subscriptionService->getCustomerSubscriptions($customerId);
        } else {
            // For demo purposes, return empty array if no customer specified
            $subscriptions = [];
        }

        $data = [];
        foreach ($subscriptions as $subscription) {
            $data[] = [
                'uuid' => $subscription->getUuid(),
                'customer_id' => $subscription->getCustomerId(),
                'amount' => $subscription->getAmount(),
                'currency' => $subscription->getCurrency(),
                'frequency' => $subscription->getFrequency(),
                'status' => $subscription->getStatus(),
                'next_billing_date' => $subscription->getNextBillingDate()?->format('Y-m-d H:i:s'),
                'created_at' => $subscription->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/subscriptions', name: 'app_api_subscription_create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $requiredFields = ['customer_id', 'amount', 'currency', 'frequency'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "Missing required field: $field"], 400);
            }
        }

        try {
            $subscription = $this->subscriptionService->createSubscription(
                $data['customer_id'],
                (float) $data['amount'],
                $data['currency'],
                $data['frequency'],
                $data['metadata'] ?? []
            );

            return new JsonResponse([
                'uuid' => $subscription->getUuid(),
                'customer_id' => $subscription->getCustomerId(),
                'amount' => $subscription->getAmount(),
                'currency' => $subscription->getCurrency(),
                'frequency' => $subscription->getFrequency(),
                'status' => $subscription->getStatus(),
                'next_billing_date' => $subscription->getNextBillingDate()?->format('Y-m-d H:i:s'),
                'created_at' => $subscription->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error('API subscription creation failed', [
                'customer_id' => $data['customer_id'],
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['error' => 'Failed to create subscription: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/subscriptions/{uuid}/cancel', name: 'app_api_subscription_cancel', methods: ['POST'])]
    public function apiCancel(string $uuid): JsonResponse
    {
        $success = $this->subscriptionService->cancelSubscription($uuid);

        if ($success) {
            return new JsonResponse(['message' => 'Subscription cancelled successfully']);
        } else {
            return new JsonResponse(['error' => 'Failed to cancel subscription'], 400);
        }
    }

    #[Route('/api/rebilling/process', name: 'app_api_rebilling_process', methods: ['POST'])]
    public function apiProcessRebilling(): JsonResponse
    {
        try {
            $results = $this->subscriptionService->processDueBilling();
            
            return new JsonResponse([
                'message' => 'Rebilling processing completed',
                'processed_count' => count($results),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Rebilling processing failed', ['error' => $e->getMessage()]);
            
            return new JsonResponse([
                'error' => 'Rebilling processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/statistics', name: 'app_api_subscription_statistics', methods: ['GET'])]
    public function apiStatistics(): JsonResponse
    {
        $statistics = $this->subscriptionService->getStatistics();
        return new JsonResponse($statistics);
    }

    #[Route('/docs/openapi.yaml', name: 'app_openapi_docs', methods: ['GET'])]
    public function openApiDocs(): Response
    {
        $docsPath = $this->getParameter('kernel.project_dir') . '/docs/openapi.yaml';

        if (!file_exists($docsPath)) {
            throw $this->createNotFoundException('OpenAPI documentation not found');
        }

        $content = file_get_contents($docsPath);

        return new Response($content, 200, [
            'Content-Type' => 'application/x-yaml',
            'Content-Disposition' => 'inline; filename="openapi.yaml"'
        ]);
    }

    #[Route('/docs', name: 'app_api_documentation', methods: ['GET'])]
    public function apiDocumentation(): Response
    {
        return $this->render('docs/api.html.twig');
    }
}