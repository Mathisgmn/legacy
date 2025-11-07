<?php

class WordProvider
{
    private const array WORDS = [
        'CASTOR',
        'CINEMA',
        'CYPRES',
        'CITRON',
        'SOURIS',
        'CHANTE',
        'GALANT',
        'POTION',
        'RAISON',
        'GIVRER',
        'TROUPE',
        'JARDIN',
        'MUSCLE',
        'RIVAGE',
        'SABLES',
        'TOURNE',
        'VANNER',
        'VOLCAN',
        'ZENITH',
        'BOTTES',
        'DYNAMO',
        'FLOCON',
        'GRATIN',
        'IMPACT',
        'JALONS',
        'LIGNEE',
        'MOTEUR',
        'NATURE',
        'PILOTE',
        'QUARTZ',
        'RAYONS',
        'STATUE',
        'TAMPON',
        'UNISSE',
        'VOYAGE',
        'YACHTS',
        'ZEBRES',
        'JOUETS',
        'ECLATS',
        'FRAISE',
        'MONTRE',
        'PLAQUE',
        'RUBANS',
        'SOUPLE',
        'TISSER',
        'UTOPIE',
        'VIOLET',
        'WAGONS',
        'XENONS',
    ];

    public static function randomWord(): string
    {
        $eligibleWords = array_values(array_filter(self::WORDS, static fn(string $word) => strlen($word) === 6));
        $word = $eligibleWords ? $eligibleWords[array_rand($eligibleWords)] : 'CASTOR';
        return strtoupper($word);
    }
}