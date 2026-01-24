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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Template('TData', 'mixed')]
class LoginType extends AbstractType
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
                    'autocomplete' => 'username',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'constraints' => [
                    new Assert\NotBlank(message: 'Password is required.'),
                ],
                'attr' => [
                    'placeholder' => 'Your password',
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('otp', TextType::class, [
                'label' => 'Verification code',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Authenticator code is required.',
                    ),
                    new Assert\Regex(
                        pattern: '/^\d{6}$/',
                        message: 'Authenticator code must be 6 digits.',
                    ),
                ],
                'attr' => [
                    'autocomplete' => 'one-time-code',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'authenticate',
        ]);
    }
}
