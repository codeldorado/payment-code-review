<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class Step2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('billing-cc-number', TextType::class, [
                'label' => 'Credit Card Number',
                'attr' => [
                    'placeholder' => '4111111111111111',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a credit card number']),
                    new Length(['min' => 13, 'max' => 19]),
                    new Regex([
                        'pattern' => '/^\d+$/',
                        'message' => 'Credit card number must contain only digits'
                    ])
                ],
            ])
            ->add('billing-cc-exp', TextType::class, [
                'label' => 'Expiration Date (MMYY)',
                'attr' => [
                    'placeholder' => 'MMYY',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an expiration date']),
                    new Regex([
                        'pattern' => '/^(0[1-9]|1[0-2])\d{2}$/',
                        'message' => 'Please enter a valid expiration date in MMYY format'
                    ])
                ],
            ])
            ->add('cvv', TextType::class, [
                'label' => 'CVV',
                'attr' => [
                    'placeholder' => '123',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter CVV']),
                    new Regex([
                        'pattern' => '/^\d{3,4}$/',
                        'message' => 'CVV must be 3 or 4 digits'
                    ])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Complete Payment',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'action' => '',
            'method' => 'POST',
            'csrf_protection' => false, // NMI handles its own security
        ]);

        $resolver->setRequired('action');
    }

    public function getBlockPrefix(): string
    {
        return ''; // This removes the form name prefix from field names
    }
}
