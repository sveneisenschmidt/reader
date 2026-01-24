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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Template('TData', 'mixed')]
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
                ->add('save', SubmitType::class, [
                    'label' => 'Update',
                ])
                ->add('remove', SubmitType::class, [
                    'label' => 'Remove',
                ]);
        } else {
            $builder->add('url', UrlType::class, [
                'label' => 'New feed URL',
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

            $builder->add('useArchiveIs', CheckboxType::class, [
                'label' => 'Open links via archive.is',
                'required' => false,
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
