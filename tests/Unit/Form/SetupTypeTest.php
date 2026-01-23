<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Form;

use App\Form\SetupType;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class SetupTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [new ValidatorExtension($validator)];
    }

    #[Test]
    public function passwordTooShortIsInvalid(): void
    {
        $form = $this->factory->create(SetupType::class);

        $form->submit([
            'email' => 'test@example.com',
            'password' => [
                'first' => 'Short1!',
                'second' => 'Short1!',
            ],
            'otp' => '123456',
        ]);

        $this->assertFalse($form->isValid());
    }

    #[Test]
    public function weakPasswordIsInvalid(): void
    {
        $form = $this->factory->create(SetupType::class);

        $form->submit([
            'email' => 'test@example.com',
            'password' => [
                'first' => 'passwordpassword',
                'second' => 'passwordpassword',
            ],
            'otp' => '123456',
        ]);

        $this->assertFalse($form->isValid());
    }

    #[Test]
    public function strongPasswordIsValid(): void
    {
        $form = $this->factory->create(SetupType::class);

        $form->submit([
            'email' => 'test@example.com',
            'password' => [
                'first' => 'Str0ng!P@ssw0rd#2024',
                'second' => 'Str0ng!P@ssw0rd#2024',
            ],
            'otp' => '123456',
        ]);

        $this->assertTrue($form->isValid());
    }

    #[Test]
    public function passwordsMustMatch(): void
    {
        $form = $this->factory->create(SetupType::class);

        $form->submit([
            'email' => 'test@example.com',
            'password' => [
                'first' => 'Str0ng!P@ssw0rd#2024',
                'second' => 'DifferentPassword123!',
            ],
            'otp' => '123456',
        ]);

        $this->assertFalse($form->isValid());
    }
}
