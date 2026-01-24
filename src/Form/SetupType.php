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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Template('TData', 'mixed')]
class SetupType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(message: 'Email is required.'),
                    new Assert\Email(
                        message: 'Please enter a valid email address.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'you@example.com',
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['placeholder' => 'Choose a password'],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => ['placeholder' => 'Repeat password'],
                ],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Password is required.'),
                    new Assert\Length(
                        min: 12,
                        minMessage: 'Password must be at least {{ limit }} characters.',
                    ),
                    new Assert\PasswordStrength(
                        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
                        message: 'Password is too weak. Use a mix of letters, numbers, and symbols.',
                    ),
                    new Assert\NotCompromisedPassword(
                        message: 'This password has been leaked in a data breach. Please choose a different password.',
                    ),
                ],
            ])
            ->add('otp', TextType::class, [
                'label' => 'Verification code',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Verification code is required.',
                    ),
                    new Assert\Regex(
                        pattern: '/^\d{6}$/',
                        message: 'Verification code must be 6 digits.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'setup',
        ]);
    }
}
