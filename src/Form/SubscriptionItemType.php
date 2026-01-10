<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SubscriptionItemType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $isExisting = $options['is_existing'];

        $builder->add('guid', HiddenType::class);

        if ($isExisting) {
            $builder
                ->add('url', TextType::class, [
                    'label' => 'URL',
                    'disabled' => true,
                    'attr' => ['readonly' => true],
                ])
                ->add('remove', SubmitType::class, [
                    'label' => 'Remove',
                ]);
        } else {
            $builder->add('url', UrlType::class, [
                'label' => 'Subscribe to new feed',
                'required' => false,
                'constraints' => [
                    new Assert\Url(message: 'Please enter a valid URL'),
                ],
                'attr' => ['placeholder' => 'https://example.com/feed.xml'],
            ]);
        }

        $builder->add('name', TextType::class, [
            'label' => 'Name',
            'required' => $isExisting,
            'attr' => $isExisting
                ? []
                : ['placeholder' => 'Feed name (auto-detected)'],
        ]);

        if ($isExisting) {
            $builder->add('folder', TextType::class, [
                'label' => 'Folder',
                'required' => false,
                'attr' => ['placeholder' => 'Optional folder name'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_existing' => false,
        ]);
    }
}
