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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Template('TData', 'mixed')]
class PreferencesType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('theme', ChoiceType::class, [
                'label' => 'Theme',
                'choices' => [
                    'Auto' => 'auto',
                    'Light' => 'light',
                    'Dark' => 'dark',
                ],
            ])
            ->add('showNextUnread', CheckboxType::class, [
                'label' => 'When marking as read, skip already read articles',
                'required' => false,
            ])
            ->add('autoMarkAsRead', CheckboxType::class, [
                'label' => 'Automatically mark articles as read after 5 seconds',
                'required' => false,
            ])
            ->add('pullToRefresh', CheckboxType::class, [
                'label' => 'Enable pull to refresh',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
