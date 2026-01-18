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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

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
            ])
            ->add('opml', FileType::class, [
                'label' => 'Import OPML',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        mimeTypes: [
                            'text/xml',
                            'application/xml',
                            'text/x-opml',
                            'application/octet-stream',
                        ],
                        mimeTypesMessage: 'Please upload a valid OPML or XML file.',
                    ),
                ],
            ])
            ->add('import', SubmitType::class, [
                'label' => 'Import',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
