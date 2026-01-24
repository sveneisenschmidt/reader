<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Form;

use PhpStaticAnalysis\Attributes\Template;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Template('TData', 'mixed')]
class TotpType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'constraints' => [
                    new Assert\NotBlank(message: 'Password is required.'),
                ],
                'attr' => [
                    'placeholder' => 'Your current password',
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('current_otp', TextType::class, [
                'label' => 'Current verification code',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Current verification code is required.',
                    ),
                    new Assert\Regex(
                        pattern: '/^\d{6}$/',
                        message: 'Verification code must be 6 digits.',
                    ),
                ],
                'attr' => [
                    'placeholder' => '000000',
                    'inputmode' => 'numeric',
                    'maxlength' => 6,
                    'autocomplete' => 'one-time-code',
                ],
            ])
            ->add('new_otp', TextType::class, [
                'label' => 'New verification code',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'New verification code is required.',
                    ),
                    new Assert\Regex(
                        pattern: '/^\d{6}$/',
                        message: 'Verification code must be 6 digits.',
                    ),
                ],
                'attr' => [
                    'placeholder' => '000000',
                    'inputmode' => 'numeric',
                    'maxlength' => 6,
                    'autocomplete' => 'one-time-code',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'totp',
        ]);
    }
}
