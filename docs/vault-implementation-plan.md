# Payment Vault System Implementation Plan

## Overview

The Payment Vault System is designed to securely store tokenized payment methods for customers, enabling recurring billing, subscription management, and improved user experience by eliminating the need to re-enter payment information for each transaction.

## Architecture Design

### 1. Security Model

#### Data Protection
- **No Sensitive Data Storage**: The vault stores only tokenized references to payment methods, never actual card numbers or sensitive data
- **Gateway Integration**: Actual payment data is stored securely in the payment gateway's PCI-compliant vault
- **Token-Based Access**: All payment operations use tokens that are meaningless outside the payment gateway context

#### Access Control
- **Customer Isolation**: Payment methods are strictly isolated by customer ID
- **UUID-Based References**: All external references use UUIDs to prevent enumeration attacks
- **Active/Inactive States**: Payment methods can be deactivated without deletion for audit trails

### 2. Database Schema

#### PaymentVault Entity
```sql
CREATE TABLE payment_vault (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL,
    customer_id VARCHAR(255) NOT NULL,
    gateway_customer_id VARCHAR(255) NOT NULL,
    payment_method_token VARCHAR(255) NOT NULL,
    payment_method_type VARCHAR(20) NOT NULL,
    last4_digits VARCHAR(4),
    card_brand VARCHAR(50),
    expiry_month VARCHAR(4),
    expiry_year VARCHAR(4),
    billing_name VARCHAR(255),
    billing_address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP,
    metadata TEXT
);

-- Indexes for performance
CREATE INDEX idx_vault_customer_id ON payment_vault(customer_id);
CREATE INDEX idx_vault_active ON payment_vault(is_active);
CREATE INDEX idx_vault_default ON payment_vault(customer_id, is_default);
```

### 3. Service Layer Architecture

#### VaultService
- **Primary Interface**: Main service for vault operations
- **Validation**: Input validation and sanitization
- **Business Logic**: Default payment method management, expiry handling
- **Audit Logging**: Comprehensive logging of all vault operations

#### Integration Points
- **PaymentGatewayInterface**: Abstracted gateway communication
- **ValidationService**: Centralized input validation
- **SubscriptionService**: Integration for recurring billing

### 4. API Design

#### RESTful Endpoints
```
GET    /api/vault/payment-methods          # List customer's payment methods
POST   /api/vault/payment-methods          # Store new payment method
GET    /api/vault/payment-methods/{uuid}   # Get specific payment method
PUT    /api/vault/payment-methods/{uuid}   # Update payment method
DELETE /api/vault/payment-methods/{uuid}   # Deactivate payment method
POST   /api/vault/payment-methods/{uuid}/set-default  # Set as default
POST   /api/vault/payment-methods/{uuid}/charge       # Process payment
```

## Implementation Phases

### Phase 1: Core Infrastructure (Completed)
- [x] PaymentVault entity and repository
- [x] VaultService with basic operations
- [x] Database migration
- [x] Security and validation framework

### Phase 2: Gateway Integration
- [ ] Extend NmiPaymentGateway for vault operations
- [ ] Customer vault creation in gateway
- [ ] Payment method tokenization
- [ ] Token-based payment processing

### Phase 3: API Implementation
- [ ] VaultController with RESTful endpoints
- [ ] Request/Response DTOs
- [ ] API documentation
- [ ] Rate limiting and security middleware

### Phase 4: Frontend Integration
- [ ] Payment method management UI
- [ ] Default payment method selection
- [ ] Expiry notifications
- [ ] Payment method addition flow

### Phase 5: Advanced Features
- [ ] Automatic expiry detection and cleanup
- [ ] Payment method verification
- [ ] Fraud detection integration
- [ ] Multi-gateway support

## Security Considerations

### 1. PCI Compliance
- **Scope Reduction**: By storing only tokens, PCI scope is minimized
- **Gateway Responsibility**: Actual card data handling is delegated to PCI-compliant gateway
- **Audit Trail**: All operations are logged for compliance

### 2. Data Protection
- **Encryption at Rest**: Database encryption for sensitive metadata
- **Encryption in Transit**: HTTPS for all communications
- **Access Logging**: Comprehensive audit logs

### 3. Fraud Prevention
- **Rate Limiting**: API rate limiting to prevent abuse
- **Validation**: Strict input validation and sanitization
- **Monitoring**: Real-time monitoring of vault operations

## Operational Procedures

### 1. Monitoring
- **Health Checks**: Regular vault system health monitoring
- **Performance Metrics**: Response times and success rates
- **Error Alerting**: Immediate alerts for system failures

### 2. Maintenance
- **Expiry Cleanup**: Automated cleanup of expired payment methods
- **Token Validation**: Periodic validation of stored tokens
- **Data Archival**: Long-term storage of deactivated payment methods

### 3. Disaster Recovery
- **Backup Strategy**: Regular backups of vault metadata
- **Recovery Procedures**: Documented recovery processes
- **Gateway Sync**: Procedures for re-syncing with payment gateway

## Integration Examples

### 1. Storing a Payment Method
```php
// After successful payment, store the payment method
$vault = $vaultService->storePaymentMethod(
    $customerId,
    $gatewayCustomerId,
    $paymentMethodToken,
    [
        'type' => 'credit_card',
        'last4' => '1234',
        'brand' => 'visa',
        'exp_month' => '12',
        'exp_year' => '2025',
        'billing_name' => 'John Doe'
    ]
);
```

### 2. Processing Recurring Payment
```php
// Use stored payment method for subscription billing
$result = $vaultService->processPaymentWithVault(
    $vaultUuid,
    $subscriptionAmount,
    'USD',
    ['subscription_id' => $subscriptionId]
);
```

### 3. Managing Default Payment Method
```php
// Set a payment method as default
$vaultService->setAsDefault($vaultUuid);

// Get customer's default payment method
$defaultMethod = $vaultService->getDefaultPaymentMethod($customerId);
```

## Benefits

### 1. User Experience
- **Faster Checkout**: No need to re-enter payment information
- **Subscription Management**: Easy recurring payment handling
- **Multiple Payment Methods**: Support for multiple stored methods

### 2. Business Benefits
- **Reduced Friction**: Lower cart abandonment rates
- **Recurring Revenue**: Simplified subscription billing
- **Customer Retention**: Improved customer experience

### 3. Technical Benefits
- **Security**: Reduced PCI compliance scope
- **Scalability**: Efficient payment processing
- **Maintainability**: Clean separation of concerns

## Future Enhancements

### 1. Advanced Features
- **Payment Method Verification**: Periodic verification of stored methods
- **Smart Routing**: Intelligent payment method selection
- **Fraud Scoring**: Integration with fraud detection services

### 2. Multi-Gateway Support
- **Gateway Abstraction**: Support for multiple payment gateways
- **Failover Logic**: Automatic failover between gateways
- **Cost Optimization**: Route payments based on cost and success rates

### 3. Analytics and Reporting
- **Usage Analytics**: Payment method usage patterns
- **Success Rate Monitoring**: Track payment success rates by method
- **Customer Insights**: Payment behavior analysis