<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\User\Service;

class UsernameGenerator
{
    private const ADJECTIVES = [
        'Brave',
        'Bright',
        'Calm',
        'Clever',
        'Cool',
        'Curious',
        'Daring',
        'Eager',
        'Fancy',
        'Fast',
        'Fierce',
        'Fluffy',
        'Friendly',
        'Gentle',
        'Glad',
        'Golden',
        'Happy',
        'Jolly',
        'Kind',
        'Lively',
        'Lucky',
        'Merry',
        'Mighty',
        'Noble',
        'Proud',
        'Quick',
        'Quiet',
        'Sharp',
        'Shiny',
        'Silent',
        'Silver',
        'Smart',
        'Snappy',
        'Sneaky',
        'Soft',
        'Speedy',
        'Spicy',
        'Stellar',
        'Strong',
        'Swift',
        'Tender',
        'Tiny',
        'Tough',
        'Vivid',
        'Warm',
        'Wild',
        'Wise',
        'Witty',
        'Zany',
        'Zippy',
    ];

    private const ANIMALS = [
        'Badger',
        'Bear',
        'Beaver',
        'Bison',
        'Bunny',
        'Cat',
        'Cheetah',
        'Cobra',
        'Coyote',
        'Crane',
        'Crow',
        'Deer',
        'Dingo',
        'Dolphin',
        'Donkey',
        'Dove',
        'Dragon',
        'Eagle',
        'Falcon',
        'Ferret',
        'Finch',
        'Fox',
        'Frog',
        'Giraffe',
        'Goose',
        'Gorilla',
        'Hawk',
        'Hedgehog',
        'Heron',
        'Horse',
        'Husky',
        'Jaguar',
        'Koala',
        'Lemur',
        'Leopard',
        'Lion',
        'Llama',
        'Lobster',
        'Lynx',
        'Mole',
        'Moose',
        'Newt',
        'Orca',
        'Otter',
        'Owl',
        'Panda',
        'Parrot',
        'Pelican',
        'Penguin',
        'Pigeon',
        'Pony',
        'Puffin',
        'Puma',
        'Quail',
        'Rabbit',
        'Raccoon',
        'Raven',
        'Robin',
        'Salmon',
        'Seal',
        'Shark',
        'Sloth',
        'Snail',
        'Snake',
        'Sparrow',
        'Spider',
        'Squid',
        'Stork',
        'Swan',
        'Tiger',
        'Toucan',
        'Turtle',
        'Walrus',
        'Whale',
        'Wolf',
        'Wombat',
        'Zebra',
    ];

    /**
     * Generate a random username like "Happy Dolphin".
     */
    public function generate(): string
    {
        $adjective =
            self::ADJECTIVES[random_int(0, count(self::ADJECTIVES) - 1)];
        $animal = self::ANIMALS[random_int(0, count(self::ANIMALS) - 1)];

        return $adjective.' '.$animal;
    }
}
