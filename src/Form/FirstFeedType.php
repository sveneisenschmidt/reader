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
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Template('TData', 'mixed')]
class FirstFeedType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add('feedUrl', UrlType::class, [
            'label' => 'URL',
            'constraints' => [
                new Assert\NotBlank(message: 'Please enter a URL.'),
                new Assert\Url(message: 'Please enter a valid URL.'),
            ],
            'attr' => [
                'placeholder' => 'https://sven.eisenschmidt.website/index.xml',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'first_feed',
        ]);
    }
}
