<?php

namespace App\Tests\Service;

use App\Service\ValidationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolation;

class ValidationServiceTest extends TestCase
{
    private $validator;
    private ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validationService = new ValidationService($this->validator);
    }

    public function testValidateAmountValid(): void
    {
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $errors = $this->validationService->validateAmount(29.99);

        $this->assertEmpty($errors);
    }

    public function testValidateAmountInvalid(): void
    {
        $violation = new ConstraintViolation(
            'Amount must be positive',
            null,
            [],
            -10.0,
            '',
            -10.0
        );
        
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $errors = $this->validationService->validateAmount(-10.0);

        $this->assertCount(1, $errors);
        $this->assertEquals('Amount must be positive', $errors[0]);
    }

    public function testValidateCurrencyValid(): void
    {
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $errors = $this->validationService->validateCurrency('USD');

        $this->assertEmpty($errors);
    }

    public function testValidateCurrencyInvalid(): void
    {
        $violation = new ConstraintViolation(
            'Currency must be 3 uppercase letters',
            null,
            [],
            'us',
            '',
            'us'
        );
        
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $errors = $this->validationService->validateCurrency('us');

        $this->assertCount(1, $errors);
        $this->assertEquals('Currency must be 3 uppercase letters', $errors[0]);
    }

    public function testValidateCustomerIdValid(): void
    {
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $errors = $this->validationService->validateCustomerId('customer_123');

        $this->assertEmpty($errors);
    }

    public function testValidateCustomerIdInvalid(): void
    {
        $violation = new ConstraintViolation(
            'Customer ID can only contain letters, numbers, underscores, and hyphens',
            null,
            [],
            'customer@123',
            '',
            'customer@123'
        );
        
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $errors = $this->validationService->validateCustomerId('customer@123');

        $this->assertCount(1, $errors);
        $this->assertEquals('Customer ID can only contain letters, numbers, underscores, and hyphens', $errors[0]);
    }

    public function testValidateFrequencyValid(): void
    {
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $errors = $this->validationService->validateFrequency('monthly');

        $this->assertEmpty($errors);
    }

    public function testValidateFrequencyInvalid(): void
    {
        $violation = new ConstraintViolation(
            'The value you selected is not a valid choice',
            null,
            [],
            'invalid',
            '',
            'invalid'
        );
        
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $errors = $this->validationService->validateFrequency('invalid');

        $this->assertCount(1, $errors);
        $this->assertEquals('The value you selected is not a valid choice', $errors[0]);
    }

    public function testSanitizeString(): void
    {
        $input = "  Hello\x00World\x1F  ";
        $expected = "HelloWorld";

        $result = $this->validationService->sanitizeString($input);

        $this->assertEquals($expected, $result);
    }

    public function testSanitizeNumeric(): void
    {
        $this->assertEquals(123.45, $this->validationService->sanitizeNumeric('123.45'));
        $this->assertEquals(123.45, $this->validationService->sanitizeNumeric('$123.45'));
        $this->assertEquals(123.45, $this->validationService->sanitizeNumeric('abc123.45def'));
        $this->assertEquals(0.0, $this->validationService->sanitizeNumeric('abc'));
    }

    public function testValidatePaymentDataValid(): void
    {
        $this->validator->expects($this->exactly(3))
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $data = [
            'amount' => 29.99,
            'currency' => 'USD',
            'customer_id' => 'customer_123'
        ];

        $errors = $this->validationService->validatePaymentData($data);

        $this->assertEmpty($errors);
    }

    public function testValidatePaymentDataWithErrors(): void
    {
        $amountViolation = new ConstraintViolation(
            'Amount must be positive',
            null,
            [],
            -10.0,
            '',
            -10.0
        );
        
        $currencyViolation = new ConstraintViolation(
            'Currency must be 3 uppercase letters',
            null,
            [],
            'us',
            '',
            'us'
        );

        $this->validator->expects($this->exactly(3))
            ->method('validate')
            ->willReturnOnConsecutiveCalls(
                new ConstraintViolationList([$amountViolation]),
                new ConstraintViolationList([$currencyViolation]),
                new ConstraintViolationList()
            );

        $data = [
            'amount' => -10.0,
            'currency' => 'us',
            'customer_id' => 'customer_123'
        ];

        $errors = $this->validationService->validatePaymentData($data);

        $this->assertArrayHasKey('amount', $errors);
        $this->assertArrayHasKey('currency', $errors);
        $this->assertArrayNotHasKey('customer_id', $errors);
        $this->assertEquals('Amount must be positive', $errors['amount'][0]);
        $this->assertEquals('Currency must be 3 uppercase letters', $errors['currency'][0]);
    }
}