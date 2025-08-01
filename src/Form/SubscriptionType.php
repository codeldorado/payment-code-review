<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerId', TextType::class, [
                'label' => 'Customer ID',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 3, 'max' => 255]),
                ],
                'help' => 'Unique identifier for the customer in the payment gateway'
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Subscription Amount',
                'scale' => 2,
                'html5' => true,
                'constraints' => [
                    new NotBlank(),
                    new Positive(),
                ],
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Currency',
                'choices' => [
                    'US Dollar' => 'USD',
                    'Euro' => 'EUR',
                    'British Pound' => 'GBP',
                ],
                'data' => 'USD',
            ])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Billing Frequency',
                'choices' => [
                    'Daily' => 'daily',
                    'Weekly' => 'weekly',
                    'Monthly' => 'monthly',
                    'Yearly' => 'yearly',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create Subscription',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}