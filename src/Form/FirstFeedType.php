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
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FirstFeedType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add("feedUrl", UrlType::class, [
            "label" => "Feed URL",
            "constraints" => [
                new Assert\NotBlank(message: "Please enter a feed URL."),
                new Assert\Url(message: "Please enter a valid URL."),
            ],
            "attr" => [
                "placeholder" => "https://sven.eisenschmidt.website/index.xml",
                "autofocus" => true,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "csrf_protection" => true,
            "csrf_token_id" => "first_feed",
        ]);
    }
}
