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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add("username", TextType::class, [
                "label" => "Username",
                "constraints" => [
                    new NotBlank(message: "Username is required"),
                ],
                "attr" => [
                    "placeholder" => "Your username",
                ],
            ])
            ->add("theme", ChoiceType::class, [
                "label" => "Theme",
                "choices" => [
                    "Auto" => "auto",
                    "Light" => "light",
                    "Dark" => "dark",
                ],
            ])
            ->add("save", SubmitType::class, [
                "label" => "Update",
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
