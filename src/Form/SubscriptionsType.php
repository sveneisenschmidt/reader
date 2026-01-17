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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Template('TData', 'mixed')]
class SubscriptionsType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('existing', CollectionType::class, [
                'entry_type' => SubscriptionItemType::class,
                'entry_options' => ['is_existing' => true],
                'allow_delete' => true,
                'label' => false,
            ])
            ->add('new', SubscriptionItemType::class, [
                'is_existing' => false,
                'label' => false,
                'required' => false,
            ])
            ->add('add', SubmitType::class, [
                'label' => 'Subscribe',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
